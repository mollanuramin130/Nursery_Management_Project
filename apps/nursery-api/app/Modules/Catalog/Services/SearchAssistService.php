<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SearchEvent;
use App\Modules\Catalog\Support\ProductPresenter;
use Illuminate\Support\Facades\Schema;

/**
 * Zero-result / weak-result search assistance from live catalog data only.
 */
class SearchAssistService
{
    public function assist(string $query): array
    {
        $query = trim($query);
        $tokens = collect(preg_split('/\s+/', mb_strtolower($query)) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 2)
            ->unique()
            ->take(6)
            ->values();

        $categoryHints = [];
        if ($tokens->isNotEmpty()) {
            $categoryQuery = Category::query()->where('status', 'active');
            $categoryQuery->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $token).'%';
                    $q->orWhere('name', 'like', $like)->orWhere('slug', 'like', $like);
                }
            });
            $categoryHints = $categoryQuery->orderBy('sort_order')->limit(6)->get(['id', 'name', 'slug'])
                ->map(fn (Category $c) => [
                    'type' => 'category',
                    'label' => $c->name,
                    'slug' => $c->slug,
                ])->values()->all();
        }

        // Keyword → filter shortcuts from known plant attributes (not invented products).
        $filterSuggestions = [];
        $map = [
            'indoor' => ['label' => 'Indoor plants', 'params' => ['indoor_outdoor' => 'indoor']],
            'outdoor' => ['label' => 'Outdoor plants', 'params' => ['indoor_outdoor' => 'outdoor']],
            'balcony' => ['label' => 'Outdoor / balcony', 'params' => ['indoor_outdoor' => 'both']],
            'low light' => ['label' => 'Low light plants', 'params' => ['sunlight' => 'low']],
            'low-light' => ['label' => 'Low light plants', 'params' => ['sunlight' => 'low']],
            'bright' => ['label' => 'Bright light plants', 'params' => ['sunlight' => 'bright_indirect']],
            'beginner' => ['label' => 'Beginner-friendly', 'params' => ['difficulty_level' => 'easy']],
            'easy' => ['label' => 'Easy care', 'params' => ['difficulty_level' => 'easy']],
            'pet' => ['label' => 'Pet-safe plants', 'params' => ['pet_safety' => 'safe']],
            'flowering' => ['label' => 'Flowering plants', 'params' => ['q' => 'flower']],
            'succulent' => ['label' => 'Succulents', 'params' => ['q' => 'succulent']],
            'herb' => ['label' => 'Herbs', 'params' => ['q' => 'herb']],
        ];
        $hay = mb_strtolower($query);
        foreach ($map as $needle => $suggestion) {
            if (str_contains($hay, $needle)) {
                $filterSuggestions[] = array_merge(['type' => 'filter'], $suggestion);
            }
        }
        $filterSuggestions = array_values(array_unique($filterSuggestions, SORT_REGULAR));

        $popular = Product::query()
            ->active()
            ->with('images')
            ->where('stock_status', '!=', 'out_of_stock')
            ->orderByDesc('rating_count')
            ->orderByDesc('rating_avg')
            ->limit(8)
            ->get()
            ->map(fn (Product $p) => ProductPresenter::card($p))
            ->values()
            ->all();

        $popularSearches = [];
        if (Schema::hasTable('search_events')) {
            $popularSearches = SearchEvent::query()
                ->selectRaw('normalized_query, COUNT(*) as searches')
                ->where('results_count', '>', 0)
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('normalized_query')
                ->orderByDesc('searches')
                ->limit(8)
                ->pluck('normalized_query')
                ->filter()
                ->values()
                ->all();
        }

        return [
            'query' => $query,
            'categories' => $categoryHints,
            'filters' => $filterSuggestions,
            'popular_products' => $popular,
            'popular_searches' => $popularSearches,
            'plant_finder' => [
                'available' => true,
                'path' => '/find-your-plant',
                'hint' => 'Not sure what to search? Use Find Your Plant for guided matching.',
            ],
        ];
    }
}
