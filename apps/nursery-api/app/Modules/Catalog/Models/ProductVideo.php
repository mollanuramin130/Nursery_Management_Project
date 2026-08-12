<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVideo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'url', 'thumbnail_url', 'sort_order', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
