<?php

namespace App\Modules\Payment\Models;

use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id', 'user_id', 'provider', 'method', 'amount', 'currency', 'status',
        'idempotency_key', 'provider_order_id', 'provider_payment_id', 'provider_signature',
        'failure_code', 'failure_message', 'paid_at', 'raw_response_json', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'raw_response_json' => 'array',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
