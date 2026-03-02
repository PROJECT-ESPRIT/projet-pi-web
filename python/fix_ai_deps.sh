#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PYTHON_BIN="${PYTHON_BIN:-python3}"

echo "Using python: ${PYTHON_BIN}"
"${PYTHON_BIN}" -m pip install --upgrade pip
"${PYTHON_BIN}" -m pip uninstall -y torch torchvision || true
"${PYTHON_BIN}" -m pip install --index-url https://download.pytorch.org/whl/cpu torch torchvision
"${PYTHON_BIN}" -m pip install -r "${ROOT_DIR}/python/requirements.txt"
