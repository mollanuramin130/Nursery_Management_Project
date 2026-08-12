<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Admin\Models\Supplier;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id', 'product_id', 'supplier_sku', 'unit_cost',
        'lead_time_days', 'moq', 'status', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
