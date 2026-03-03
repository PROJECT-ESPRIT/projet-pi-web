#!/usr/bin/env python3
"""
Registration prediction script — Python standard library only.
Same usage pattern as event_hotness.py / event_recommender.py: argparse, .env, optional DB.

Modes:
  - Stdin:  read JSON from stdin (used by PHP RegistrationPredictionService).
  - --input FILE: read JSON from file (analyse a full export).
  - --fetch-db:   load DATABASE_URL from .env, run mysql CLI, analyse all registration
                  data from the database (no external Python libs; requires mysql in PATH).

Input JSON: {"monthly": [{"month": "Jan", "count": 5}, ...], "monthly_by_role": [...]}
Output:     {"next_month": {...}, "future_by_type": [...]}
"""
import argparse
import json
import math
import os
import re
import subprocess
import sys
from datetime import datetime

ROLES = ["ROLE_USER", "ROLE_PARTICIPANT", "ROLE_ARTISTE", "ROLE_ADMIN"]
FUTURE_MONTHS = 6


# ---------------------------------------------------------------------------
# .env and DB (stdlib only — same pattern as event_hotness / event_recommender)
# ---------------------------------------------------------------------------

def load_db_url_from_env(env_path=None):
    """Read DATABASE_URL from the nearest .env file (stdlib only)."""
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
                    return value

    return None


def parse_db_url(url):
    """Parse mysql://user:pass@host:port/dbname into components (stdlib only)."""
    if not url or not url.startswith("mysql://"):
        return None
    url = url.replace("mysql://", "", 1).split("?")[0]
    try:
        auth, rest = url.split("@", 1)
        host_part, dbname = rest.rsplit("/", 1)
        user, password = (auth.split(":", 1) + [""])[:2]
        if ":" in host_part:
            host, port = host_part.rsplit(":", 1)
            port = int(port)
        else:
            host = host_part
            port = 3306
        return {"user": user, "password": password, "host": host, "port": port, "dbname": dbname}
    except (ValueError, TypeError):
        return None


def fetch_registrations_from_db(months=12):
    """
    Run mysql CLI to query the database and return the same structure as PHP
    (monthly + monthly_by_role). Requires mysql client in PATH. Stdlib only.
    """
    url = load_db_url_from_env()
    if not url:
        return None, "DATABASE_URL not found in .env"

    parsed = parse_db_url(url)
    if not parsed:
        return None, "Invalid DATABASE_URL"

    # Monthly counts
    q1 = (
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, DATE_FORMAT(created_at, '%b') AS month, COUNT(*) AS cnt "
        "FROM `user` WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {} MONTH) "
        "GROUP BY ym, month ORDER BY ym"
    ).format(max(1, int(months)))

    cmd = [
        "mysql",
        "-h", parsed["host"],
        "-P", str(parsed["port"]),
        "-u", parsed["user"],
        "-N", "-B",  # no headers, tab-separated
        parsed["dbname"],
        "-e", q1,
    ]
    if parsed["password"]:
        cmd.insert(-2, "-p" + parsed["password"])

    try:
        out = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=30,
            cwd=os.path.dirname(os.path.abspath(__file__)),
        )
    except FileNotFoundError:
        return None, "mysql CLI not found (install MySQL client)"
    except subprocess.TimeoutExpired:
        return None, "mysql command timed out"

    if out.returncode != 0:
        return None, (out.stderr or "mysql error").strip() or "mysql failed"

    monthly = []
    for line in out.stdout.strip().splitlines():
        parts = line.split("\t")
        if len(parts) >= 3:
            monthly.append({"month": parts[1], "ym": parts[0], "count": int(parts[2])})

    # Monthly by role (roles column: JSON array like ["ROLE_PARTICIPANT"])
    q2 = (
        "SELECT DATE_FORMAT(created_at, '%b') AS month, DATE_FORMAT(created_at, '%Y-%m') AS ym, "
        "CASE "
        "WHEN roles LIKE '%\"ROLE_ADMIN\"%' THEN 'ROLE_ADMIN' "
        "WHEN roles LIKE '%\"ROLE_ARTISTE\"%' THEN 'ROLE_ARTISTE' "
        "WHEN roles LIKE '%\"ROLE_PARTICIPANT\"%' THEN 'ROLE_PARTICIPANT' "
        "ELSE 'ROLE_USER' END AS role, COUNT(*) AS cnt "
        "FROM `user` WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {} MONTH) "
        "GROUP BY ym, month, role ORDER BY ym"
    ).format(max(1, int(months)))

    cmd2 = [
        "mysql", "-h", parsed["host"], "-P", str(parsed["port"]),
        "-u", parsed["user"], "-N", "-B", parsed["dbname"], "-e", q2,
    ]
    if parsed["password"]:
        cmd2.insert(-2, "-p" + parsed["password"])

    try:
        out2 = subprocess.run(cmd2, capture_output=True, text=True, timeout=30,
                              cwd=os.path.dirname(os.path.abspath(__file__)))
    except Exception as e:
        return None, str(e)

    if out2.returncode != 0:
        return {"monthly": monthly, "monthly_by_role": _monthly_by_role_from_monthly(monthly)}, None

    # Pivot: (month, ym) -> {ROLE_*: count}
    by_month = {}
    for line in out2.stdout.strip().splitlines():
        parts = line.split("\t")
        if len(parts) < 4:
            continue
        month_label, ym, role, cnt = parts[0], parts[1], parts[2], int(parts[3])
        key = (ym, month_label)
        if key not in by_month:
            by_month[key] = {r: 0 for r in ROLES}
        if role in ROLES:
            by_month[key][role] = cnt

    # Preserve same order as monthly (chronological); drop internal "ym" from output
    order = [(row.get("ym"), row["month"]) for row in monthly]
    seen = set()
    monthly_by_role = []
    for ym, m in order:
        if (ym, m) in seen:
            continue
        seen.add((ym, m))
        monthly_by_role.append({"month": m, **by_month.get((ym, m), {r: 0 for r in ROLES})})
    for k in sorted(by_month):
        if k not in seen:
            monthly_by_role.append({"month": k[1], **by_month[k]})
            seen.add(k)
    if not monthly_by_role and monthly:
        monthly_by_role = [{"month": row["month"], **{r: 0 for r in ROLES}} for row in monthly]
    # Strip "ym" from monthly so output format matches PHP
    monthly = [{"month": row["month"], "count": row["count"]} for row in monthly]

    return {"monthly": monthly, "monthly_by_role": monthly_by_role}, None


def _monthly_by_role_from_monthly(monthly):
    """Fallback: no role breakdown, use zeros for all roles."""
    return [{"month": row["month"], **{r: 0 for r in ROLES}} for row in monthly]


# ---------------------------------------------------------------------------
# Prediction (stdlib only: math, datetime, json)
# ---------------------------------------------------------------------------

def add_months(dt, months):
    """Add months to datetime using stdlib only."""
    y, m = dt.year, dt.month
    m += months
    while m > 12:
        m -= 12
        y += 1
    while m < 1:
        m += 12
        y -= 1
    return datetime(y, m, 1)


def linear_regression_prediction(values):
    n = len(values)
    if n <= 1:
        return float(values[0]) if values else 0.0
    sum_x = sum(i for i in range(n))
    sum_y = sum(values)
    sum_xy = sum(i * y for i, y in enumerate(values))
    sum_x2 = sum(i * i for i in range(n))
    denom = n * sum_x2 - sum_x * sum_x
    if abs(denom) < 1e-9:
        return float(values[-1])
    slope = (n * sum_xy - sum_x * sum_y) / denom
    intercept = (sum_y - slope * sum_x) / n
    return intercept + slope * n


def moving_average(values, window=3):
    if not values:
        return 0.0
    slice_ = values[-max(1, window):]
    return sum(slice_) / len(slice_)


def exponential_smoothing(values, alpha=0.45):
    if not values:
        return 0.0
    level = values[0]
    for i in range(1, len(values)):
        level = alpha * values[i] + (1.0 - alpha) * level
    return level


def seasonality_factor(values):
    n = len(values)
    if n < 8:
        return 0.0
    recent = values[-3:]
    older = values[-6:-3]
    if len(older) < 3:
        return 0.0
    recent_avg = sum(recent) / len(recent)
    older_avg = sum(older) / len(older)
    if older_avg <= 1e-9:
        return 0.0
    raw = (recent_avg - older_avg) / older_avg
    return max(-0.15, min(0.15, raw * 0.35))


def confidence_score(values, prediction):
    n = len(values)
    if n < 3:
        return 35
    mean = sum(values) / n
    if mean <= 1e-9:
        return 50
    variance = sum((v - mean) ** 2 for v in values) / n
    std = math.sqrt(variance)
    coef_var = std / mean
    volatility_score = (1.0 - min(1.0, coef_var)) * 55.0
    last = values[-1]
    drift = abs(prediction - last) / max(1.0, mean) if mean else 1.0
    drift_score = (1.0 - min(1.0, drift)) * 30.0
    sample_score = min(15.0, (n / 12.0) * 15.0)
    score = int(round(volatility_score + drift_score + sample_score))
    return max(0, min(100, score))


def confidence_band(values, prediction, confidence_score_val):
    n = len(values)
    if n == 0:
        return 0, 0
    mean = sum(values) / n
    variance = sum((v - mean) ** 2 for v in values) / n
    std = math.sqrt(variance)
    uncertainty = 1.35 - (confidence_score_val / 100.0) * 0.8
    margin = max(1.0, std * uncertainty)
    lower = max(0, round(prediction - margin))
    upper = max(lower, round(prediction + margin))
    return int(lower), int(upper)


def confidence_label(score):
    if score >= 75:
        return "high"
    if score >= 50:
        return "medium"
    return "low"


def next_month_label():
    try:
        d = add_months(datetime.now(), 1)
        return d.strftime("%b %Y")
    except Exception:
        return "N/A"


def predict_next_month(counts):
    counts = [max(0.0, float(c)) for c in counts]
    n = len(counts)
    if n == 0:
        return {
            "predictedCount": 0,
            "trend": "stable",
            "confidence": "low",
            "confidenceScore": 0,
            "lowerBound": 0,
            "upperBound": 0,
            "seasonalityFactor": 0.0,
            "nextMonthLabel": next_month_label(),
        }
    reg = linear_regression_prediction(counts)
    ma = moving_average(counts, 3)
    exp = exponential_smoothing(counts, 0.45)
    seas = seasonality_factor(counts)
    prediction = (0.45 * reg + 0.25 * ma + 0.30 * exp) * (1.0 + seas)
    prediction = max(0.0, prediction)
    last = counts[-1]
    delta = max(1.0, last * 0.07)
    trend = "stable"
    if prediction > last + delta:
        trend = "up"
    elif prediction < last - delta:
        trend = "down"
    conf_score = confidence_score(counts, prediction)
    lower, upper = confidence_band(counts, prediction, conf_score)
    return {
        "predictedCount": int(round(prediction)),
        "trend": trend,
        "confidence": confidence_label(conf_score),
        "confidenceScore": conf_score,
        "lowerBound": lower,
        "upperBound": upper,
        "seasonalityFactor": round(seas, 4),
        "nextMonthLabel": next_month_label(),
    }


def predict_series(values, steps=1):
    """Predict next `steps` values for a series using same ML blend."""
    values = [max(0.0, float(v)) for v in values]
    out = []
    current = list(values)
    for _ in range(steps):
        if not current:
            out.append(0.0)
            continue
        reg = linear_regression_prediction(current)
        ma = moving_average(current, 3)
        exp = exponential_smoothing(current, 0.45)
        seas = seasonality_factor(current) if len(current) >= 8 else 0.0
        pred = (0.45 * reg + 0.25 * ma + 0.30 * exp) * (1.0 + seas)
        pred = max(0.0, pred)
        out.append(pred)
        current.append(pred)
    return out


def future_month_labels(count):
    labels = []
    try:
        for i in range(1, count + 1):
            d = add_months(datetime.now(), i)
            labels.append(d.strftime("%b %Y"))
    except Exception:
        labels = [f"Month +{i}" for i in range(1, count + 1)]
    return labels


def run_predictions(data):
    """Compute next_month and future_by_type from input data (monthly + monthly_by_role)."""
    monthly = data.get("monthly") or []
    monthly_by_role = data.get("monthly_by_role") or []

    counts = [row.get("count", 0) for row in monthly]
    next_month = predict_next_month(counts)

    role_series = {r: [] for r in ROLES}
    for row in monthly_by_role:
        for r in ROLES:
            role_series[r].append(float(row.get(r, 0) or 0))

    future_by_type = []
    labels = future_month_labels(FUTURE_MONTHS)
    for step, label in enumerate(labels):
        row = {"month": label}
        for r in ROLES:
            series = role_series.get(r, [])
            preds = predict_series(series, step + 1)
            row[r] = int(round(preds[-1])) if preds else 0
        future_by_type.append(row)

    return {"next_month": next_month, "future_by_type": future_by_type}


# ---------------------------------------------------------------------------
# CLI (argparse like event_hotness / event_recommender)
# ---------------------------------------------------------------------------

def parse_args():
    p = argparse.ArgumentParser(
        description="Registration prediction (stdlib only). Read JSON from stdin, file, or DB."
    )
    p.add_argument(
        "--input", "-i",
        type=str,
        default=None,
        help="Read input JSON from file instead of stdin",
    )
    p.add_argument(
        "--output", "-o",
        type=str,
        default=None,
        help="Write output JSON to file instead of stdout",
    )
    p.add_argument(
        "--fetch-db",
        action="store_true",
        help="Load DATABASE_URL from .env and fetch all registration data from DB (mysql CLI)",
    )
    p.add_argument(
        "--months",
        type=int,
        default=12,
        help="Months of history when using --fetch-db (default: 12)",
    )
    return p.parse_args()


def main():
    args = parse_args()

    if args.fetch_db:
        data, err = fetch_registrations_from_db(months=args.months)
        if err:
            sys.stderr.write(f"DB fetch failed: {err}\n")
            return 1
        # data is the full input for run_predictions
    else:
        if args.input:
            try:
                with open(args.input, encoding="utf-8") as f:
                    raw = f.read()
            except OSError as e:
                sys.stderr.write(f"Cannot read --input: {e}\n")
                return 1
        else:
            raw = sys.stdin.read()

        try:
            data = json.loads(raw)
        except json.JSONDecodeError as e:
            sys.stderr.write(f"Invalid JSON: {e}\n")
            return 1

    result = run_predictions(data)
    out_str = json.dumps(result)

    if args.output:
        try:
            with open(args.output, "w", encoding="utf-8") as f:
                f.write(out_str)
        except OSError as e:
            sys.stderr.write(f"Cannot write --output: {e}\n")
            return 1
    else:
        print(out_str)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
