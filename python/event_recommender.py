#!/usr/bin/env python3
"""
Event Recommender — cosine similarity on user profile vs event features.

Feature vector:
  - TF-IDF keyword weights from event title  (sklearn TfidfVectorizer)
  - Normalised price
  - Location one-hot
  - Age-range centre normalised to [0,1]
  - Age compatibility flag

When the user has booking history the profile vector is the average of their
past events; otherwise the profile is built directly from the user's own age
and a neutral price/location preference (equal weight → fair ranking by
keyword + age fit).

Age-ineligible events are always excluded.
"""

import argparse, json, re, unicodedata
from datetime import date
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer


def _normalise(text: str) -> str:
    """Lowercase + strip accents so French titles match correctly."""
    nfkd = unicodedata.normalize("NFKD", text or "")
    return "".join(c for c in nfkd if not unicodedata.combining(c)).lower()


# ---------------------------------------------------------------------------
# DB helpers
# ---------------------------------------------------------------------------

def load_db_url_from_env(env_path=None):
    import os
    if env_path is None:
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
                    value = re.sub(r"^mysql://", "mysql+pymysql://", value)
                    value = re.sub(r"\?.*$", "", value)
                    return value

    return "mysql+pymysql://root:@127.0.0.1:3306/projet_pi_web"


def parse_args():
    p = argparse.ArgumentParser()
    p.add_argument("--user_id", type=int, required=True)
    p.add_argument("--limit",   type=int, default=6)
    p.add_argument("--db_url",  default=None)
    return p.parse_args()


def get_user(conn, user_id):
    """Return (age, ville) for the user, both may be None."""
    from sqlalchemy import text
    row = conn.execute(
        text("SELECT date_naissance FROM user WHERE id=:u"),
        {"u": user_id}
    ).fetchone()
    if not row:
        return None, None
    m = row._mapping
    age = None
    if m.get("date_naissance"):
        d = m["date_naissance"]
        t = date.today()
        age = t.year - d.year - ((t.month, t.day) < (d.month, d.day))
    return age, None   # ville not stored on user; extend here if added later


def get_history(conn, user_id):
    from sqlalchemy import text
    rows = conn.execute(text("""
        SELECT e.titre, e.lieu, COALESCE(e.prix,0) AS prix,
               e.age_min, e.age_max
        FROM reservation r JOIN evenement e ON e.id=r.evenement_id
        WHERE r.participant_id=:u AND r.status='CONFIRMED' AND e.annule=0
    """), {"u": user_id})
    return [dict(r._mapping) for r in rows]


def get_upcoming(conn, user_id):
    from sqlalchemy import text
    rows = conn.execute(text("""
        SELECT e.id, e.titre, e.lieu, e.description, e.image,
               COALESCE(e.prix,0) AS prix, e.nb_places, e.age_min, e.age_max,
               e.date_debut,
               (e.nb_places - COALESCE(cnt.n,0)) AS places_restantes
        FROM evenement e
        LEFT JOIN (SELECT evenement_id, COUNT(*) AS n FROM reservation
                   WHERE status='CONFIRMED' GROUP BY evenement_id) cnt
               ON cnt.evenement_id=e.id
        WHERE e.annule=0 AND e.date_debut>NOW()
          AND e.id NOT IN (SELECT evenement_id FROM reservation
                           WHERE participant_id=:u AND status!='CANCELLED')
          AND (e.nb_places - COALESCE(cnt.n,0)) > 0
        ORDER BY e.date_debut ASC
    """), {"u": user_id})
    return [dict(r._mapping) for r in rows]


# ---------------------------------------------------------------------------
# Vectorisation
# ---------------------------------------------------------------------------

def build_tfidf(all_rows):
    """
    Fit a TfidfVectorizer on all event titles (history + upcoming) and return
    (vectorizer, dense_matrix) where matrix[i] corresponds to all_rows[i].
    """
    corpus = [_normalise(r.get("titre", "")) for r in all_rows]
    tfidf  = TfidfVectorizer(
        analyzer="word",
        ngram_range=(1, 2),   # unigrams + bigrams catch "court metrage"
        min_df=1,
        sublinear_tf=True,
    )
    matrix = tfidf.fit_transform(corpus).toarray()
    return tfidf, matrix


def build_extra(row, lieux, max_prix, user_age=None):
    """
    Return the non-text part of the feature vector:
      [0]         normalised price
      [1 .. L]    location one-hot  (L = len(lieux))
      [L+1]       age-range centre  (0 = child, 1 = senior)
      [L+2]       age compatibility flag
    """
    prix  = float(row["prix"]) / max_prix if max_prix else 0.0
    loc   = [1.0 if (row.get("lieu") or "").lower() == l else 0.0 for l in lieux]

    age_min = row.get("age_min")
    age_max = row.get("age_max")
    a_min   = int(age_min) if age_min is not None else 0
    a_max   = int(age_max) if age_max is not None else 100
    centre  = ((a_min + a_max) / 2.0) / 100.0

    compat  = 1.0 if user_age is None else (1.0 if a_min <= user_age <= a_max else 0.0)

    return np.array([prix] + loc + [centre, compat], dtype=float)


def cosine(a, b):
    na, nb = np.linalg.norm(a), np.linalg.norm(b)
    return float(np.dot(a, b) / (na * nb)) if na and nb else 0.0


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    args   = parse_args()
    db_url = args.db_url or load_db_url_from_env()
    try:
        from sqlalchemy import create_engine
        conn = create_engine(db_url).connect()
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e), "recommendations": []}))
        return 1

    try:
        age, ville   = get_user(conn, args.user_id)
        history      = get_history(conn, args.user_id)
        upcoming     = get_upcoming(conn, args.user_id)
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e), "recommendations": []}))
        return 1
    finally:
        conn.close()

    # Hard age filter — never show events the user is ineligible for
    if age is not None:
        upcoming = [
            e for e in upcoming
            if not (e.get("age_min") and age < int(e["age_min"]))
            and not (e.get("age_max") and age > int(e["age_max"]))
        ]

    if not upcoming:
        print(json.dumps({"success": True, "user_id": args.user_id,
                          "has_history": bool(history), "recommendations": []}))
        return 0

    all_rows = history + upcoming
    lieux    = sorted({(r.get("lieu") or "").lower() for r in all_rows} - {""})
    max_prix = max((float(r["prix"]) for r in all_rows), default=1.0) or 1.0
    has_hist = bool(history)

    # --- TF-IDF on all titles (history + upcoming) ---
    _, tfidf_matrix = build_tfidf(all_rows)
    n_hist = len(history)

    if has_hist:
        # Profile = average of history TF-IDF rows + average extra features
        hist_tfidf = tfidf_matrix[:n_hist].mean(axis=0)
        hist_extra = np.mean(
            [build_extra(h, lieux, max_prix, age) for h in history], axis=0
        )
        user_vec = np.concatenate([hist_tfidf, hist_extra])
    else:
        # No history: zero keyword preference; age + location drive ranking
        n_vocab    = tfidf_matrix.shape[1]
        kw_neutral = np.zeros(n_vocab)
        age_centre = (age / 100.0) if age is not None else 0.5
        loc_neutral = np.array([
            1.0 if ville and ville == l else 0.0 for l in lieux
        ])
        extra = np.concatenate([[0.5], loc_neutral, [age_centre, 1.0]])
        user_vec = np.concatenate([kw_neutral, extra])

    scored = []
    for i, ev in enumerate(upcoming):
        ev_tfidf = tfidf_matrix[n_hist + i]
        ev_extra = build_extra(ev, lieux, max_prix, age)
        ev_vec   = np.concatenate([ev_tfidf, ev_extra])
        score    = cosine(user_vec, ev_vec)

        seats = max(int(ev["nb_places"]), 1)
        rem   = max(int(ev.get("places_restantes") or 0), 0)

        scored.append({
            "id":               ev["id"],
            "titre":            ev["titre"],
            "lieu":             ev["lieu"],
            "description":      ev.get("description") or "",
            "image":            ev.get("image"),
            "date_debut":       ev["date_debut"].isoformat()
                                if hasattr(ev["date_debut"], "isoformat")
                                else str(ev["date_debut"]),
            "prix":             float(ev["prix"]),
            "nb_places":        seats,
            "places_restantes": rem,
            "age_min":          int(ev["age_min"]) if ev.get("age_min") is not None else None,
            "age_max":          int(ev["age_max"]) if ev.get("age_max") is not None else None,
            "score":            round(score, 4),
        })

    scored.sort(key=lambda x: x["score"], reverse=True)
    print(json.dumps({
        "success":      True,
        "user_id":      args.user_id,
        "has_history":  has_hist,
        "recommendations": scored[:args.limit],
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
