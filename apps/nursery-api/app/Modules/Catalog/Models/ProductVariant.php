<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'name', 'price', 'compare_at_price',
        'attributes_json', 'status', 'barcode', 'weight_grams', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'attributes_json' => 'array',
            'meta' => 'array',
        ];
    }
}
