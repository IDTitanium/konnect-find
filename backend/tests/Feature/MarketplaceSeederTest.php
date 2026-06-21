<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_json_can_be_generated_and_stream_imported(): void
    {
        $path = storage_path('framework/testing-marketplace-seeder.json');

        $this->artisan('marketplace:generate-seeder', [
            '--vendors' => 2,
            '--products-per-vendor' => 3,
            '--path' => $path,
        ])->assertSuccessful();

        $decoded = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(2, $decoded['vendors']);
        $this->assertCount(6, $decoded['products']);
        $this->assertStringContainsString('ixlib=rb-4.0.3', $decoded['products'][0]['image_url']);
        $this->assertStringNotContainsString('1583391733956-6c78276477e2', json_encode($decoded['products'], JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('1581147036324-c1c89c2c8b5c', json_encode($decoded['products'], JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Edition', $decoded['products'][0]['name']);

        $this->artisan('marketplace:import-seeder', ['--path' => $path, '--fresh' => true])
            ->assertSuccessful();

        $this->assertSame(2, Vendor::count());
        $this->assertSame(6, Product::count());
        $this->assertSame(3, Product::where('vendor_id', Vendor::first()->id)->count());
        $this->assertGreaterThan(1, Product::distinct()->count('category'));

        unlink($path);
    }

    public function test_vendor_buffer_is_flushed_before_large_product_batches(): void
    {
        $path = storage_path('framework/testing-marketplace-batched-seeder.json');

        $this->artisan('marketplace:generate-seeder', [
            '--vendors' => 2,
            '--products-per-vendor' => 60,
            '--path' => $path,
        ])->assertSuccessful();

        $this->artisan('marketplace:import-seeder', [
            '--path' => $path,
            '--fresh' => true,
            '--batch' => 100,
        ])->assertSuccessful();

        $this->assertSame(2, Vendor::count());
        $this->assertSame(120, Product::count());
        $this->assertSame(120, Product::distinct()->count('name'));

        unlink($path);
    }

    public function test_large_marketplace_bootstrap_imports_and_removes_its_temporary_file(): void
    {
        $temporaryFilesBefore = glob(storage_path('app/marketplace-seeder-*.json'));

        $this->artisan('marketplace:bootstrap-large', [
            '--vendors' => 2,
            '--products-per-vendor' => 3,
            '--replace' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Vendor::count());
        $this->assertSame(6, Product::count());
        $this->assertSame($temporaryFilesBefore, glob(storage_path('app/marketplace-seeder-*.json')));
    }

    public function test_marketplace_image_repair_normalizes_urls_and_clears_product_image_embeddings(): void
    {
        $vendor = Vendor::create([
            'name' => 'Broken Images Store',
            'slug' => 'broken-images-store',
            'description' => 'A test vendor.',
            'logo_url' => 'BI',
            'banner_url' => 'https://images.unsplash.com/photo-1555529669-e69e7aa0ba9a?auto=format&fit=crop&w=1200&q=80',
            'location' => 'Lagos',
            'is_verified' => true,
            'rating' => 4.8,
            'fulfillment_days' => 2,
            'is_active' => true,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Broken Tool Image',
            'category' => 'Tools',
            'description' => 'Test product.',
            'image_url' => 'https://images.unsplash.com/photo-1581147036324-c1c89c2c8b5c?auto=format&fit=crop&w=900&q=80',
            'price_kobo' => 100000,
            'search_terms' => ['tools'],
            'seller_sku' => 'BROK-0001',
            'inventory_count' => 5,
            'is_active' => true,
            'text_embedding' => [1, 0],
            'image_embedding' => [0, 1],
            'image_embedding_model' => 'old-image-model',
            'embeddings_indexed_at' => now(),
        ]);

        $this->artisan('marketplace:repair-image-urls')->assertSuccessful();

        $this->assertStringContainsString('ixlib=rb-4.0.3', $vendor->fresh()->banner_url);
        $product = $product->fresh();
        $this->assertStringContainsString('photo-1581783898377-1c85bf937427', $product->image_url);
        $this->assertStringContainsString('ixlib=rb-4.0.3', $product->image_url);
        $this->assertNull($product->image_embedding);
        $this->assertNull($product->image_embedding_model);
        $this->assertNull($product->embeddings_indexed_at);
        $this->assertSame([1, 0], $product->text_embedding);
    }

    public function test_marketplace_product_name_repair_replaces_generic_names_and_clears_text_embeddings(): void
    {
        $vendor = Vendor::create([
            'name' => 'Generated Vendor',
            'slug' => 'generated-vendor',
            'description' => 'A generated vendor.',
            'logo_url' => 'GV',
            'banner_url' => 'https://example.com/banner.jpg',
            'location' => 'Lagos',
            'is_verified' => true,
            'rating' => 4.8,
            'fulfillment_days' => 2,
            'is_active' => true,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Premium Ankara Midi Dress - Edition 001',
            'category' => 'Women Fashion',
            'description' => 'Premium Ankara Midi Dress selected for Nigerian shoppers.',
            'image_url' => 'https://example.com/image.jpg',
            'price_kobo' => 100000,
            'search_terms' => ['ankara'],
            'seller_sku' => 'V0001-P0001',
            'inventory_count' => 5,
            'is_active' => true,
            'text_embedding' => [1, 0],
            'text_embedding_model' => 'old-text-model',
            'image_embedding' => [0, 1],
            'embeddings_indexed_at' => now(),
        ]);

        $this->artisan('marketplace:repair-product-names')->assertSuccessful();

        $product = $product->fresh();
        $this->assertStringNotContainsString('Edition', $product->name);
        $this->assertStringContainsString('Dress', $product->name);
        $this->assertStringContainsString('Lagos', $product->description);
        $this->assertNull($product->text_embedding);
        $this->assertNull($product->text_embedding_model);
        $this->assertNull($product->embeddings_indexed_at);
        $this->assertSame([0, 1], $product->image_embedding);
    }
}
