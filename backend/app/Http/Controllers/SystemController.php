<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function health()
    {
        DB::select('select 1');

        return response()->json([
            'status' => 'healthy',
            'service' => 'KonnectFind API',
            'database' => [
                'status' => 'connected',
                'driver' => DB::getDriverName(),
            ],
            'search' => [
                'text_provider' => config('services.search.text_provider'),
                'image_provider' => config('services.search.image_provider'),
                'indexed_products' => Product::whereNotNull('embeddings_indexed_at')->count(),
            ],
            'marketplace' => [
                'active_vendors' => Vendor::where('is_active', true)->count(),
                'active_products' => Product::where('is_active', true)->count(),
                'orders' => Order::count(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
