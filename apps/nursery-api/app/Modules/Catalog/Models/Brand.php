<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'logo_url', 'status', 'description', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
