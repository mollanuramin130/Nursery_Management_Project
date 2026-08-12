<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantProfile extends Model
{
    protected $fillable = [
        'product_id', 'common_name', 'scientific_name', 'local_names', 'plant_kind',
        'indoor_outdoor', 'sunlight', 'water_requirement', 'soil_type',
        'temperature_min_c', 'temperature_max_c', 'humidity_requirement', 'growth_rate',
        'mature_height_cm', 'mature_width_cm', 'flowering_season', 'fruiting_season',
        'planting_season', 'bloom_color', 'flowering_duration', 'lifespan',
        'difficulty_level', 'care_level', 'propagation_method', 'toxicity_info',
        'pet_safety', 'benefits', 'uses', 'growing_instructions', 'planting_instructions',
        'pruning_instructions', 'fertilization_instructions', 'pest_disease_info',
        'harvest_info', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'local_names' => 'array',
            'flowering_season' => 'array',
            'fruiting_season' => 'array',
            'planting_season' => 'array',
            'benefits' => 'array',
            'uses' => 'array',
            'temperature_min_c' => 'decimal:2',
            'temperature_max_c' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
