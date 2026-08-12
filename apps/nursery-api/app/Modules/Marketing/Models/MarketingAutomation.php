<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Campaign\Models\Campaign;
use App\Modules\Promotion\Models\Coupon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingAutomation extends Model
{
    protected $fillable = [
        'key', 'name', 'type', 'status', 'segment_id', 'campaign_id', 'coupon_id',
        'channels_json', 'config_json', 'title_template', 'body_template',
        'scheduled_at', 'last_run_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'channels_json' => 'array',
            'config_json' => 'array',
            'scheduled_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CustomerSegment::class, 'segment_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MarketingDelivery::class, 'automation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
