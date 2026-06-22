<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RoutineTask extends Model
{
    protected $fillable = ['game_routine_id', 'title', 'cadence', 'sort_order'];

    public function routine(): BelongsTo
    {
        return $this->belongsTo(GameRoutine::class, 'game_routine_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(RoutineCompletion::class);
    }

    /** 現在の期間キー（日課=日付 / 週課=ISO週 / 月課=年月） */
    public function currentPeriodKey(): string
    {
        $now = Carbon::now();

        return match ($this->cadence) {
            'weekly' => $now->isoFormat('GGGG-[W]WW'),
            'monthly' => $now->format('Y-m'),
            default => $now->format('Y-m-d'),
        };
    }

    public function cadenceLabel(): string
    {
        return match ($this->cadence) {
            'weekly' => '週課',
            'monthly' => '月課',
            default => '日課',
        };
    }
}
