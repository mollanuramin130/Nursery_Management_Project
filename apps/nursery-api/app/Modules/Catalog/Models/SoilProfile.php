<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class SoilProfile extends Model
{
    protected $fillable = [
        'product_id', 'composition', 'ph_range', 'suitable_for',
        'usage_instructions', 'organic', 'weight_kg', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'suitable_for' => 'array',
            'organic' => 'boolean',
            'weight_kg' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
