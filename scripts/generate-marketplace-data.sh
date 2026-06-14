#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND_DIR="$ROOT_DIR/backend"
VENDORS="${VENDORS:-500}"
PRODUCTS_PER_VENDOR="${PRODUCTS_PER_VENDOR:-500}"
OUTPUT_PATH="${OUTPUT_PATH:-$BACKEND_DIR/database/data/seeder.json}"

echo "Generating $VENDORS vendors and $((VENDORS * PRODUCTS_PER_VENDOR)) products..."
(cd "$BACKEND_DIR" && php artisan marketplace:generate-seeder \
    --vendors="$VENDORS" \
    --products-per-vendor="$PRODUCTS_PER_VENDOR" \
    --path="$OUTPUT_PATH")

echo "Validating generated JSON..."
php -r '
$path = $argv[1];
$handle = fopen($path, "rb");
$section = null;
$vendors = $products = 0;
$vendorSlugs = $productsByVendor = $categories = [];
while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if ($line === "\"vendors\":[") { $section = "vendors"; continue; }
    if ($line === "\"products\":[") { $section = "products"; continue; }
    if ($section === null || !str_starts_with($line, "{")) { continue; }
    $record = json_decode(rtrim($line, ","), true, 512, JSON_THROW_ON_ERROR);
    if ($section === "vendors") {
        $vendors++;
        $vendorSlugs[$record["slug"]] = true;
    }
    if ($section === "products") {
        $products++;
        $productsByVendor[$record["vendor_slug"]] = ($productsByVendor[$record["vendor_slug"]] ?? 0) + 1;
        $categories[$record["category"]] = true;
        if (!filter_var($record["image_url"], FILTER_VALIDATE_URL)) {
            fwrite(STDERR, "Invalid product image URL: {$record["image_url"]}\n");
            exit(1);
        }
    }
}
fclose($handle);
$expectedVendors = (int) $argv[2];
$expectedProducts = (int) $argv[3];
$expectedProductsPerVendor = (int) $argv[4];
if ($vendors !== $expectedVendors || $products !== $expectedProducts) {
    fwrite(STDERR, "Count validation failed: $vendors vendors, $products products\n");
    exit(1);
}
foreach ($vendorSlugs as $slug => $_) {
    if (($productsByVendor[$slug] ?? 0) !== $expectedProductsPerVendor) {
        fwrite(STDERR, "Distribution validation failed for $slug: ".($productsByVendor[$slug] ?? 0)." products\n");
        exit(1);
    }
}
if (count($productsByVendor) !== $vendors || count($categories) < 20) {
    fwrite(STDERR, "Diversity validation failed: ".count($productsByVendor)." represented vendors and ".count($categories)." categories\n");
    exit(1);
}
echo "Validated $vendors vendors, $products products, $expectedProductsPerVendor products per vendor, and ".count($categories)." categories.\n";
' "$OUTPUT_PATH" "$VENDORS" "$((VENDORS * PRODUCTS_PER_VENDOR))" "$PRODUCTS_PER_VENDOR"
