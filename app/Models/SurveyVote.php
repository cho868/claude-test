<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyVote extends Model
{
    protected $fillable = ['survey_id', 'survey_option_id', 'user_id'];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(SurveyOption::class, 'survey_option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
