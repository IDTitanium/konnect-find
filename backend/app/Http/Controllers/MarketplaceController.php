<?php

namespace App\Http\Controllers;

use App\Models\Vendor;

class MarketplaceController extends Controller
{
    public function index()
    {
        return response()->json(Vendor::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_verified')
            ->orderByDesc('rating')
            ->get());
    }

    public function show(Vendor $vendor)
    {
        abort_unless($vendor->is_active, 404);

        return response()->json($vendor->load([
            'products' => fn ($query) => $query->where('is_active', true)->latest(),
        ]));
    }
}
