#!/usr/bin/env bash

set -Eeuo pipefail

BASE_URL="${1:-}"
EXPECTED_PRODUCTS_MIN="${EXPECTED_PRODUCTS_MIN:-1}"
EXPECTED_DB_DRIVER="${EXPECTED_DB_DRIVER:-pgsql}"

[[ "$BASE_URL" =~ ^https?:// ]] || {
    echo "Usage: ./scripts/laravel-cloud-smoke-test.sh https://your-app.laravel.cloud" >&2
    exit 1
}

BASE_URL="${BASE_URL%/}"

echo "==> Checking Laravel health"
curl --fail --silent --show-error "$BASE_URL/up" >/dev/null

echo "==> Checking KonnectFind readiness"
health="$(curl --fail --silent --show-error "$BASE_URL/api/health")"
php -r '
$health = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$minimum = (int) $argv[2];
if (($health["status"] ?? null) !== "healthy") {
    fwrite(STDERR, "API did not report healthy status.\n");
    exit(1);
}
if (($health["database"]["driver"] ?? null) !== $argv[3]) {
    fwrite(STDERR, "Expected {$argv[3]}, received ".($health["database"]["driver"] ?? "unknown").".\n");
    exit(1);
}
if (($health["marketplace"]["active_products"] ?? 0) < $minimum) {
    fwrite(STDERR, "Expected at least $minimum active products.\n");
    exit(1);
}
' "$health" "$EXPECTED_PRODUCTS_MIN" "$EXPECTED_DB_DRIVER"

echo "==> Checking marketplace API"
curl --fail --silent --show-error "$BASE_URL/api/vendors" >/dev/null

echo "==> Checking React storefront"
html="$(curl --fail --silent --show-error "$BASE_URL/")"
[[ "$html" == *'<div id="root"></div>'* ]] || {
    echo "React application root was not found in the storefront response." >&2
    exit 1
}

echo "Laravel Cloud smoke test passed for $BASE_URL"
