<?php

namespace App\Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'label', 'name', 'phone', 'line1', 'line2',
        'city', 'state', 'postal_code', 'country', 'is_default', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'name' => $this->name,
            'phone' => $this->phone,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'is_default' => (bool) $this->is_default,
        ];
    }
}
