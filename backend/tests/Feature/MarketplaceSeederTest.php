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

        $this->artisan('marketplace:import-seeder', ['--path' => $path, '--fresh' => true])
            ->assertSuccessful();

        $this->assertSame(2, Vendor::count());
        $this->assertSame(6, Product::count());
        $this->assertSame(3, Product::where('vendor_id', Vendor::first()->id)->count());
        $this->assertGreaterThan(1, Product::distinct()->count('category'));

        unlink($path);
    }
}
