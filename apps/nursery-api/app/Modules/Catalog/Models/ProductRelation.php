<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRelation extends Model
{
    protected $fillable = [
        'product_id', 'related_product_id', 'relation_type', 'sort_order', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}
