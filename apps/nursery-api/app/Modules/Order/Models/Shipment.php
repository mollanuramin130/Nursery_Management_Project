<?php

namespace App\Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id', 'status', 'carrier', 'tracking_number', 'tracking_url',
        'shipped_at', 'delivered_at', 'shipping_method_id', 'warehouse_id',
        'eta_date', 'weight_grams', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'eta_date' => 'date',
            'meta' => 'array',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('event_at')->orderBy('id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
