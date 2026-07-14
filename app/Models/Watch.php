<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Watch extends Model
{
    protected $fillable = [
        'line_user_id',
        'keyword',
        'seen_item_codes',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'seen_item_codes' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    public function lineUser(): BelongsTo
    {
        return $this->belongsTo(LineUser::class);
    }
}
