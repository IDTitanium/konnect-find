<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommerceController extends Controller
{
    private const DELIVERY_FEE_KOBO = 250000;

    public function product(Product $product)
    {
        abort_unless($product->is_active && $product->vendor?->is_active, 404);

        return response()->json([
            ...$product->load('vendor')->toArray(),
            'price' => $product->price_kobo / 100,
        ]);
    }

    public function storeOrder(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:180'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'delivery_address' => ['required', 'string', 'max:500'],
            'delivery_city' => ['required', 'string', 'max:100'],
            'delivery_state' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'in:pay_on_delivery,bank_transfer'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $order = DB::transaction(function () use ($validated): Order {
            $requested = collect($validated['items'])->keyBy('product_id');
            $products = Product::query()
                ->with('vendor')
                ->whereIn('id', $requested->keys())
                ->lockForUpdate()
                ->get();

            if ($products->count() !== $requested->count()) {
                throw ValidationException::withMessages(['items' => 'One or more products are no longer available.']);
            }

            $subtotal = 0;
            foreach ($products as $product) {
                $quantity = $requested[$product->id]['quantity'];
                if (! $product->is_active || ! $product->vendor?->is_active) {
                    throw ValidationException::withMessages(['items' => "$product->name is no longer available."]);
                }
                if ($quantity > $product->inventory_count) {
                    throw ValidationException::withMessages(['items' => "Only $product->inventory_count units of $product->name remain."]);
                }
                $subtotal += $product->price_kobo * $quantity;
            }

            $order = Order::create([
                'reference' => 'KF-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                ...collect($validated)->except('items')->all(),
                'payment_status' => 'pending',
                'status' => 'placed',
                'subtotal_kobo' => $subtotal,
                'delivery_fee_kobo' => self::DELIVERY_FEE_KOBO,
                'total_kobo' => $subtotal + self::DELIVERY_FEE_KOBO,
            ]);

            foreach ($products as $product) {
                $quantity = $requested[$product->id]['quantity'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'vendor_id' => $product->vendor_id,
                    'product_name' => $product->name,
                    'vendor_name' => $product->vendor->name,
                    'seller_sku' => $product->seller_sku,
                    'quantity' => $quantity,
                    'unit_price_kobo' => $product->price_kobo,
                    'line_total_kobo' => $product->price_kobo * $quantity,
                ]);
                $product->decrement('inventory_count', $quantity);
            }

            return $order;
        });

        return response()->json($this->orderPayload($order), 201);
    }

    public function showOrder(string $reference)
    {
        return response()->json($this->orderPayload(
            Order::where('reference', $reference)->firstOrFail(),
        ));
    }

    private function orderPayload(Order $order): array
    {
        $order->load('items');

        return [
            ...$order->toArray(),
            'subtotal' => $order->subtotal_kobo / 100,
            'delivery_fee' => $order->delivery_fee_kobo / 100,
            'total' => $order->total_kobo / 100,
            'items' => $order->items->map(fn ($item) => [
                ...$item->toArray(),
                'unit_price' => $item->unit_price_kobo / 100,
                'line_total' => $item->line_total_kobo / 100,
            ]),
        ];
    }
}
