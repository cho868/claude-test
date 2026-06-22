<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboEntry extends Model
{
    protected $fillable = [
        'user_id', 'character', 'starter', 'hit_type', 'combo', 'damage', 'note', 'is_public',
    ];

    protected $casts = ['is_public' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
