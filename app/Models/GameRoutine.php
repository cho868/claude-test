<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 管理対象のソシャゲ1本。
 *
 * ソシャゲの「1日」は 0:00 始まりとは限らない（朝5時リセットが多い）。
 * 期間の判定はすべてこのモデルのリセット設定を基準に行う。
 */
class GameRoutine extends Model
{
    protected $fillable = ['user_id', 'name', 'icon', 'reset_hour', 'reset_dow', 'reset_day', 'notify', 'sort_order'];

    protected function casts(): array
    {
        return [
            'reset_hour' => 'integer',
            'reset_dow' => 'integer',
            'reset_day' => 'integer',
            'notify' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RoutineTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * いま進行中の期間が「実時間で」いつ始まったか。
     * 例: 5時リセットのゲームで 9/15 02:00 なら、日課の期間は 9/14 05:00 開始。
     */
    public function periodStart(string $cadence, ?Carbon $now = null): Carbon
    {
        $now = ($now ?? Carbon::now())->copy();
        // リセット時刻ぶん巻き戻した「ゲーム内の今日」
        $inGame = $now->copy()->subHours($this->reset_hour);

        $start = match ($cadence) {
            'weekly' => $inGame->copy()->startOfDay()
                ->subDays(($inGame->dayOfWeek - $this->reset_dow + 7) % 7),
            'monthly' => $this->monthlyStart($inGame),
            default => $inGame->copy()->startOfDay(),
        };

        return $start->addHours($this->reset_hour);
    }

    /** 次にリセットされる実時間。カウントダウンと通知タイミングに使う。 */
    public function nextResetAt(string $cadence, ?Carbon $now = null): Carbon
    {
        $start = $this->periodStart($cadence, $now);

        return match ($cadence) {
            'weekly' => $start->copy()->addWeek(),
            'monthly' => $start->copy()->addMonthNoOverflow(),
            default => $start->copy()->addDay(),
        };
    }

    /**
     * 完了記録のキー。同じ期間内なら同じ文字列になる。
     * 日課だけは旧形式(Y-m-d)と互換になるよう接頭辞を付けない。
     */
    public function periodKey(string $cadence, ?Carbon $now = null): string
    {
        $start = $this->periodStart($cadence, $now);

        return match ($cadence) {
            'weekly' => 'W:'.$start->format('Y-m-d'),
            'monthly' => 'M:'.$start->format('Y-m'),
            default => $start->format('Y-m-d'),
        };
    }

    /** 月課の開始日（ゲーム内時刻ベース）。reset_day は 1〜28 に制限してある。 */
    private function monthlyStart(Carbon $inGame): Carbon
    {
        $start = $inGame->copy()->startOfMonth()->addDays($this->reset_day - 1);

        if ($inGame->lt($start)) {
            $start->subMonthNoOverflow();
        }

        return $start;
    }

    public function resetLabel(): string
    {
        $dow = ['日', '月', '火', '水', '木', '金', '土'][$this->reset_dow] ?? '月';

        return "日課 {$this->reset_hour}:00 / 週課 {$dow}曜 / 月課 {$this->reset_day}日";
    }
}
