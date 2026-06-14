#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
FRONTEND_DIR="$ROOT_DIR/frontend"
k=5
file=""
reindex=false
verify=false

usage() {
    cat <<'EOF'
Usage: ./scripts/evaluate.sh [options]

Run offline product-search retrieval evaluation.

Options:
  --k NUMBER       Evaluate the first NUMBER ranked results (default: 5)
  --file PATH      Use a custom JSON relevance set
  --reindex        Re-index every product before evaluation
  --verify         Also run backend tests, formatting, frontend build, and lint
  -h, --help       Show this help
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --k)
            [[ $# -ge 2 ]] || { echo "--k requires a number" >&2; exit 1; }
            k="$2"
            shift 2
            ;;
        --file)
            [[ $# -ge 2 ]] || { echo "--file requires a path" >&2; exit 1; }
            file="$2"
            shift 2
            ;;
        --reindex) reindex=true; shift ;;
        --verify) verify=true; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 1 ;;
    esac
done

[[ "$k" =~ ^[1-9][0-9]*$ ]] || { echo "--k must be a positive integer" >&2; exit 1; }
[[ -f "$BACKEND_DIR/.env" && -d "$BACKEND_DIR/vendor" ]] || {
    echo "Backend setup is incomplete. Run ./scripts/setup.sh first." >&2
    exit 1
}

echo "==> Ensuring the product catalogue is indexed"
if [[ "$reindex" == true ]]; then
    (cd "$BACKEND_DIR" && php artisan search:index --force)
else
    (cd "$BACKEND_DIR" && php artisan search:index)
fi

evaluation=(php artisan search:evaluate "--k=$k")
if [[ -n "$file" ]]; then
    [[ -f "$file" ]] || { echo "Evaluation file not found: $file" >&2; exit 1; }
    evaluation+=("--file=$(cd "$(dirname "$file")" && pwd)/$(basename "$file")")
fi

echo "==> Running retrieval evaluation at K=$k"
(cd "$BACKEND_DIR" && "${evaluation[@]}")

if [[ "$verify" == true ]]; then
    echo "==> Running complete project verification"
    (cd "$BACKEND_DIR" && php artisan test && ./vendor/bin/pint --test)
    (cd "$FRONTEND_DIR" && npm run build && npm run lint)
fi
