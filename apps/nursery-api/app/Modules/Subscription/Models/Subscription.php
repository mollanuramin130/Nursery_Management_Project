<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'PENDING',
        'ACTIVE',
        'PAUSED',
        'PAYMENT_FAILED',
        'CANCELLED',
        'EXPIRED',
        'COMPLETED',
    ];

    public const FREQUENCIES = [
        'WEEKLY',
        'BIWEEKLY',
        'MONTHLY',
        'QUARTERLY',
        'YEARLY',
    ];

    protected $fillable = [
        'subscription_number', 'user_id', 'subscription_plan_id', 'product_id',
        'quantity', 'frequency', 'unit_price', 'currency', 'status',
        'address_id', 'shipping_address_json', 'billing_address_json',
        'shipping_method_id', 'payment_method', 'cycle_count',
        'next_billing_at', 'paused_at', 'cancelled_at', 'cancel_reason',
        'failed_payment_count', 'max_failed_payments', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'shipping_address_json' => 'array',
            'billing_address_json' => 'array',
            'next_billing_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(SubscriptionCycle::class)->orderBy('cycle_number');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->orderByDesc('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'subscription_id');
    }
}
