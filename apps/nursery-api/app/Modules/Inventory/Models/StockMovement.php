<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inventory_item_id', 'product_id', 'product_variant_id', 'warehouse_id',
        'type', 'qty_delta', 'qty_before', 'qty_after', 'reference_type', 'reference_id', 'note',
        'actor_user_id', 'meta', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement) {
            $movement->created_at ??= now();
        });
    }
}
