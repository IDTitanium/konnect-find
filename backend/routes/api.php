<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CommerceController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

Route::post('/search', [SearchController::class, 'search']);
Route::post('/search/click', [SearchController::class, 'click']);
Route::get('/vendors', [MarketplaceController::class, 'index']);
Route::get('/vendors/{vendor:slug}', [MarketplaceController::class, 'show']);
Route::get('/products/{product}', [CommerceController::class, 'product']);
Route::post('/orders', [CommerceController::class, 'storeOrder']);
Route::get('/orders/{reference}', [CommerceController::class, 'showOrder']);
Route::get('/health', [SystemController::class, 'health']);

Route::prefix('analytics')->group(function (): void {
    Route::get('/summary', [AnalyticsController::class, 'summary']);
    Route::get('/zero-results', [AnalyticsController::class, 'zeroResults']);
    Route::get('/abandonment-rate', [AnalyticsController::class, 'abandonmentRate']);
    Route::get('/category-gaps', [AnalyticsController::class, 'categoryGaps']);
    Route::get('/search-volume', [AnalyticsController::class, 'searchVolume']);
    Route::get('/vendor-performance', [AnalyticsController::class, 'vendorPerformance']);
    Route::get('/commerce-summary', [AnalyticsController::class, 'commerceSummary']);
});
