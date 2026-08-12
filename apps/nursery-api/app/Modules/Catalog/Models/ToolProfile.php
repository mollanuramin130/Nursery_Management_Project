<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class ToolProfile extends Model
{
    protected $fillable = [
        'product_id', 'material', 'size', 'usage', 'warranty_months', 'brand_name', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
