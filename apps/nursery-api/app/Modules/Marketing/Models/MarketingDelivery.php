<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Campaign\Models\Campaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingDelivery extends Model
{
    protected $fillable = [
        'automation_id', 'campaign_id', 'user_id', 'channel', 'status',
        'idempotency_key', 'notification_id', 'skip_reason', 'meta_json', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(MarketingAutomation::class, 'automation_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
