<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseRecord extends Model
{
    protected $fillable = ['user_id', 'recorded_on', 'activity', 'minutes', 'calories'];

    protected $casts = [
        'recorded_on' => 'date',
        'minutes' => 'integer',
        'calories' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
