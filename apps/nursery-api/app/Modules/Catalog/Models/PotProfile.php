<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class PotProfile extends Model
{
    protected $fillable = [
        'product_id', 'material', 'diameter_cm', 'height_cm', 'capacity_liters',
        'drainage_holes', 'indoor_outdoor_suitability', 'color', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'diameter_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'capacity_liters' => 'decimal:2',
            'drainage_holes' => 'boolean',
            'meta' => 'array',
        ];
    }
}
