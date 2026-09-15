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

    /** 現在の期間キー。判定はゲーム側のリセット設定に従う。 */
    public function currentPeriodKey(?Carbon $now = null): string
    {
        return $this->routine->periodKey($this->cadence, $now);
    }

    /** 次のリセット時刻。 */
    public function nextResetAt(?Carbon $now = null): Carbon
    {
        return $this->routine->nextResetAt($this->cadence, $now);
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
