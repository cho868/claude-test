<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Title extends Model
{
    protected $fillable = ['name', 'required_points', 'color', 'icon', 'description'];

    protected $casts = [
        'required_points' => 'integer',
    ];
}
