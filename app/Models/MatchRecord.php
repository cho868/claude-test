<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchRecord extends Model
{
    protected $fillable = [
        'user_id', 'game', 'result', 'opponent', 'score', 'played_on', 'note',
    ];

    protected $casts = [
        'played_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resultLabel(): string
    {
        return match ($this->result) {
            'win' => '勝ち',
            'loss' => '負け',
            default => '引分',
        };
    }
}
