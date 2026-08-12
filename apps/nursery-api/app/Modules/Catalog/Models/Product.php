<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id', 'product_type', 'name', 'slug', 'sku', 'description',
        'price', 'compare_at_price', 'currency', 'status', 'stock_status',
        'is_featured', 'is_new', 'rating_avg', 'rating_count', 'published_at',
        'tax_class', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags')->withTimestamps();
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Campaign\Models\Campaign::class,
            'campaign_products',
        )->withPivot('sort_order')->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductVideo::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function plantProfile(): HasOne
    {
        return $this->hasOne(PlantProfile::class);
    }

    public function fertilizerProfile(): HasOne
    {
        return $this->hasOne(FertilizerProfile::class);
    }

    public function potProfile(): HasOne
    {
        return $this->hasOne(PotProfile::class);
    }

    public function toolProfile(): HasOne
    {
        return $this->hasOne(ToolProfile::class);
    }

    public function soilProfile(): HasOne
    {
        return $this->hasOne(SoilProfile::class);
    }

    public function accessoryProfile(): HasOne
    {
        return $this->hasOne(AccessoryProfile::class);
    }

    public function relatedLinks(): HasMany
    {
        return $this->hasMany(ProductRelation::class)->orderBy('sort_order');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\InventoryItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function primaryImageUrl(): ?string
    {
        $primary = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return $primary?->url;
    }

    public function badges(): array
    {
        $meta = $this->meta ?? [];
        $badges = array_values(array_filter(
            is_array($meta['badges'] ?? null) ? $meta['badges'] : [],
            fn ($b) => is_string($b) && $b !== '',
        ));

        // Derive commerce badges from authoritative product fields (not client-side).
        if ($this->is_new && ! in_array('new', $badges, true) && ! in_array('new-arrival', $badges, true)) {
            $badges[] = 'new';
        }
        if ($this->stock_status === 'low_stock' && ! in_array('low-stock', $badges, true)) {
            $badges[] = 'low-stock';
        }
        if (
            $this->compare_at_price !== null
            && (float) $this->compare_at_price > (float) $this->price
            && ! in_array('sale', $badges, true)
        ) {
            $badges[] = 'sale';
        }

        return array_values(array_unique($badges));
    }

    public function availableQty(): int
    {
        return (int) $this->inventoryItems->sum(function ($item) {
            return max(0, $item->qty_on_hand - $item->qty_reserved - $item->qty_damaged);
        });
    }
}
