#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"

fresh=false
skip_install=false
skip_index=false
skip_build=false
large_catalogue=false

usage() {
    cat <<'EOF'
Usage: ./scripts/setup.sh [options]

Prepare the project for local development using SQLite and local embeddings.

Options:
  --fresh          Recreate the database before seeding it
  --skip-install   Do not run Composer or npm install
  --skip-index     Do not generate product embeddings
  --skip-build     Do not create a frontend production build
  --large-catalogue Import database/data/seeder.json after migrations
  -h, --help       Show this help
EOF
}

for argument in "$@"; do
    case "$argument" in
        --fresh) fresh=true ;;
        --skip-install) skip_install=true ;;
        --skip-index) skip_index=true ;;
        --skip-build) skip_build=true ;;
        --large-catalogue) large_catalogue=true ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $argument" >&2; usage >&2; exit 1 ;;
    esac
done

for command in php composer node npm; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Missing required command: $command" >&2
        exit 1
    fi
done

echo "==> Preparing Laravel backend"
if [[ "$skip_install" == false ]]; then
    (cd "$BACKEND_DIR" && composer install --no-interaction)
fi

if [[ ! -f "$BACKEND_DIR/.env" ]]; then
    cp "$BACKEND_DIR/.env.example" "$BACKEND_DIR/.env"
fi

touch "$BACKEND_DIR/database/database.sqlite"

if ! grep -q '^APP_KEY=base64:' "$BACKEND_DIR/.env"; then
    (cd "$BACKEND_DIR" && php artisan key:generate --force)
fi

if [[ "$fresh" == true ]]; then
    (cd "$BACKEND_DIR" && php artisan migrate:fresh --seed)
else
    (cd "$BACKEND_DIR" && php artisan migrate --force && php artisan db:seed --force)
fi

if [[ "$large_catalogue" == true ]]; then
    "$ROOT_DIR/scripts/import-marketplace-data.sh" --fresh
elif [[ "$skip_index" == false ]]; then
    (cd "$BACKEND_DIR" && php artisan search:index)
fi

echo "==> Preparing React frontend"
if [[ "$skip_install" == false ]]; then
    (cd "$FRONTEND_DIR" && npm install)
fi

if [[ "$skip_build" == false ]]; then
    (cd "$FRONTEND_DIR" && npm run build)
fi

cat <<'EOF'

Setup complete.

Start the application:
  ./scripts/run-dev.sh

Run retrieval evaluation:
  ./scripts/evaluate.sh
EOF
