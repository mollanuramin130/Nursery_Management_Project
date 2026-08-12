<?php

namespace App\Modules\Order\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Payment\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number', 'user_id', 'status', 'currency',
        'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total',
        'coupon_code', 'campaign_id', 'payment_method', 'shipping_method_id', 'notes',
        'shipping_address_json', 'billing_address_json',
        'placed_at', 'confirmed_at', 'cancelled_at', 'cancel_reason',
        'ip', 'platform', 'request_id', 'warehouse_id',
        'subscription_id', 'subscription_cycle_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'shipping_address_json' => 'array',
            'billing_address_json' => 'array',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class)->orderByDesc('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(\App\Modules\Admin\Models\Refund::class)->orderByDesc('id');
    }
}
