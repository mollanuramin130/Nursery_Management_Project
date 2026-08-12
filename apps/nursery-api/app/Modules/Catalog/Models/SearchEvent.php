<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class SearchEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'query',
        'normalized_query',
        'results_count',
        'user_id',
        'platform',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'results_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
