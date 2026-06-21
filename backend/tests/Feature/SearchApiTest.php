<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_conversational_query_finds_relevant_products(): void
    {
        $response = $this->postJson('/api/search', ['query' => 'durable school bag for my pikin']);

        $response->assertOk()
            ->assertJsonPath('search_type', 'text')
            ->assertJsonPath('results.0.name', 'Durable Kids School Backpack')
            ->assertJsonPath('results.0.vendor.name', 'Everyday Market');
    }

    public function test_zero_result_search_is_visible_in_analytics(): void
    {
        $this->postJson('/api/search', ['query' => 'intergalactic submarine']);

        $this->getJson('/api/analytics/summary')
            ->assertOk()
            ->assertJsonPath('total_searches', 1)
            ->assertJsonPath('zero_result_rate', 100);
    }

    public function test_click_can_be_recorded_for_a_returned_product(): void
    {
        $search = $this->postJson('/api/search', ['query' => 'power solution'])->json();

        $this->postJson('/api/search/click', [
            'search_id' => $search['search_id'],
            'product_id' => $search['results'][0]['id'],
            'rank' => 1,
        ])->assertOk()->assertJson(['recorded' => true]);

        $this->getJson('/api/analytics/summary')->assertJsonPath('click_through_rate', 100);
    }

    public function test_catalogue_can_be_indexed_and_searched_with_embeddings(): void
    {
        $this->artisan('search:index')->assertSuccessful();

        $this->assertDatabaseMissing('products', ['text_embedding' => null]);
        $this->postJson('/api/search', ['query' => 'heavy-duty power solution'])
            ->assertOk()
            ->assertJsonPath('results.0.category', 'Power')
            ->assertJsonMissingPath('results.0.text_embedding')
            ->assertJsonMissingPath('results.0.image_embedding');
    }

    public function test_embeddings_can_be_exported_and_imported_for_seeded_products(): void
    {
        $path = storage_path('framework/testing/product-embeddings.jsonl');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        if (is_file($path)) {
            unlink($path);
        }

        $this->artisan('search:index', ['--limit' => 2])->assertSuccessful();
        $this->artisan('search:export-embeddings', ['--path' => $path])->assertSuccessful();

        Product::query()->update([
            'text_embedding' => null,
            'image_embedding' => null,
            'text_embedding_model' => null,
            'image_embedding_model' => null,
            'embeddings_indexed_at' => null,
        ]);

        $this->artisan('search:import-embeddings', ['--path' => $path])->assertSuccessful();

        $indexed = Product::query()->whereNotNull('text_embedding')->whereNotNull('image_embedding')->count();
        $this->assertSame(2, $indexed);
    }

    public function test_search_can_be_scoped_to_a_vendor_storefront(): void
    {
        $vendor = Vendor::where('slug', 'brighthome-energy')->firstOrFail();

        $response = $this->postJson('/api/search', [
            'query' => 'power solution',
            'vendor_id' => $vendor->id,
        ])->assertOk();

        $this->assertNotEmpty($response->json('results'));
        $this->assertSame([$vendor->id], collect($response->json('results'))->pluck('vendor_id')->unique()->values()->all());
    }

    public function test_marketplace_exposes_vendor_storefronts_and_performance(): void
    {
        $this->getJson('/api/vendors')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Aso Lagos', 'is_verified' => true]);

        $this->getJson('/api/vendors/aso-lagos')
            ->assertOk()
            ->assertJsonPath('slug', 'aso-lagos')
            ->assertJsonCount(3, 'products');

        $this->getJson('/api/analytics/vendor-performance')
            ->assertOk()
            ->assertJsonFragment(['name' => 'BrightHome Energy']);
    }
}
