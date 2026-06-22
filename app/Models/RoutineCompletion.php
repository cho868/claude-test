<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutineCompletion extends Model
{
    protected $fillable = ['routine_task_id', 'user_id', 'period_key'];
}
