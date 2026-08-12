<?php

namespace App\Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDevice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'platform', 'device_id', 'push_token',
        'app_version', 'is_active', 'last_seen_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
