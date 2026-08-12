<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'city', 'is_default', 'status', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'meta' => 'array',
        ];
    }
}
