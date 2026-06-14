#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
INPUT_PATH="${INPUT_PATH:-$BACKEND_DIR/database/data/seeder.json}"
fresh_flag=()

if [[ "${1:-}" == "--fresh" ]]; then
    fresh_flag=(--fresh)
elif [[ $# -gt 0 ]]; then
    echo "Usage: ./scripts/import-marketplace-data.sh [--fresh]" >&2
    exit 1
fi

[[ -f "$INPUT_PATH" ]] || {
    echo "Seeder file not found: $INPUT_PATH" >&2
    echo "Run ./scripts/generate-marketplace-data.sh first." >&2
    exit 1
}

(cd "$BACKEND_DIR" && php artisan marketplace:import-seeder --path="$INPUT_PATH" "${fresh_flag[@]}")
