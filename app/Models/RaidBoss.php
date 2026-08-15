<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RaidBoss extends Model
{
    protected $fillable = ['week', 'name', 'species', 'total_hp', 'defeated_at'];

    protected $casts = ['defeated_at' => 'datetime'];
}
