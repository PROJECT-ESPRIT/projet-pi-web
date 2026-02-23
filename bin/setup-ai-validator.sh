#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PYTHON_BIN="${1:-python3}"
VENV_DIR="python/.venv"
PROJECT_PYTHON="python/.venv/bin/python"
PROJECT_PIP="python/.venv/bin/pip"
ENV_LOCAL_FILE=".env.local"
ENV_LINE="DONATION_AI_PYTHON_BIN=python/.venv/bin/python"
ULTRALYTICS_SETTINGS_DIR_PATH="var/ultralytics"

if ! command -v "$PYTHON_BIN" >/dev/null 2>&1; then
  echo "Python introuvable: $PYTHON_BIN"
  echo "Usage: bin/setup-ai-validator.sh [python_binary]"
  exit 1
fi

echo "==> Creation du virtualenv ($VENV_DIR)"
"$PYTHON_BIN" -m venv "$VENV_DIR"

echo "==> Upgrade pip"
"$PROJECT_PIP" install --upgrade pip

echo "==> Installation torch CPU-only"
"$PROJECT_PIP" install --index-url https://download.pytorch.org/whl/cpu torch torchvision

echo "==> Installation dependencies IA"
"$PROJECT_PIP" install -r python/requirements.txt

if [[ -f "$ENV_LOCAL_FILE" ]]; then
  if grep -q '^DONATION_AI_PYTHON_BIN=' "$ENV_LOCAL_FILE"; then
    sed -i "s|^DONATION_AI_PYTHON_BIN=.*|$ENV_LINE|" "$ENV_LOCAL_FILE"
  else
    printf "\n%s\n" "$ENV_LINE" >> "$ENV_LOCAL_FILE"
  fi
else
  printf "%s\n" "$ENV_LINE" > "$ENV_LOCAL_FILE"
fi

mkdir -p "$ULTRALYTICS_SETTINGS_DIR_PATH"

echo "==> Verification ultralytics"
ULTRALYTICS_SETTINGS_DIR="$ULTRALYTICS_SETTINGS_DIR_PATH" "$PROJECT_PYTHON" -c "from ultralytics import YOLO; print('ultralytics-ok')"

echo "==> Prechargement du modele YOLO"
ULTRALYTICS_SETTINGS_DIR="$ULTRALYTICS_SETTINGS_DIR_PATH" "$PROJECT_PYTHON" -c "from ultralytics import YOLO; YOLO('yolov8n.pt'); print('model-ok')"

if command -v php >/dev/null 2>&1; then
  echo "==> Symfony cache clear"
  php bin/console cache:clear >/dev/null
fi

echo "Setup termine."
echo "Interpreteur AI: $PROJECT_PYTHON"
