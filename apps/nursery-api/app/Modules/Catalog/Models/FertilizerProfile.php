<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class FertilizerProfile extends Model
{
    protected $fillable = [
        'product_id', 'npk_ratio', 'suitable_plant_types', 'application_frequency',
        'application_quantity', 'usage_instructions', 'organic', 'form', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'suitable_plant_types' => 'array',
            'organic' => 'boolean',
            'meta' => 'array',
        ];
    }
}
