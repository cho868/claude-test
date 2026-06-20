<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetupTask extends Model
{
    protected $fillable = ['key', 'category', 'title', 'description', 'done', 'sort_order'];

    protected $casts = [
        'done' => 'boolean',
    ];
}
