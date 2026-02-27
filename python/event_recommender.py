#!/usr/bin/env python3
"""
Feature 1 — Personalized Event Recommender
===========================================
Recommends upcoming events to a participant based on their past reservations.
Uses a simple content-based scoring (no ML library needed).

Usage:
    python event_recommender.py --user_id 42
    python event_recommender.py --user_id 42 --limit 5 --db_url "mysql+pymysql://root:@127.0.0.1:3306/projet_pi_web"

Output (stdout) — JSON:
    {
      "success": true,
      "user_id": 42,
      "recommendations": [
        {
          "id": 7,
          "titre": "Concert Jazz",
          "lieu": "Tunis",
          "date_debut": "2026-03-15T20:00:00",
          "prix": 25.0,
          "nb_places": 200,
          "places_restantes": 45,
          "score": 0.87
        }
      ]
    }
"""

import argparse
import json
import sys


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Recommend events for a participant.")
    parser.add_argument("--user_id", type=int, required=True, help="Participant user ID")
    parser.add_argument("--limit", type=int, default=5, help="Max recommendations to return")
    parser.add_argument(
        "--db_url",
        default="mysql+pymysql://root:@127.0.0.1:3306/projet_pi_web",
        help="SQLAlchemy-compatible DB URL",
    )
    return parser.parse_args()


def fetch_user_history(conn, user_id: int) -> list:
    from sqlalchemy import text
    result = conn.execute(text("""
        SELECT e.lieu,
               COALESCE(e.prix, 0)          AS prix,
               HOUR(e.date_debut)            AS hour_debut,
               DAYOFWEEK(e.date_debut)       AS dow_debut
        FROM   reservation r
        JOIN   evenement e ON e.id = r.evenement_id
        WHERE  r.participant_id = :user_id
          AND  r.status = 'CONFIRMED'
          AND  e.annule = 0
    """), {"user_id": user_id})
    return [dict(row._mapping) for row in result.fetchall()]


def fetch_upcoming_events(conn, user_id: int) -> list:
    from sqlalchemy import text
    result = conn.execute(text("""
        SELECT e.id,
               e.titre,
               e.lieu,
               COALESCE(e.prix, 0)                              AS prix,
               e.nb_places,
               e.date_debut,
               HOUR(e.date_debut)                               AS hour_debut,
               DAYOFWEEK(e.date_debut)                          AS dow_debut,
               (e.nb_places - COALESCE(cnt.reserved, 0))        AS places_restantes
        FROM   evenement e
        LEFT JOIN (
            SELECT evenement_id, COUNT(*) AS reserved
            FROM   reservation
            WHERE  status = 'CONFIRMED'
            GROUP  BY evenement_id
        ) cnt ON cnt.evenement_id = e.id
        WHERE  e.annule = 0
          AND  e.date_debut > NOW()
          AND  e.id NOT IN (
              SELECT evenement_id
              FROM   reservation
              WHERE  participant_id = :user_id
                AND  status != 'CANCELLED'
          )
          AND  (e.nb_places - COALESCE(cnt.reserved, 0)) > 0
        ORDER  BY e.date_debut ASC
    """), {"user_id": user_id})
    return [dict(row._mapping) for row in result.fetchall()]


def build_profile(history: list) -> dict:
    if not history:
        return {}
    prices = [float(r["prix"]) for r in history]
    hours  = [int(r["hour_debut"] or 12) for r in history]
    dows   = [int(r["dow_debut"]  or 1)  for r in history]
    lieux  = [r["lieu"] for r in history if r["lieu"]]

    lieu_freq: dict = {}
    for l in lieux:
        lieu_freq[l] = lieu_freq.get(l, 0) + 1

    return {
        "avg_price":     sum(prices) / len(prices),
        "max_price":     max(prices) or 1.0,
        "avg_hour":      sum(hours)  / len(hours),
        "preferred_dow": max(set(dows), key=dows.count),
        "top_lieu":      max(lieu_freq, key=lieu_freq.get) if lieu_freq else None,
    }


def score_event(event: dict, profile: dict) -> float:
    total     = max(int(event["nb_places"]), 1)
    remaining = max(int(event["places_restantes"] or 0), 0)
    fill_rate = 1.0 - (remaining / total)

    if not profile:
        return round(0.3 + 0.4 * fill_rate, 4)

    # Price proximity  (weight 35 %)
    price_diff  = abs(float(event["prix"]) - profile["avg_price"]) / max(profile["max_price"], 1.0)
    price_score = max(0.0, 1.0 - price_diff)

    # Location match   (weight 30 %)
    if profile.get("top_lieu") and event.get("lieu"):
        loc_score = 1.0 if event["lieu"].lower() == profile["top_lieu"].lower() else 0.2
    else:
        loc_score = 0.5

    # Time-of-day      (weight 20 %)
    hour_diff  = abs(int(event["hour_debut"] or 12) - profile["avg_hour"])
    hour_score = max(0.0, 1.0 - hour_diff / 12.0)

    # Day-of-week      (weight 15 %)
    dow_score = 1.0 if int(event["dow_debut"] or 1) == profile["preferred_dow"] else 0.3

    raw = 0.35 * price_score + 0.30 * loc_score + 0.20 * hour_score + 0.15 * dow_score
    return round(raw, 4)


def main() -> int:
    args = parse_args()

    try:
        from sqlalchemy import create_engine
        engine = create_engine(args.db_url)
    except Exception as exc:
        print(json.dumps({"success": False, "error": f"DB connection failed: {exc}", "recommendations": []}))
        return 1

    try:
        with engine.connect() as conn:
            history  = fetch_user_history(conn, args.user_id)
            upcoming = fetch_upcoming_events(conn, args.user_id)
    except Exception as exc:
        print(json.dumps({"success": False, "error": f"Query failed: {exc}", "recommendations": []}))
        return 1

    profile = build_profile(history)

    scored = []
    for ev in upcoming:
        scored.append({
            "id":               ev["id"],
            "titre":            ev["titre"],
            "lieu":             ev["lieu"],
            "date_debut":       ev["date_debut"].isoformat() if hasattr(ev["date_debut"], "isoformat") else str(ev["date_debut"]),
            "prix":             float(ev["prix"]),
            "nb_places":        int(ev["nb_places"]),
            "places_restantes": int(ev["places_restantes"] or 0),
            "score":            score_event(ev, profile),
        })

    scored.sort(key=lambda x: x["score"], reverse=True)

    print(json.dumps({
        "success":         True,
        "user_id":         args.user_id,
        "recommendations": scored[: args.limit],
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
