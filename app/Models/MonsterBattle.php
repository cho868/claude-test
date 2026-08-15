<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonsterBattle extends Model
{
    protected $fillable = ['challenger_id', 'opponent_id', 'seed', 'winner_id', 'turns'];

    public function challenger(): BelongsTo { return $this->belongsTo(User::class, 'challenger_id'); }
    public function opponent(): BelongsTo   { return $this->belongsTo(User::class, 'opponent_id'); }
    public function winner(): BelongsTo     { return $this->belongsTo(User::class, 'winner_id'); }
}
