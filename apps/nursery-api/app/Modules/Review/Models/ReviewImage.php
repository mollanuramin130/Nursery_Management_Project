<?php

namespace App\Modules\Review\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewImage extends Model
{
    use SoftDeletes;

    protected $fillable = ['review_id', 'url', 'sort_order', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
