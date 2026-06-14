<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierList extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'tiers', 'is_public'];

    protected $casts = [
        'tiers' => 'array',
        'is_public' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
