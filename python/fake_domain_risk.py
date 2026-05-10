#!/usr/bin/env python3
"""
Fake domain risk — simple ML (logistic regression) on domain features.
Input: --domain example.com
Output: one line to stdout: "94 high" (percent 0-100, then label: low|medium|high).
No JSON. Used by admin user management to help approval decisions.
"""
import argparse
import re
import sys

# Known disposable / temporary email domains (small list for training + lookup)
DISPOSABLE_DOMAINS = frozenset([
    "mailinator.com", "tempmail.com", "10minutemail.com", "guerrillamail.com",
    "throwaway.email", "temp-mail.org", "fakeinbox.com", "trashmail.com",
    "yopmail.com", "getnada.com", "maildrop.cc", "sharklasers.com",
    "guerrillamail.info", "grr.la", "spam4.me", "dispostable.com",
    "tempail.com", "mohmal.com", "emailondeck.com", "mailnesia.com",
])

# Common real domains (for training)
REAL_DOMAINS = frozenset([
    "gmail.com", "outlook.com", "yahoo.com", "hotmail.com", "icloud.com",
    "live.com", "mail.tn", "orange.tn", "yahoo.fr", "free.fr", "laposte.net",
    "sfr.fr", "wanadoo.fr", "protonmail.com", "zoho.com", "art.com",
])

COMMON_TLDS = frozenset(["com", "fr", "tn", "net", "org", "co", "io", "eu"])


def extract_domain(email_or_domain: str) -> str:
    s = (email_or_domain or "").strip().lower()
    if "@" in s:
        s = s.split("@", 1)[1]
    return s.split("/")[0].split("?")[0] or ""


def domain_features(domain: str) -> list:
    if not domain:
        return [0, 0, 0, 0, 0]
    domain = domain.lower()
    parts = domain.rsplit(".", 1)
    tld = parts[-1] if len(parts) == 2 else ""
    return [
        1 if domain in DISPOSABLE_DOMAINS else 0,
        min(len(domain), 50) / 50.0,
        min(sum(c.isdigit() for c in domain), 20) / 20.0,
        1 if "-" in domain else 0,
        1 if tld in COMMON_TLDS else 0,
    ]


def build_training_data():
    X, y = [], []
    for d in DISPOSABLE_DOMAINS:
        X.append(domain_features(d))
        y.append(1)
    for d in REAL_DOMAINS:
        X.append(domain_features(d))
        y.append(0)
    return X, y


def label_from_percent(p: int) -> str:
    if p >= 70:
        return "high"
    if p >= 30:
        return "medium"
    return "low"


def main():
    parser = argparse.ArgumentParser(description="Fake domain risk (ML). Output: percent label")
    parser.add_argument("--domain", type=str, required=True, help="Email or domain to score")
    args = parser.parse_args()

    domain = extract_domain(args.domain)
    if not domain:
        print("0 low")
        return 0

    try:
        import numpy as np
        from sklearn.linear_model import LogisticRegression
    except ImportError:
        # Fallback: list lookup only
        p = 95 if domain in DISPOSABLE_DOMAINS else (10 if domain in REAL_DOMAINS else 40)
        print(f"{p} {label_from_percent(p)}")
        return 0

    X_train, y_train = build_training_data()
    X_train = np.array(X_train)
    y_train = np.array(y_train)

    model = LogisticRegression(random_state=42, max_iter=500)
    model.fit(X_train, y_train)

    feat = domain_features(domain)
    prob = float(model.predict_proba([feat])[0][1])
    percent = max(0, min(100, int(round(prob * 100))))
    # If domain is in disposable list, force high score
    if domain in DISPOSABLE_DOMAINS:
        percent = max(percent, 90)
    elif domain in REAL_DOMAINS:
        percent = min(percent, 15)

    lbl = label_from_percent(percent)
    print(f"{percent} {lbl}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
