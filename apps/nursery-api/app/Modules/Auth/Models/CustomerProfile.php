<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'preferred_language',
        'marketing_opt_in',
        'default_address_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'marketing_opt_in' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
