<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCycle extends Model
{
    protected $fillable = [
        'subscription_id', 'cycle_number', 'scheduled_at', 'status',
        'order_id', 'unit_price', 'quantity', 'amount',
        'failure_reason', 'processed_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'processed_at' => 'datetime',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
