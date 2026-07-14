<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'item_id',
        'title',
        'nickname',
        'rating',
        'comment',
        'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }
}
