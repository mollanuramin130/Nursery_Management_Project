<?php

namespace App\Modules\Marketing\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSegment extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'criteria_json', 'is_system', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'criteria_json' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
