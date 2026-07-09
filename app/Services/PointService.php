<?php

namespace App\Services;

use App\Models\PointLog;
use App\Models\Title;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ポイント加算と称号の付け替えを一手に担うサービス。
 */
class PointService
{
    /** ログインボーナスの基礎ポイント */
    public const DAILY_LOGIN_POINTS = 10;

    /** 連続ログイン 1 日あたりの加算(上限あり) */
    public const STREAK_BONUS_PER_DAY = 2;
    public const STREAK_BONUS_MAX_DAYS = 7;

    /**
     * 任意の理由でポイントを加算し、履歴を残して称号を再評価する。
     */
    public function award(User $user, int $amount, string $reason, ?string $description = null): void
    {
        DB::transaction(function () use ($user, $amount, $reason, $description) {
            $user->increment('points', $amount);

            PointLog::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'reason' => $reason,
                'description' => $description,
            ]);

            $this->refreshTitle($user->refresh());
        });
    }

    /**
     * ログイン時に1日1回だけボーナスを付与する。
     * 連続ログインならストリークを伸ばし、途切れていればリセットする。
     *
     * @return int 付与した合計ポイント(既に本日付与済みなら 0)
     */
    public function awardDailyLogin(User $user): int
    {
        $today = Carbon::today();

        // 本日すでに付与済みなら何もしない
        if ($user->last_login_date && $user->last_login_date->isSameDay($today)) {
            return 0;
        }

        $continued = $user->last_login_date
            && $user->last_login_date->isSameDay($today->copy()->subDay());

        $streak = $continued ? $user->login_streak + 1 : 1;

        $streakBonus = min($streak, self::STREAK_BONUS_MAX_DAYS) * self::STREAK_BONUS_PER_DAY;
        $total = self::DAILY_LOGIN_POINTS + $streakBonus;

        DB::transaction(function () use ($user, $today, $streak, $total, $streakBonus) {
            // increment() は対象カラムしか保存しないため、まとめて save() で永続化する
            $user->forceFill([
                'login_streak' => $streak,
                'last_login_date' => $today,
                'total_logins' => $user->total_logins + 1,
                'points' => $user->points + $total,
            ])->save();

            PointLog::create([
                'user_id' => $user->id,
                'amount' => $total,
                'reason' => 'daily_login',
                'description' => "ログインボーナス(連続{$streak}日 / ストリーク+{$streakBonus})",
            ]);

            $this->refreshTitle($user->refresh());
        });

        // 🎂 誕生日ボーナス（年1回 +100pt）
        $total += $this->awardBirthdayBonus($user);

        return $total;
    }

    /** 誕生日当日の初ログインで +100pt（年1回）。付与額を返す（対象外は0）。 */
    public function awardBirthdayBonus(User $user): int
    {
        if (! $user->isBirthdayToday()) {
            return 0;
        }

        $alreadyThisYear = PointLog::where('user_id', $user->id)
            ->where('reason', 'birthday')
            ->whereYear('created_at', now()->year)
            ->exists();
        if ($alreadyThisYear) {
            return 0;
        }

        $this->award($user, 100, 'birthday', '🎂 誕生日ボーナス！おめでとう！');

        return 100;
    }

    /**
     * 現在のポイントに見合う最高位の称号を user.title_id に反映する。
     */
    public function refreshTitle(User $user): void
    {
        $title = Title::where('required_points', '<=', $user->points)
            ->orderByDesc('required_points')
            ->first();

        if ($title && $user->title_id !== $title->id) {
            $user->forceFill(['title_id' => $title->id])->save();
        }
    }
}
