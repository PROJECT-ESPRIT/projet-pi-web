#!/usr/bin/env python3
"""
Registration prediction — Ridge regression on lagged monthly counts.
Input: JSON with monthly + monthly_by_role. Output: next_month + future_by_type.
Modes: stdin, --input FILE, --fetch-db (mysql CLI).
"""
import argparse
import json
import os
import subprocess
import sys
from datetime import datetime

import numpy as np
from sklearn.linear_model import Ridge

ROLES = ["ROLE_USER", "ROLE_PARTICIPANT", "ROLE_ARTISTE", "ROLE_ADMIN"]
LAG = 6
MIN_SAMPLES = 2
RIDGE_ALPHA = 1.0
RANDOM_STATE = 42


def load_db_url_from_env(env_path=None):
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
    url = load_db_url_from_env()
    if not url:
        return None, "DATABASE_URL not found in .env"

    parsed = parse_db_url(url)
    if not parsed:
        return None, "Invalid DATABASE_URL"

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
        "-N", "-B",
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
    monthly = [{"month": row["month"], "count": row["count"]} for row in monthly]

    return {"monthly": monthly, "monthly_by_role": monthly_by_role}, None


def _monthly_by_role_from_monthly(monthly):
    return [{"month": row["month"], **{r: 0 for r in ROLES}} for row in monthly]


def add_months(dt, months):
    y, m = dt.year, dt.month
    m += months
    while m > 12:
        m -= 12
        y += 1
    while m < 1:
        m += 12
        y -= 1
    return datetime(y, m, 1)


def _build_lag_matrix(values, lag=LAG):
    arr = np.asarray(values, dtype=float).ravel()
    n = len(arr)
    if n < 2:
        return None, None
    lag_used = min(lag, n - 1)
    X_list = []
    y_list = []
    for i in range(lag_used, n):
        row = list(arr[i - lag_used : i])
        while len(row) < lag:
            row.insert(0, np.mean(arr[:i]) if i else 0.0)
        row = row[-lag:]
        row.append(float(i))
        X_list.append(row)
        y_list.append(arr[i])
    if not X_list:
        return None, None
    return np.array(X_list), np.array(y_list)


def _fit_predict_ridge(values, steps=1):
    arr = np.asarray([max(0.0, float(v)) for v in values], dtype=float).ravel()
    n = len(arr)
    if n == 0:
        return [0.0] * max(1, steps), 0.0, None
    if n == 1 or (n < LAG and steps >= 1):
        last = float(arr[-1])
        return [last] * max(1, steps), float(np.std(arr)) if n > 1 else 0.0, None

    X, y = _build_lag_matrix(arr.tolist(), LAG)
    if X is None or len(X) < MIN_SAMPLES:
        last = float(arr[-1])
        return [last] * max(1, steps), 0.0, None

    model = Ridge(alpha=RIDGE_ALPHA, random_state=RANDOM_STATE)
    model.fit(X, y)
    residual_std = float(np.sqrt(np.mean((y - model.predict(X)) ** 2)))

    current = list(arr)
    predictions = []
    for _ in range(steps):
        row = list(current[-LAG:])
        while len(row) < LAG:
            row.insert(0, np.mean(current) if current else 0.0)
        row = row[-LAG:]
        row.append(float(len(current)))
        X_next = np.array([row])
        next_val = float(model.predict(X_next)[0])
        next_val = max(0.0, next_val)
        predictions.append(next_val)
        current.append(next_val)

    return predictions, residual_std, model


def _seasonality_factor(values):
    n = len(values)
    if n < 8:
        return 0.0
    recent = values[-3:]
    older = values[-6:-3]
    if len(older) < 3:
        return 0.0
    recent_avg = np.mean(recent)
    older_avg = np.mean(older)
    if older_avg <= 1e-9:
        return 0.0
    raw = (recent_avg - older_avg) / older_avg
    return float(max(-0.15, min(0.15, raw * 0.35)))


def _confidence_from_residuals(residual_std, n, prediction):
    if n < 2:
        return 35
    sample_score = min(45.0, (n / 12.0) * 45.0)
    mean_val = prediction
    if residual_std <= 1e-9 or mean_val <= 1e-9:
        return min(100, int(50 + sample_score))
    coef_var = residual_std / max(mean_val, 1.0)
    volatility_score = (1.0 - min(1.0, coef_var)) * 55.0
    score = int(round(volatility_score + sample_score))
    return max(0, min(100, score))


def _confidence_band(prediction, residual_std, confidence_score_val):
    uncertainty = 1.35 - (confidence_score_val / 100.0) * 0.8
    margin = max(1.0, residual_std * uncertainty)
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

    preds, residual_std, _ = _fit_predict_ridge(counts, steps=1)
    prediction = preds[0] if preds else 0.0
    prediction = max(0.0, prediction)
    last = counts[-1]
    delta = max(1.0, last * 0.07)
    trend = "stable"
    if prediction > last + delta:
        trend = "up"
    elif prediction < last - delta:
        trend = "down"

    seas = _seasonality_factor(counts)
    conf_score = _confidence_from_residuals(residual_std, n, prediction)
    lower, upper = _confidence_band(prediction, residual_std, conf_score)

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
    preds, _, _ = _fit_predict_ridge(values, steps=max(1, steps))
    return preds[: max(1, steps)]


def months_remaining_in_year():
    """Number of months left in the current year (from next month through December)."""
    return max(0, 12 - datetime.now().month)


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
    monthly = data.get("monthly") or []
    monthly_by_role = data.get("monthly_by_role") or []

    counts = [row.get("count", 0) for row in monthly]
    next_month = predict_next_month(counts)

    role_series = {r: [] for r in ROLES}
    for row in monthly_by_role:
        for r in ROLES:
            role_series[r].append(float(row.get(r, 0) or 0))

    future_by_type = []
    n_future = months_remaining_in_year()
    labels = future_month_labels(n_future)
    for step, label in enumerate(labels):
        row = {"month": label}
        for r in ROLES:
            series = role_series.get(r, [])
            preds = predict_series(series, step + 1)
            row[r] = int(round(preds[-1])) if preds else 0
        future_by_type.append(row)

    return {"next_month": next_month, "future_by_type": future_by_type}


def parse_args():
    p = argparse.ArgumentParser(description="Registration prediction. JSON from stdin, file, or --fetch-db.")
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
