<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointLog extends Model
{
    protected $fillable = ['user_id', 'amount', 'reason', 'description'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
