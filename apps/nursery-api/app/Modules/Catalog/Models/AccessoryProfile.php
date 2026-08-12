<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class AccessoryProfile extends Model
{
    protected $fillable = [
        'product_id', 'material', 'usage', 'indoor_outdoor_suitability', 'pack_qty', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
