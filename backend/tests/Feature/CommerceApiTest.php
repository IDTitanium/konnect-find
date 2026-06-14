<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_product_details_are_available_for_active_products(): void
    {
        $product = Product::firstOrFail();

        $this->getJson("/api/products/$product->id")
            ->assertOk()
            ->assertJsonPath('id', $product->id)
            ->assertJsonPath('vendor.id', $product->vendor_id)
            ->assertJsonPath('price', $product->price_kobo / 100);
    }

    public function test_checkout_uses_server_prices_and_deducts_inventory(): void
    {
        $product = Product::firstOrFail();
        $startingInventory = $product->inventory_count;

        $response = $this->postJson('/api/orders', [
            'customer_name' => 'Idris Lawal',
            'customer_email' => 'idris@example.com',
            'customer_phone' => '08012345678',
            'delivery_address' => '12 Market Road',
            'delivery_city' => 'Lagos',
            'delivery_state' => 'Lagos',
            'payment_method' => 'pay_on_delivery',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 1]],
        ])->assertCreated()
            ->assertJsonPath('status', 'placed')
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('subtotal', ($product->price_kobo * 2) / 100);

        $this->assertStringStartsWith('KF-', $response->json('reference'));
        $this->assertSame($startingInventory - 2, $product->fresh()->inventory_count);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'unit_price_kobo' => $product->price_kobo,
            'quantity' => 2,
        ]);
    }

    public function test_checkout_rejects_quantity_above_available_inventory(): void
    {
        $product = Product::firstOrFail();

        $this->postJson('/api/orders', [
            'customer_name' => 'Idris Lawal',
            'customer_email' => 'idris@example.com',
            'customer_phone' => '08012345678',
            'delivery_address' => '12 Market Road',
            'delivery_city' => 'Lagos',
            'delivery_state' => 'Lagos',
            'payment_method' => 'bank_transfer',
            'items' => [['product_id' => $product->id, 'quantity' => $product->inventory_count + 1]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame($product->inventory_count, $product->fresh()->inventory_count);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_health_and_commerce_analytics_expose_operational_evidence(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('database.status', 'connected')
            ->assertJsonPath('marketplace.active_vendors', 5);

        $this->getJson('/api/analytics/commerce-summary')
            ->assertOk()
            ->assertJsonPath('orders', 0)
            ->assertJsonPath('gmv', 0)
            ->assertJsonPath('active_inventory', Product::where('is_active', true)->sum('inventory_count'));
    }
}
