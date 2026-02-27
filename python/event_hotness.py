#!/usr/bin/env python3
"""
Hot Event Predictor — ML (RandomForestRegressor)
=================================================
Fetches past events as training data, trains a Random Forest model,
then predicts a hotness score for every upcoming event.

If there are no past events yet, scores all upcoming events as 0.5
and returns ml_mode: false so the UI can show a message.
"""

import argparse
import json


def load_db_url_from_env(env_path=None):
    """Read DATABASE_URL from the nearest .env file and convert to SQLAlchemy format."""
    import os, re
    if env_path is None:
        # Walk up from this script's directory to find .env
        current = os.path.dirname(os.path.abspath(__file__))
        for _ in range(5):
            candidate = os.path.join(current, ".env")
            if os.path.isfile(candidate):
                env_path = candidate
                break
            current = os.path.dirname(current)

    if env_path and os.path.isfile(env_path):
        with open(env_path, encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if line.startswith("DATABASE_URL="):
                    value = line[len("DATABASE_URL="):].strip().strip('"').strip("'")
                    # Convert mysql:// → mysql+pymysql:// and strip Doctrine query params
                    value = re.sub(r"^mysql://", "mysql+pymysql://", value)
                    value = re.sub(r"\?.*$", "", value)
                    return value

    return "mysql+pymysql://root:@127.0.0.1:3306/projet_pi_web"


def parse_args():
    p = argparse.ArgumentParser()
    p.add_argument("--limit",  type=int, default=5)
    p.add_argument("--db_url", default=None)
    return p.parse_args()


def fetch_past_events(conn):
    from sqlalchemy import text
    rows = conn.execute(text("""
        SELECT e.id,
               COALESCE(e.prix, 0)   AS prix,
               e.nb_places,
               COUNT(r.id)           AS reservations_total,
               SUM(CASE WHEN r.date_reservation >= DATE_SUB(e.date_debut, INTERVAL 7 DAY)
                        THEN 1 ELSE 0 END) AS reservations_last_7d
        FROM   evenement e
        LEFT JOIN reservation r ON r.evenement_id = e.id AND r.status = 'CONFIRMED'
        WHERE  e.annule = 0
          AND  e.date_debut <= NOW()
          AND  e.nb_places > 0
        GROUP  BY e.id, e.prix, e.nb_places, e.date_debut
    """))
    return [dict(r._mapping) for r in rows.fetchall()]


def fetch_upcoming_events(conn):
    from sqlalchemy import text
    rows = conn.execute(text("""
        SELECT e.id,
               e.titre,
               e.lieu,
               COALESCE(e.prix, 0)   AS prix,
               e.nb_places,
               e.date_debut,
               DATEDIFF(e.date_debut, NOW())  AS days_until,
               COUNT(r.id)                    AS reservations_total,
               SUM(CASE WHEN r.date_reservation >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        THEN 1 ELSE 0 END)    AS reservations_last_7d
        FROM   evenement e
        LEFT JOIN reservation r ON r.evenement_id = e.id AND r.status = 'CONFIRMED'
        WHERE  e.annule = 0
          AND  e.date_debut > NOW()
        GROUP  BY e.id, e.titre, e.lieu, e.prix, e.nb_places, e.date_debut
        ORDER  BY e.date_debut ASC
    """))
    return [dict(r._mapping) for r in rows.fetchall()]


def to_features(event, max_recent):
    """5 numeric features for one event."""
    seats   = max(int(event["nb_places"]), 1)
    reserved = int(event["reservations_total"] or 0)
    recent  = int(event["reservations_last_7d"] or 0)
    days    = max(int(event.get("days_until") or 0), 0)
    prix    = float(event["prix"])

    return [
        min(reserved / seats, 1.0),              # fill_rate
        recent / max_recent if max_recent else 0, # recent_bookings (normalised)
        max(0.0, 1.0 - days / 90.0),             # urgency
        1.0 if prix > 0 else 0.0,                # price_signal
        min(seats / 1000.0, 1.0),                # capacity
    ]


def hotness_label(score):
    if score >= 0.75: return "En feu"
    if score >= 0.50: return "Populaire"
    if score >= 0.25: return "Tiede"
    return "Calme"


def main():
    args = parse_args()

    db_url = args.db_url or load_db_url_from_env()

    try:
        from sqlalchemy import create_engine
        engine = create_engine(db_url)
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e), "hot_events": []}))
        return 1

    try:
        with engine.connect() as conn:
            past     = fetch_past_events(conn)
            upcoming = fetch_upcoming_events(conn)
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e), "hot_events": []}))
        return 1

    if not upcoming:
        print(json.dumps({"success": True, "ml_mode": False, "ml_info": {}, "hot_events": []}))
        return 0

    # Normalisation reference across all events
    all_events  = past + upcoming
    max_recent  = max(int(ev["reservations_last_7d"] or 0) for ev in all_events) or 1

    ml_mode             = len(past) > 0
    feature_importances = {}

    if ml_mode:
        import numpy as np
        from sklearn.ensemble import RandomForestRegressor

        # Training: past events, target = how full they got
        X = np.array([to_features(ev, max_recent) for ev in past])
        y = np.array([
            min(int(ev["reservations_total"] or 0) / max(int(ev["nb_places"]), 1), 1.0)
            for ev in past
        ])

        model = RandomForestRegressor(n_estimators=100, random_state=42)
        model.fit(X, y)

        feature_importances = {
            name: round(float(v), 4)
            for name, v in zip(
                ["fill_rate", "recent_bookings", "urgency", "price_signal", "capacity"],
                model.feature_importances_
            )
        }

        X_pred = np.array([to_features(ev, max_recent) for ev in upcoming])
        raw_scores = [max(0.0, min(1.0, float(s))) for s in model.predict(X_pred)]
    else:
        # No past data — neutral score so upcoming events still appear
        raw_scores = [0.5] * len(upcoming)

    results = []
    for ev, score in zip(upcoming, raw_scores):
        score = round(score, 4)
        seats = max(int(ev["nb_places"]), 1)
        results.append({
            "id":                   ev["id"],
            "titre":                ev["titre"],
            "lieu":                 ev["lieu"],
            "date_debut":           ev["date_debut"].isoformat() if hasattr(ev["date_debut"], "isoformat") else str(ev["date_debut"]),
            "prix":                 float(ev["prix"]),
            "nb_places":            seats,
            "reservations_total":   int(ev["reservations_total"] or 0),
            "reservations_last_7d": int(ev["reservations_last_7d"] or 0),
            "fill_rate":            round(int(ev["reservations_total"] or 0) / seats, 4),
            "days_until":           max(int(ev["days_until"] or 0), 0),
            "hotness":              score,
            "label":                hotness_label(score),
        })

    results.sort(key=lambda x: x["hotness"], reverse=True)

    print(json.dumps({
        "success":   True,
        "ml_mode":   ml_mode,
        "ml_info": {
            "training_samples":    len(past),
            "feature_importances": feature_importances,
        },
        "hot_events": results[: args.limit],
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
