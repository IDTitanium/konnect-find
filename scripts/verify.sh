#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"
REPORT_DIR="$ROOT_DIR/reports"

mkdir -p "$REPORT_DIR"

echo "==> Evaluating retrieval quality"
(cd "$BACKEND_DIR" && php artisan search:index && php artisan search:evaluate --k=5 --json="$REPORT_DIR/search-evaluation.json")

echo "==> Verifying backend behavior and formatting"
(cd "$BACKEND_DIR" && php artisan test && ./vendor/bin/pint --test)

echo "==> Verifying frontend production build and lint"
(cd "$FRONTEND_DIR" && npm run build && npm run lint)

echo "==> Recording system readiness"
(cd "$BACKEND_DIR" && php artisan migrate:status > "$REPORT_DIR/migration-status.txt")
(cd "$BACKEND_DIR" && php artisan route:list --path=api > "$REPORT_DIR/api-routes.txt")

echo
echo "Verification complete. Evidence written to:"
echo "  $REPORT_DIR/search-evaluation.json"
echo "  $REPORT_DIR/migration-status.txt"
echo "  $REPORT_DIR/api-routes.txt"
