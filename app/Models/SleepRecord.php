<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SleepRecord extends Model
{
    protected $fillable = [
        'user_id', 'sleep_date', 'bed_at', 'wake_at', 'duration_minutes', 'note',
    ];

    protected $casts = [
        'sleep_date' => 'date',
        'bed_at' => 'datetime',
        'wake_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hoursLabel(): string
    {
        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;

        return sprintf('%d時間%02d分', $h, $m);
    }
}
