<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Campaign\Models\Banner;
use App\Modules\Campaign\Models\Campaign;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductPresenter;
use Illuminate\Support\Facades\Cache;

class HomeService
{
    public const CACHE_KEY = 'catalog:home:feed:v2';

    public const CACHE_TTL_SECONDS = 60;

    public function feed(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->buildFeed();
        });
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function buildFeed(): array
    {
        $banners = Banner::query()->active()
            ->where('placement', 'home')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'image_url' => $b->image_url,
                'link_type' => $b->link_type,
                'link_value' => $b->link_value,
                'sort_order' => $b->sort_order,
            ])->values()->all();

        $campaigns = Campaign::query()->active()
            ->orderByDesc('priority')
            ->limit(6)
            ->get()
            ->map(fn (Campaign $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'title' => $c->title,
                'subtitle' => $c->subtitle,
                'image_url' => $c->image_url,
            ])->values()->all();

        $categories = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->limit(10)
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'image_url' => $c->image_url,
                'parent_id' => $c->parent_id,
            ])->values()->all();

        $mapCards = fn ($products) => $products->map(fn (Product $p) => ProductPresenter::card($p))->values()->all();

        $indoor = Product::query()->active()->with('images')
            ->where('stock_status', '!=', 'out_of_stock')
            ->whereHas('plantProfile', fn ($q) => $q->where('indoor_outdoor', 'indoor'))
            ->orderByDesc('rating_count')
            ->limit(8)->get();

        $outdoor = Product::query()->active()->with('images')
            ->where('stock_status', '!=', 'out_of_stock')
            ->whereHas('plantProfile', fn ($q) => $q->whereIn('indoor_outdoor', ['outdoor', 'both']))
            ->orderByDesc('rating_count')
            ->limit(8)->get();

        $lowMaintenance = Product::query()->active()->with('images')
            ->where('stock_status', '!=', 'out_of_stock')
            ->whereHas('plantProfile', fn ($q) => $q->where('difficulty_level', 'easy'))
            ->orderByDesc('rating_count')
            ->limit(8)->get();

        $feed = [
            'banners' => $banners,
            'categories' => $categories,
            'campaigns' => $campaigns,
            'featured_products' => $mapCards(
                Product::query()->active()->with('images')->where('is_featured', true)->orderByDesc('rating_count')->limit(8)->get()
            ),
            'new_arrivals' => $mapCards(
                Product::query()->active()->with('images')->where('is_new', true)->orderByDesc('published_at')->limit(8)->get()
            ),
            'best_sellers' => $mapCards(
                Product::query()->active()->with('images')
                    ->where('stock_status', '!=', 'out_of_stock')
                    ->orderByDesc('rating_count')->orderByDesc('rating_avg')->limit(8)->get()
            ),
            // Explainable rule: easy-care plants (not ML personalization).
            'recommended_for_you' => $mapCards($lowMaintenance),
            'definitions' => [
                'recommended_for_you' => 'Active easy-care (difficulty_level=easy) plants in stock — rule-based, not purchase-history ML',
                'best_sellers' => 'Ordered by rating_count / rating_avg (not sales units) — see analytics docs',
                'indoor_plants' => 'plant_profiles.indoor_outdoor = indoor',
                'outdoor_plants' => 'plant_profiles.indoor_outdoor in (outdoor, both)',
                'low_maintenance' => 'Same as recommended_for_you (easy difficulty)',
            ],
        ];

        // Only expose taxonomy rails when catalog data exists (no empty fabricated sections).
        if ($indoor->isNotEmpty()) {
            $feed['indoor_plants'] = $mapCards($indoor);
        }
        if ($outdoor->isNotEmpty()) {
            $feed['outdoor_plants'] = $mapCards($outdoor);
        }
        if ($lowMaintenance->isNotEmpty()) {
            $feed['low_maintenance'] = $mapCards($lowMaintenance);
        }

        return $feed;
    }
}
