<?php

use App\Modules\Campaign\Http\Controllers\BannerController;
use App\Modules\Campaign\Http\Controllers\CampaignController;
use App\Modules\Campaign\Http\Controllers\OfferController;
use Illuminate\Support\Facades\Route;

Route::get('banners', [BannerController::class, 'index']);
Route::get('offers', [OfferController::class, 'index']);
Route::get('campaigns/featured', [CampaignController::class, 'featured']);
Route::get('campaigns', [CampaignController::class, 'index']);
Route::get('campaigns/{slug}/products', [CampaignController::class, 'products']);
Route::get('campaigns/{slug}', [CampaignController::class, 'show']);
