<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return ApiResponse::success([], 'Search suggestions retrieved successfully');
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        $products = Product::query()
            ->active()
            ->where(function ($query) use ($like, $q) {
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->orderByDesc('rating_count')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'product_type'])
            ->map(fn (Product $p) => [
                'type' => 'product',
                'label' => $p->name,
                'slug' => $p->slug,
                'product_type' => $p->product_type,
            ])
            ->values()
            ->all();

        $categories = Category::query()
            ->where('status', 'active')
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })
            ->orderBy('sort_order')
            ->limit(4)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $c) => [
                'type' => 'category',
                'label' => $c->name,
                'slug' => $c->slug,
            ])
            ->values()
            ->all();

        return ApiResponse::success(
            array_values(array_merge($categories, $products)),
            'Search suggestions retrieved successfully',
            200,
            ['query' => $q],
        );
    }
}
