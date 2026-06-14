<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'multiple_choice', 'is_closed', 'closes_at',
    ];

    protected $casts = [
        'multiple_choice' => 'boolean',
        'is_closed' => 'boolean',
        'closes_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(SurveyOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(SurveyVote::class);
    }

    public function totalVotes(): int
    {
        return $this->votes()->count();
    }
}
