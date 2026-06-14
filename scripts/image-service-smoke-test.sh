#!/usr/bin/env bash

set -Eeuo pipefail

BASE_URL="${1:-}"
TOKEN="${IMAGE_SERVICE_TOKEN:-}"

[[ "$BASE_URL" =~ ^https?:// ]] || {
    echo "Usage: IMAGE_SERVICE_TOKEN=... ./scripts/image-service-smoke-test.sh https://service.example.com" >&2
    exit 1
}
[[ -n "$TOKEN" ]] || {
    echo "IMAGE_SERVICE_TOKEN is required." >&2
    exit 1
}

BASE_URL="${BASE_URL%/}"

echo "==> Checking image-service health"
health="$(curl --fail --silent --show-error "$BASE_URL/health")"
php -r '
$health = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
if (($health["status"] ?? null) !== "ok") {
    fwrite(STDERR, "Image service did not report healthy status.\n");
    exit(1);
}
echo "Model: {$health["model"]}\n";
' "$health"

echo "==> Confirming embedding endpoint is protected"
status="$(curl --silent --output /dev/null --write-out '%{http_code}' \
    --request POST "$BASE_URL/embed-url" \
    --header 'Content-Type: application/json' \
    --data '{"url":"https://images.unsplash.com/photo-1553062407-98eeb64c6a62"}')"
[[ "$status" == "401" ]] || {
    echo "Expected unauthenticated request to return 401; received $status." >&2
    exit 1
}

echo "==> Generating an authenticated image embedding"
result="$(curl --fail --silent --show-error \
    --request POST "$BASE_URL/embed-url" \
    --header "Authorization: Bearer $TOKEN" \
    --header 'Content-Type: application/json' \
    --data '{"url":"https://images.unsplash.com/photo-1553062407-98eeb64c6a62"}')"
php -r '
$result = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
if (($result["dimension"] ?? 0) < 1 || count($result["embedding"] ?? []) !== $result["dimension"]) {
    fwrite(STDERR, "Image embedding response is invalid.\n");
    exit(1);
}
echo "Embedding dimension: {$result["dimension"]}\n";
' "$result"

echo "Image-service smoke test passed for $BASE_URL"
