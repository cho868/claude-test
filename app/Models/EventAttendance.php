<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendance extends Model
{
    protected $fillable = ['schedule_event_id', 'user_id', 'status', 'comment'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(ScheduleEvent::class, 'schedule_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
