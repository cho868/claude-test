<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Challenge extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'metric', 'starts_on', 'ends_on'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function metricLabel(): string
    {
        return $this->metric === 'exercise_minutes' ? '運動時間(合計)' : '減量率';
    }

    public function status(): string
    {
        $today = Carbon::today();
        if ($today->lt($this->starts_on)) {
            return 'upcoming';
        }
        if ($today->gt($this->ends_on)) {
            return 'finished';
        }

        return 'active';
    }

    /**
     * 参加者ごとの成績を計算してランキング順に返す。
     *
     * @return Collection<int, array{user: User, value: float, detail: string}>
     */
    public function standings(): Collection
    {
        $this->loadMissing('participants');

        return $this->participants->map(function (User $u) {
            return $this->metric === 'exercise_minutes'
                ? $this->exerciseStanding($u)
                : $this->weightStanding($u);
        })->sortByDesc('value')->values();
    }

    private function weightStanding(User $u): array
    {
        $records = WeightRecord::where('user_id', $u->id)
            ->whereBetween('recorded_on', [$this->starts_on, $this->ends_on])
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'weight_kg']);

        if ($records->count() < 2) {
            return ['user' => $u, 'value' => 0.0, 'detail' => '記録不足(2日以上必要)'];
        }

        $first = (float) $records->first()->weight_kg;
        $last = (float) $records->last()->weight_kg;
        $pct = $first > 0 ? ($first - $last) / $first * 100 : 0;

        return [
            'user' => $u,
            'value' => round($pct, 2),
            'detail' => sprintf('%.1f → %.1fkg（%+.1f%%）', $first, $last, $pct),
        ];
    }

    private function exerciseStanding(User $u): array
    {
        $minutes = (int) ExerciseRecord::where('user_id', $u->id)
            ->whereBetween('recorded_on', [$this->starts_on, $this->ends_on])
            ->sum('minutes');

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return [
            'user' => $u,
            'value' => (float) $minutes,
            'detail' => "{$h}時間{$m}分（{$minutes}分）",
        ];
    }
}
