<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['user_id', 'spent_on', 'amount', 'kind', 'category', 'memo', 'is_shared'];

    protected $casts = [
        'spent_on' => 'date',
        'amount' => 'integer',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
