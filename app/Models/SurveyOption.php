<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyOption extends Model
{
    protected $fillable = ['survey_id', 'label', 'sort_order'];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(SurveyVote::class);
    }
}
