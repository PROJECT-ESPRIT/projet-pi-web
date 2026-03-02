#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Load app env to keep the service aligned with Symfony settings.
set -a
if [ -f "${ROOT_DIR}/.env" ]; then
  # shellcheck disable=SC1090
  source "${ROOT_DIR}/.env"
fi
if [ -f "${ROOT_DIR}/.env.local" ]; then
  # shellcheck disable=SC1090
  source "${ROOT_DIR}/.env.local"
fi
set +a

PYTHON_BIN="${PYTHON_BIN:-${DONATION_AI_PYTHON_BIN:-python3}}"

mkdir -p "${ROOT_DIR}/var/tmp"
export TMPDIR="${ROOT_DIR}/var/tmp"
export DONATION_AI_MODEL="${DONATION_AI_MODEL:-${ROOT_DIR}/models/donation.pt}"
export DONATION_AI_LABEL_GROUPS="${DONATION_AI_LABEL_GROUPS:-${ROOT_DIR}/config/ai_label_groups.json}"
export ULTRALYTICS_SETTINGS_DIR="${ULTRALYTICS_SETTINGS_DIR:-${ROOT_DIR}/var/ultralytics}"
export DONATION_AI_ALLOW_SKIP="${DONATION_AI_ALLOW_SKIP:-0}"
export DONATION_AI_CONFIDENCE="${DONATION_AI_CONFIDENCE:-0.25}"
export DONATION_AI_MARGIN="${DONATION_AI_MARGIN:-0.0}"
export DONATION_AI_MAX_IMAGE="${DONATION_AI_MAX_IMAGE:-1024}"
export DONATION_AI_TOPK="${DONATION_AI_TOPK:-5}"
export YOLO_IMG_SIZE="${YOLO_IMG_SIZE:-224}"
export YOLO_MAX_DET="${YOLO_MAX_DET:-1}"
export YOLO_DEVICE="${YOLO_DEVICE:-cpu}"
export YOLO_HALF="${YOLO_HALF:-0}"

if ! "${PYTHON_BIN}" - <<'PY'
import sys
try:
    import torch  # noqa: F401
    import torchvision  # noqa: F401
except Exception as exc:
    print("ERROR: torch/torchvision import failed:", exc)
    print("Run: bash python/fix_ai_deps.sh")
    sys.exit(1)
PY
then
  exit 1
fi

exec "${PYTHON_BIN}" -m uvicorn ai_service:app \
  --app-dir "${ROOT_DIR}/python" \
  --host 127.0.0.1 \
  --port 8001
