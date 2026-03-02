#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VENV_DIR="$ROOT_DIR/python/.venv"
REQ_FILE="$ROOT_DIR/python/requirements.txt"
MODEL_FILE="$ROOT_DIR/models/donation.pt"
SETTINGS_DIR="$ROOT_DIR/var/ultralytics"

mkdir -p "$SETTINGS_DIR"

if [ ! -d "$VENV_DIR" ]; then
  python3 -m venv "$VENV_DIR"
fi

"$VENV_DIR/bin/pip" install --upgrade pip
"$VENV_DIR/bin/pip" install -r "$REQ_FILE"

if [ ! -f "$MODEL_FILE" ]; then
  echo "ERROR: model not found at $MODEL_FILE"
  echo "Train a model with: $ROOT_DIR/python/train_donation_model.py"
  exit 1
fi

ENV_LOCAL="$ROOT_DIR/.env.local"
if [ ! -f "$ENV_LOCAL" ]; then
  touch "$ENV_LOCAL"
fi

set_kv() {
  local key="$1"
  local value="$2"
  if grep -q "^${key}=" "$ENV_LOCAL"; then
    sed -i "s#^${key}=.*#${key}=${value}#" "$ENV_LOCAL"
  else
    echo "${key}=${value}" >> "$ENV_LOCAL"
  fi
}

set_kv "DONATION_AI_PYTHON_BIN" "$VENV_DIR/bin/python"
set_kv "DONATION_AI_MODEL" "$MODEL_FILE"
set_kv "DONATION_AI_CONFIDENCE" "0.6"
set_kv "DONATION_AI_MARGIN" "0.15"
set_kv "DONATION_AI_ALLOW_SKIP" "0"
set_kv "DONATION_AI_SERVICE_URL" "http://127.0.0.1:8001"
set_kv "DONATION_AI_MAX_IMAGE" "1024"
set_kv "YOLO_IMG_SIZE" "224"
set_kv "YOLO_MAX_DET" "10"

echo "AI validator setup complete."
