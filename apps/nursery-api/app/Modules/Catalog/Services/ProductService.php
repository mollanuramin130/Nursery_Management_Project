<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 50);

        $query = Product::query()->active()->with(['images', 'plantProfile']);

        $this->applyFilters($query, $filters);

        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'popular' => $query->orderByDesc('rating_count')->orderByDesc('rating_avg'),
            'rating' => $query->orderByDesc('rating_avg'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        return $query->paginate($perPage);
    }

    public function findByIdOrSlug(string $idOrSlug): Product
    {
        $product = Product::query()
            ->active()
            ->where(function (Builder $q) use ($idOrSlug) {
                if (ctype_digit($idOrSlug)) {
                    $q->where('id', (int) $idOrSlug);
                }
                $q->orWhere('slug', $idOrSlug);
            })
            ->first();

        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        return $product;
    }

    public function related(int $productId): array
    {
        $product = Product::query()->active()->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        $product->load(['relatedLinks.relatedProduct.images']);

        return $product->relatedLinks
            ->where('relation_type', 'related')
            ->map(fn ($rel) => $rel->relatedProduct ? ProductPresenter::card($rel->relatedProduct) : null)
            ->filter()
            ->values()
            ->all();
    }

    public function recommendations(int $productId, string $type = 'similar'): array
    {
        $product = Product::query()->active()->with(['categories', 'plantProfile', 'tags', 'images'])->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        $query = Product::query()->active()->with('images')
            ->where('id', '!=', $productId)
            ->where('stock_status', '!=', 'out_of_stock');

        // Prefer recommendation_rules when a matching active rule exists.
        $rule = \Illuminate\Support\Facades\DB::table('recommendation_rules')
            ->where('type', $type)
            ->where('status', 'active')
            ->orderByDesc('priority')
            ->first();

        if ($rule && ! empty($rule->rules_json)) {
            $rules = json_decode($rule->rules_json, true) ?: [];
            if (! empty($rules['difficulty_level'])) {
                $query->whereHas('plantProfile', fn ($q) => $q->where('difficulty_level', $rules['difficulty_level']));
            }
            if (! empty($rules['indoor_outdoor'])) {
                $query->whereHas('plantProfile', fn ($q) => $q->where('indoor_outdoor', $rules['indoor_outdoor']));
            }
            if (! empty($rules['sunlight']) && is_array($rules['sunlight'])) {
                $query->whereHas('plantProfile', fn ($q) => $q->whereIn('sunlight', $rules['sunlight']));
            }
            if (! empty($rules['season'])) {
                $season = $rules['season'];
                $query->where(function (Builder $q) use ($season) {
                    $q->whereHas('tags', fn ($t) => $t->where('slug', $season))
                        ->orWhereHas('plantProfile', fn ($p) => $p->whereJsonContains('planting_season', $season));
                });
            }
            if (! empty($rules['product_ids']) && is_array($rules['product_ids'])) {
                $query->whereIn('id', $rules['product_ids']);
            }
        } else {
            match ($type) {
                'fbt' => $query->whereIn('id', function ($q) use ($productId) {
                    $q->select('related_product_id')
                        ->from('product_relations')
                        ->where('product_id', $productId)
                        ->where('relation_type', 'fbt');
                }),
                'beginner' => $query->whereHas('plantProfile', fn ($q) => $q->where('difficulty_level', 'easy')),
                'low_sunlight' => $query->whereHas('plantProfile', fn ($q) => $q->whereIn('sunlight', ['low', 'bright_indirect'])),
                'seasonal' => $query->whereHas('tags', fn ($q) => $q->whereIn('slug', ['monsoon', 'seasonal'])),
                'personalized' => $query->whereHas('categories', function ($c) use ($product) {
                    $c->whereIn('categories.id', $product->categories->pluck('id'));
                }),
                default => $query->where(function (Builder $q) use ($product) {
                    $categoryIds = $product->categories->pluck('id');
                    if ($categoryIds->isNotEmpty()) {
                        $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $categoryIds));
                    } else {
                        $q->where('product_type', $product->product_type);
                    }
                }),
            };
        }

        return $query->limit(12)->get()->map(fn (Product $p) => ProductPresenter::card($p))->values()->all();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function (Builder $builder) use ($q) {
                $builder->whereFullText(['name', 'description'], $q)
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            });
        }

        if (! empty($filters['category'])) {
            $slug = $filters['category'];
            $query->whereHas('categories', fn ($c) => $c->where('slug', $slug));
        }

        if (! empty($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }

        if (! empty($filters['product_types']) && is_array($filters['product_types'])) {
            $query->whereIn('product_type', $filters['product_types']);
        }

        if (! empty($filters['brand'])) {
            $query->whereHas('brand', fn ($b) => $b->where('slug', $filters['brand']));
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        if (! empty($filters['availability'])) {
            $query->where('stock_status', $filters['availability']);
        }

        if (! empty($filters['min_rating'])) {
            $query->where('rating_avg', '>=', (float) $filters['min_rating']);
        }

        foreach (['indoor_outdoor', 'sunlight', 'water_requirement', 'difficulty_level'] as $plantFilter) {
            if (! empty($filters[$plantFilter])) {
                $value = $filters[$plantFilter];
                // Normalize legacy UI alias: moderate → medium for water_requirement.
                if ($plantFilter === 'water_requirement' && $value === 'moderate') {
                    $value = 'medium';
                }
                $query->whereHas('plantProfile', fn ($p) => $p->where($plantFilter, $value));
            }
        }

        if (! empty($filters['season'])) {
            $season = $filters['season'];
            $query->where(function (Builder $q) use ($season) {
                $q->whereHas('tags', fn ($t) => $t->where('slug', $season))
                    ->orWhereHas('plantProfile', function ($p) use ($season) {
                        $p->whereJsonContains('planting_season', $season)
                            ->orWhereJsonContains('flowering_season', $season);
                    });
            });
        }

        if (! empty($filters['on_sale']) && filter_var($filters['on_sale'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price');
        }

        if (! empty($filters['pet_safety'])) {
            $query->whereHas('plantProfile', fn ($p) => $p->where('pet_safety', $filters['pet_safety']));
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn ($t) => $t->where('slug', $filters['tag']));
        }
    }
}
