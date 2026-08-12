<?php

namespace App\Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'type', 'category', 'title', 'body', 'data_json',
        'is_read', 'read_at', 'meta', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class);
    }

    public function deliveries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
