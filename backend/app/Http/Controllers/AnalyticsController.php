<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\SearchLog;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function summary()
    {
        $searches = SearchLog::count();
        $clickedSearches = SearchLog::has('clicks')->count();

        return response()->json([
            'total_searches' => $searches,
            'zero_result_rate' => $searches ? round(SearchLog::where('result_count', 0)->count() / $searches * 100, 1) : 0,
            'abandonment_rate' => $searches ? round(($searches - $clickedSearches) / $searches * 100, 1) : 0,
            'click_through_rate' => $searches ? round($clickedSearches / $searches * 100, 1) : 0,
        ]);
    }

    public function zeroResults()
    {
        return response()->json(SearchLog::where('result_count', 0)->latest()->limit(20)->get());
    }

    public function abandonmentRate()
    {
        $searches = SearchLog::count();

        return response()->json(['rate' => $searches ? round(SearchLog::doesntHave('clicks')->count() / $searches * 100, 1) : 0]);
    }

    public function categoryGaps()
    {
        $discovered = DB::table('search_clicks')->join('products', 'products.id', '=', 'search_clicks.product_id')
            ->selectRaw('products.category, count(*) as clicks')->groupBy('products.category')->pluck('clicks', 'category');

        return response()->json(Product::query()->select('category')->distinct()->pluck('category')
            ->map(fn (string $category) => ['category' => $category, 'clicks' => (int) ($discovered[$category] ?? 0)])
            ->sortBy('clicks')->values());
    }

    public function searchVolume()
    {
        return response()->json(SearchLog::query()->selectRaw('date(created_at) as date, count(*) as searches')
            ->groupByRaw('date(created_at)')->orderBy('date')->get());
    }

    public function vendorPerformance()
    {
        return response()->json(DB::table('vendors')
            ->leftJoin('products', 'products.vendor_id', '=', 'vendors.id')
            ->leftJoin('search_results', 'search_results.product_id', '=', 'products.id')
            ->leftJoin('search_clicks', function ($join): void {
                $join->on('search_clicks.product_id', '=', 'products.id')
                    ->on('search_clicks.search_log_id', '=', 'search_results.search_log_id');
            })
            ->where('vendors.is_active', true)
            ->selectRaw('vendors.id, vendors.name, vendors.slug, vendors.logo_url, vendors.is_verified, count(distinct products.id) as products_count, count(distinct search_results.id) as appearances, count(distinct search_clicks.id) as clicks')
            ->groupBy('vendors.id', 'vendors.name', 'vendors.slug', 'vendors.logo_url', 'vendors.is_verified')
            ->orderByDesc('clicks')
            ->get()
            ->map(fn ($vendor) => [
                ...((array) $vendor),
                'click_through_rate' => $vendor->appearances ? round($vendor->clicks / $vendor->appearances * 100, 1) : 0,
            ]));
    }

    public function commerceSummary()
    {
        $orders = Order::count();
        $gmvKobo = (int) Order::sum('total_kobo');

        return response()->json([
            'orders' => $orders,
            'gmv' => $gmvKobo / 100,
            'average_order_value' => $orders ? round($gmvKobo / $orders / 100, 2) : 0,
            'items_sold' => (int) DB::table('order_items')->sum('quantity'),
            'active_inventory' => (int) Product::where('is_active', true)->sum('inventory_count'),
        ]);
    }
}
