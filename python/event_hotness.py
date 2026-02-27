#!/usr/bin/env python3
"""
Feature 2 — Hot Event Predictor (Admin)
========================================
Scores every upcoming event with a "hotness" index that predicts
which events are likely to sell out or generate the most buzz.

Score factors (no ML library needed — pure maths):
  - Fill rate          : reservations / total seats           (40 %)
  - Recency of bookings: how many reservations in last 7 days (25 %)
  - Days until event   : urgency — closer = hotter            (20 %)
  - Price signal       : paid events score slightly higher    (15 %)

Usage:
    python event_hotness.py
    python event_hotness.py --limit 10 --db_url "mysql+pymysql://root:@127.0.0.1:3306/projet_pi_web"

Output (stdout) — JSON:
    {
      "success": true,
      "hot_events": [
        {
          "id": 3,
          "titre": "Festival Printemps",
          "lieu": "Sousse",
          "date_debut": "2026-03-20T18:00:00",
          "prix": 30.0,
          "nb_places": 500,
          "reservations_total": 420,
          "reservations_last_7d": 85,
          "fill_rate": 0.84,
          "days_until": 22,
          "hotness": 0.91,
          "label": "🔥 En feu"
        }
      ]
    }
"""

import argparse
import json


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Predict hot upcoming events for the admin.")
    parser.add_argument("--limit", type=int, default=10, help="Max events to return")
    parser.add_argument(
        "--db_url",
        default="mysql+pymysql://root:@127.0.0.1:3306/projet_pi_web",
        help="SQLAlchemy-compatible DB URL",
    )
    return parser.parse_args()


def fetch_events(conn) -> list:
    from sqlalchemy import text
    result = conn.execute(text("""
        SELECT e.id,
               e.titre,
               e.lieu,
               COALESCE(e.prix, 0)                              AS prix,
               e.nb_places,
               e.date_debut,
               DATEDIFF(e.date_debut, NOW())                    AS days_until,
               COUNT(r.id)                                       AS reservations_total,
               SUM(CASE
                     WHEN r.date_reservation >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                     THEN 1 ELSE 0
                   END)                                          AS reservations_last_7d
        FROM   evenement e
        LEFT JOIN reservation r
               ON r.evenement_id = e.id AND r.status = 'CONFIRMED'
        WHERE  e.annule = 0
          AND  e.date_debut > NOW()
        GROUP  BY e.id, e.titre, e.lieu, e.prix, e.nb_places, e.date_debut
        ORDER  BY e.date_debut ASC
    """))
    return [dict(row._mapping) for row in result.fetchall()]


def compute_hotness(event: dict, max_recent: int) -> float:
    seats       = max(int(event["nb_places"]), 1)
    reserved    = int(event["reservations_total"] or 0)
    recent      = int(event["reservations_last_7d"] or 0)
    days_until  = max(int(event["days_until"] or 0), 0)
    prix        = float(event["prix"])

    # Fill rate score  [0-1]
    fill_score = min(reserved / seats, 1.0)

    # Recent bookings score  [0-1]  — normalised against the busiest event
    recent_score = (recent / max_recent) if max_recent > 0 else 0.0

    # Urgency score  [0-1]  — events in ≤7 days = 1.0, ≥90 days = 0.0
    urgency_score = max(0.0, 1.0 - days_until / 90.0)

    # Price signal  [0-1]  — paid events (prix > 0) get a small boost
    price_score = 0.8 if prix > 0 else 0.3

    hotness = (
        0.40 * fill_score
        + 0.25 * recent_score
        + 0.20 * urgency_score
        + 0.15 * price_score
    )
    return round(hotness, 4)


def hotness_label(score: float) -> str:
    if score >= 0.75:
        return "En feu"
    if score >= 0.50:
        return "Populaire"
    if score >= 0.25:
        return "Tiede"
    return "Calme"


def main() -> int:
    args = parse_args()

    try:
        from sqlalchemy import create_engine
        engine = create_engine(args.db_url)
    except Exception as exc:
        print(json.dumps({"success": False, "error": f"DB connection failed: {exc}", "hot_events": []}))
        return 1

    try:
        with engine.connect() as conn:
            events = fetch_events(conn)
    except Exception as exc:
        print(json.dumps({"success": False, "error": f"Query failed: {exc}", "hot_events": []}))
        return 1

    if not events:
        print(json.dumps({"success": True, "hot_events": []}))
        return 0

    max_recent = max(int(ev["reservations_last_7d"] or 0) for ev in events) or 1

    scored = []
    for ev in events:
        h = compute_hotness(ev, max_recent)
        scored.append({
            "id":                   ev["id"],
            "titre":                ev["titre"],
            "lieu":                 ev["lieu"],
            "date_debut":           ev["date_debut"].isoformat() if hasattr(ev["date_debut"], "isoformat") else str(ev["date_debut"]),
            "prix":                 float(ev["prix"]),
            "nb_places":            int(ev["nb_places"]),
            "reservations_total":   int(ev["reservations_total"] or 0),
            "reservations_last_7d": int(ev["reservations_last_7d"] or 0),
            "fill_rate":            round(int(ev["reservations_total"] or 0) / max(int(ev["nb_places"]), 1), 4),
            "days_until":           max(int(ev["days_until"] or 0), 0),
            "hotness":              h,
            "label":                hotness_label(h),
        })

    scored.sort(key=lambda x: x["hotness"], reverse=True)

    print(json.dumps({"success": True, "hot_events": scored[: args.limit]}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
