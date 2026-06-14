<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tournament extends Model
{
    protected $fillable = [
        'user_id', 'name', 'format', 'description',
        'participants', 'bracket', 'status',
    ];

    protected $casts = [
        'participants' => 'array',
        'bracket' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
