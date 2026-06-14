#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="/tmp/konnectfind-cloud-build"

cd "$ROOT_DIR"

[[ -f backend/artisan && -f frontend/package-lock.json ]] || {
    echo "Expected backend/ and frontend/ monorepo directories were not found." >&2
    exit 1
}

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
mv backend frontend "$BUILD_DIR/"

cp -R "$BUILD_DIR/backend/." .

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

(cd "$BUILD_DIR/frontend" && npm ci && npm run build)
cp -R "$BUILD_DIR/frontend/dist/." public/

# This development artefact is intentionally excluded from the runtime image.
rm -f database/data/seeder.json

php artisan optimize

rm -rf "$BUILD_DIR"
