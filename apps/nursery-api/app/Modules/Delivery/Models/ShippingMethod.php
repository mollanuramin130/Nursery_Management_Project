<?php

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'price', 'currency', 'eta_min_days', 'eta_max_days', 'status', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'eta_min_days' => (int) $this->eta_min_days,
            'eta_max_days' => (int) $this->eta_max_days,
        ];
    }
}
