#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"
API_HOST="${API_HOST:-127.0.0.1}"
API_PORT="${API_PORT:-8000}"
FRONTEND_HOST="${FRONTEND_HOST:-127.0.0.1}"
FRONTEND_PORT="${FRONTEND_PORT:-5173}"

for command in php npm; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Missing required command: $command" >&2
        exit 1
    fi
done

if [[ ! -f "$BACKEND_DIR/.env" || ! -d "$BACKEND_DIR/vendor" || ! -d "$FRONTEND_DIR/node_modules" ]]; then
    echo "Project setup is incomplete. Run ./scripts/setup.sh first." >&2
    exit 1
fi

cleanup() {
    trap - INT TERM EXIT
    [[ -n "${backend_pid:-}" ]] && kill "$backend_pid" 2>/dev/null || true
    [[ -n "${frontend_pid:-}" ]] && kill "$frontend_pid" 2>/dev/null || true
    wait 2>/dev/null || true
}
trap cleanup INT TERM EXIT

echo "Starting API at http://$API_HOST:$API_PORT"
(cd "$BACKEND_DIR" && php artisan serve --host="$API_HOST" --port="$API_PORT") &
backend_pid=$!

echo "Starting frontend at http://$FRONTEND_HOST:$FRONTEND_PORT"
(cd "$FRONTEND_DIR" && VITE_API_PROXY_TARGET="http://$API_HOST:$API_PORT" npm run dev -- --host "$FRONTEND_HOST" --port "$FRONTEND_PORT") &
frontend_pid=$!

echo "Press Ctrl+C to stop both servers."
wait "$backend_pid" "$frontend_pid"
