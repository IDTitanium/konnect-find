<?php

namespace App\Http\Controllers;

use App\Models\SearchClick;
use App\Models\SearchLog;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request, SearchService $searchService)
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:500', 'required_without:image'],
            'image' => ['nullable', 'image', 'max:5120', 'required_without:query'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
        ]);

        return response()->json($searchService->search(
            $validated['query'] ?? null,
            $request->file('image'),
            $validated['session_id'] ?? (string) str()->uuid(),
            $validated['vendor_id'] ?? null,
        ));
    }

    public function click(Request $request)
    {
        $validated = $request->validate([
            'search_id' => ['required', 'exists:search_logs,id'],
            'product_id' => ['required', 'exists:products,id'],
            'rank' => ['required', 'integer', 'min:1'],
        ]);

        $search = SearchLog::findOrFail($validated['search_id']);
        abort_unless($search->results()->where('product_id', $validated['product_id'])->exists(), 422, 'Product was not returned by this search.');
        SearchClick::firstOrCreate(
            ['search_log_id' => $validated['search_id'], 'product_id' => $validated['product_id']],
            ['rank' => $validated['rank']],
        );

        return response()->json(['recorded' => true]);
    }
}
