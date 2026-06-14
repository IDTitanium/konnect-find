<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class MarketplaceSeederImporter
{
    public function import(string $path, bool $fresh = false, int $batchSize = 1000, ?callable $progress = null): array
    {
        throw_unless(is_file($path), RuntimeException::class, "Seeder file not found: $path");
        $handle = fopen($path, 'rb');
        throw_unless($handle, RuntimeException::class, "Unable to open seeder file: $path");

        if ($fresh) {
            Product::query()->delete();
            Vendor::query()->delete();
        }

        $section = null;
        $vendors = $products = [];
        $vendorCount = $productCount = 0;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '"vendors":[') {
                $section = 'vendors';

                continue;
            }
            if ($trimmed === '"products":[') {
                $section = 'products';

                continue;
            }
            if ($section === null || $trimmed === '],' || $trimmed === ']' || $trimmed === '}' || ! str_starts_with($trimmed, '{')) {
                continue;
            }

            try {
                $record = json_decode(rtrim($trimmed, ','), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Invalid JSON record near line: '.substr($trimmed, 0, 120), previous: $exception);
            }

            if ($section === 'vendors') {
                $vendors[] = [...$record, 'created_at' => now(), 'updated_at' => now()];
                if (count($vendors) >= $batchSize) {
                    $vendorCount += $this->upsertVendors($vendors);
                    $vendors = [];
                    if ($progress) {
                        $progress('vendors', $vendorCount);
                    }
                }
            } elseif ($section === 'products') {
                $products[] = $record;
                if (count($products) >= $batchSize) {
                    $productCount += $this->upsertProducts($products);
                    $products = [];
                    if ($progress) {
                        $progress('products', $productCount);
                    }
                }
            }
        }
        fclose($handle);

        if ($vendors) {
            $vendorCount += $this->upsertVendors($vendors);
        }
        if ($products) {
            $productCount += $this->upsertProducts($products);
        }

        return ['vendors' => $vendorCount, 'products' => $productCount];
    }

    private function upsertVendors(array $vendors): int
    {
        DB::table('vendors')->upsert($vendors, ['slug'], [
            'name', 'description', 'logo_url', 'banner_url', 'location', 'is_verified',
            'rating', 'fulfillment_days', 'is_active', 'updated_at',
        ]);

        return count($vendors);
    }

    private function upsertProducts(array $products): int
    {
        $slugs = array_values(array_unique(array_column($products, 'vendor_slug')));
        $vendorIds = Vendor::query()->whereIn('slug', $slugs)->pluck('id', 'slug');
        $timestamp = now();
        $rows = array_map(function (array $product) use ($vendorIds, $timestamp): array {
            $slug = $product['vendor_slug'];
            unset($product['vendor_slug']);

            return [
                ...$product,
                'vendor_id' => $vendorIds[$slug] ?? throw new RuntimeException("Vendor not found for slug: $slug"),
                'search_terms' => json_encode($product['search_terms'], JSON_UNESCAPED_SLASHES),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $products);

        DB::table('products')->upsert($rows, ['seller_sku'], [
            'vendor_id', 'name', 'category', 'description', 'image_url', 'price_kobo',
            'search_terms', 'inventory_count', 'is_active', 'updated_at',
        ]);

        return count($rows);
    }
}
