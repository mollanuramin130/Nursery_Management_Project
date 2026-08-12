<?php

use App\Modules\Catalog\Http\Controllers\BrandController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\HomeController;
use App\Modules\Catalog\Http\Controllers\PlantController;
use App\Modules\Catalog\Http\Controllers\PlantFinderController;
use App\Modules\Catalog\Http\Controllers\ProductController;
use App\Modules\Catalog\Http\Controllers\ProductViewController;
use App\Modules\Catalog\Http\Controllers\SearchAssistController;
use App\Modules\Catalog\Http\Controllers\SearchController;
use App\Modules\Catalog\Http\Controllers\SearchSuggestionController;
use App\Modules\Catalog\Http\Controllers\StockAlertController;
use Illuminate\Support\Facades\Route;

Route::get('home', HomeController::class);
Route::get('plant-finder/options', [PlantFinderController::class, 'options']);
Route::post('plant-finder/match', [PlantFinderController::class, 'match'])->middleware('throttle:30,1');
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{slug}', [CategoryController::class, 'show']);
Route::get('brands', [BrandController::class, 'index']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{id}/related', [ProductController::class, 'related'])->whereNumber('id');
Route::get('products/{id}/recommendations', [ProductController::class, 'recommendations'])->whereNumber('id');
// reviews routes loaded from Review module (products/{id}/reviews)
Route::get('products/{idOrSlug}', [ProductController::class, 'show']);
Route::get('plants', [PlantController::class, 'index']);
Route::get('plants/{idOrSlug}/care', [PlantController::class, 'care']);
Route::get('plants/{idOrSlug}', [PlantController::class, 'show']);
Route::get('search', SearchController::class);
Route::get('search/suggestions', SearchSuggestionController::class)->middleware('throttle:60,1');
Route::get('search/assist', SearchAssistController::class)->middleware('throttle:60,1');

Route::post('product-views', [ProductViewController::class, 'store'])
    ->middleware(['optional.jwt', 'throttle:120,1']);
Route::get('recently-viewed', [ProductViewController::class, 'recent'])
    ->middleware(['optional.jwt', 'throttle:60,1']);

Route::middleware(['auth:api', 'active.user'])->group(function () {
    Route::get('customer/stock-alerts', [StockAlertController::class, 'index']);
    Route::get('products/{id}/stock-alert', [StockAlertController::class, 'status'])->whereNumber('id');
    Route::post('products/{id}/stock-alert', [StockAlertController::class, 'store'])->whereNumber('id')->middleware('throttle:30,1');
    Route::delete('products/{id}/stock-alert', [StockAlertController::class, 'destroy'])->whereNumber('id');
});
