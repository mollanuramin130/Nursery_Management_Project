<?php

namespace App\Modules\Admin\Services;

use App\Modules\Catalog\Models\PlantProfile;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\Tag;
use App\Modules\Inventory\Models\InventoryItem;
use App\Shared\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminProductService
{
    public function list(?string $status, ?string $q, int $perPage = 20): array
    {
        $paginator = Product::query()
            ->with(['brand', 'categories', 'images', 'inventoryItems'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn (Product $p) => $this->summary($p))->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function create(array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($payload, $actorUserId) {
            $slug = $payload['slug'] ?? Str::slug($payload['name']);
            $product = Product::query()->create([
                'brand_id' => $payload['brand_id'] ?? null,
                'product_type' => $payload['product_type'],
                'name' => $payload['name'],
                'slug' => $slug,
                'sku' => $payload['sku'],
                'description' => $payload['description'] ?? null,
                'price' => $payload['price'],
                'compare_at_price' => $payload['compare_at_price'] ?? null,
                'currency' => $payload['currency'] ?? 'INR',
                'status' => $payload['status'] ?? 'draft',
                'stock_status' => $payload['stock_status'] ?? 'in_stock',
                'is_featured' => (bool) ($payload['is_featured'] ?? false),
                'is_new' => (bool) ($payload['is_new'] ?? false),
                'published_at' => ($payload['status'] ?? 'draft') === 'active' ? now() : null,
                'tax_class' => $payload['tax_class'] ?? null,
                'meta' => $payload['meta'] ?? null,
            ]);

            if (! empty($payload['category_ids'])) {
                $product->categories()->sync($payload['category_ids']);
            }

            if (! empty($payload['tags'])) {
                $tagIds = collect($payload['tags'])->map(function ($tag) {
                    $slug = Str::slug((string) $tag);

                    return Tag::query()->firstOrCreate(
                        ['slug' => $slug],
                        ['name' => (string) $tag, 'status' => 'active'],
                    )->id;
                })->all();
                $product->tags()->sync($tagIds);
            }

            if (! empty($payload['plant']) && $product->product_type === 'plant') {
                PlantProfile::query()->create(array_merge(
                    ['product_id' => $product->id],
                    $payload['plant'],
                ));
            }

            if (! empty($payload['inventory'])) {
                InventoryItem::query()->create([
                    'warehouse_id' => $payload['inventory']['warehouse_id'],
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'qty_on_hand' => (int) ($payload['inventory']['qty_on_hand'] ?? 0),
                    'qty_reserved' => 0,
                    'qty_damaged' => 0,
                    'low_stock_threshold' => (int) ($payload['inventory']['low_stock_threshold'] ?? 5),
                ]);
            }

            AuditLogger::log('product.create', 'product', $product->id, null, $product->toArray(), $actorUserId);

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'status' => $product->status,
            ];
        });
    }

    public function update(int $id, array $payload, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($id, $payload, $actorUserId) {
            $product = Product::query()->find($id);
            if (! $product) {
                throw new NotFoundHttpException('Product not found');
            }

            $before = $product->toArray();
            $product->fill(collect($payload)->only([
                'brand_id', 'product_type', 'name', 'slug', 'sku', 'description',
                'price', 'compare_at_price', 'currency', 'status', 'stock_status',
                'is_featured', 'is_new', 'tax_class', 'meta',
            ])->all());

            if (isset($payload['status']) && $payload['status'] === 'active' && ! $product->published_at) {
                $product->published_at = now();
            }

            $product->save();

            if (array_key_exists('category_ids', $payload)) {
                $product->categories()->sync($payload['category_ids'] ?? []);
            }

            if (array_key_exists('tags', $payload)) {
                $tagIds = collect($payload['tags'] ?? [])->map(function ($tag) {
                    $slug = Str::slug((string) $tag);

                    return Tag::query()->firstOrCreate(
                        ['slug' => $slug],
                        ['name' => (string) $tag, 'status' => 'active'],
                    )->id;
                })->all();
                $product->tags()->sync($tagIds);
            }

            if (! empty($payload['plant']) && $product->product_type === 'plant') {
                $profile = $product->plantProfile ?: new PlantProfile(['product_id' => $product->id]);
                $profile->fill($payload['plant']);
                $profile->product_id = $product->id;
                $profile->save();
            }

            AuditLogger::log('product.update', 'product', $product->id, $before, $product->fresh()->toArray(), $actorUserId);

            return $this->summary($product->fresh(['brand', 'categories', 'images', 'inventoryItems']));
        });
    }

    public function detail(int $id): array
    {
        $product = Product::query()
            ->with(['brand', 'categories', 'images', 'tags', 'plantProfile', 'inventoryItems.warehouse'])
            ->find($id);

        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        $summary = $this->summary($product);

        return array_merge($summary, [
            'description' => $product->description,
            'compare_at_price' => $product->compare_at_price !== null ? (float) $product->compare_at_price : null,
            'brand_id' => $product->brand_id,
            'category_ids' => $product->categories?->pluck('id')->values()->all() ?? [],
            'tags' => $product->tags?->pluck('name')->values()->all() ?? [],
            'is_featured' => (bool) $product->is_featured,
            'is_new' => (bool) $product->is_new,
            'stock_status' => $product->stock_status,
            'plant' => $product->plantProfile ? collect($product->plantProfile->toArray())
                ->except(['id', 'product_id', 'created_at', 'updated_at'])
                ->all() : null,
            'images' => $product->images->map(fn (ProductImage $img) => [
                'id' => $img->id,
                'url' => $img->url,
                'alt' => $img->alt,
                'is_primary' => (bool) $img->is_primary,
                'sort_order' => (int) $img->sort_order,
            ])->values()->all(),
            'inventory' => $product->inventoryItems->map(fn (InventoryItem $item) => [
                'id' => $item->id,
                'warehouse_id' => $item->warehouse_id,
                'warehouse_code' => $item->warehouse?->code,
                'qty_on_hand' => (int) $item->qty_on_hand,
                'qty_reserved' => (int) $item->qty_reserved,
                'qty_damaged' => (int) $item->qty_damaged,
                'sellable' => max(0, (int) $item->qty_on_hand - (int) $item->qty_reserved - (int) $item->qty_damaged),
                'low_stock_threshold' => (int) $item->low_stock_threshold,
            ])->values()->all(),
        ]);
    }

    public function delete(int $id, ?int $actorUserId = null): void
    {
        $product = Product::query()->find($id);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        $before = $product->toArray();
        $product->delete();
        AuditLogger::log('product.delete', 'product', $id, $before, null, $actorUserId);
    }

    public function addImages(int $id, array $images, ?int $actorUserId = null): array
    {
        $product = Product::query()->find($id);
        if (! $product) {
            throw new NotFoundHttpException('Product not found');
        }

        $created = [];
        foreach ($images as $index => $image) {
            $row = ProductImage::query()->create([
                'product_id' => $product->id,
                'url' => $image['url'],
                'alt' => $image['alt'] ?? $product->name,
                'is_primary' => (bool) ($image['is_primary'] ?? false),
                'sort_order' => $image['sort_order'] ?? $index,
            ]);
            $created[] = [
                'id' => $row->id,
                'url' => $row->url,
                'is_primary' => $row->is_primary,
                'sort_order' => $row->sort_order,
            ];
        }

        AuditLogger::log('product.images.add', 'product', $product->id, null, ['images' => $created], $actorUserId);

        return $created;
    }

    private function summary(Product $p): array
    {
        $sellable = 0;
        if ($p->relationLoaded('inventoryItems')) {
            $sellable = (int) $p->inventoryItems->sum(function (InventoryItem $item) {
                return max(0, (int) $item->qty_on_hand - (int) $item->qty_reserved - (int) $item->qty_damaged);
            });
        }

        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'slug' => $p->slug,
            'product_type' => $p->product_type,
            'price' => (float) $p->price,
            'compare_at_price' => $p->compare_at_price !== null ? (float) $p->compare_at_price : null,
            'status' => $p->status,
            'stock_qty' => $sellable,
            'brand' => $p->brand?->name,
            'categories' => $p->categories?->pluck('name')->values()->all() ?? [],
            'thumbnail_url' => $p->primaryImageUrl(),
            'updated_at' => optional($p->updated_at)?->toIso8601String(),
        ];
    }
}
