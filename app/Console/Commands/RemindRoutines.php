<?php

namespace App\Console\Commands;

use App\Models\GameRoutine;
use App\Models\RoutineCompletion;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * ソシャゲ日課のリマインド。cron から毎時0分に叩く想定。
 *
 *   0 * * * * cd /var/www/portal && php artisan routines:remind >/dev/null 2>&1
 *
 * 「リセットのN時間前」にちょうど当たった時だけ鳴らす。
 * 実行時刻そのものが重複送信よけになるので、送信履歴をDBに残さない
 * （＝SDへの書き込みゼロ）。全部終わっていれば黙る。
 */
class RemindRoutines extends Command
{
    protected $signature = 'routines:remind
                            {--dry-run : 送信せずに内容だけ表示する}
                            {--force : 残り時間に関係なく未完了があれば送る（動作確認用）}
                            {--user= : 対象を1人に絞る（ログインID）}';

    protected $description = 'ソシャゲの日課/週課/月課で未完了があればリセット前に通知する';

    public function handle(PushService $push): int
    {
        if (! $push->isConfigured() && ! $this->option('dry-run')) {
            $this->error('VAPID鍵が未設定です。`php artisan push:vapid` で生成して .env に設定してください。');

            return self::FAILURE;
        }

        $now = Carbon::now();

        $users = User::query()
            ->where('routine_notify', true)
            ->when($this->option('user'), fn ($q, $name) => $q->where('username', $name))
            ->whereHas('gameRoutines')
            ->get();

        $sentTotal = 0;

        foreach ($users as $user) {
            $lines = $this->pendingLines($user, $now);

            if (! $lines) {
                continue;
            }

            $payload = [
                'title' => $this->option('force')
                    ? '⏰ ソシャゲの未完了'
                    : "⏰ あと{$user->routine_notify_before}時間でリセット",
                'body' => implode(' / ', $lines),
                'url' => route('social.index'),
                'tag' => 'routine-remind',
            ];

            if ($this->option('dry-run')) {
                $this->line("[{$user->username}] {$payload['title']} — {$payload['body']}");

                continue;
            }

            $sent = $push->sendToUser($user, $payload);
            $sentTotal += $sent;
            $this->line("[{$user->username}] {$sent}端末に送信: {$payload['body']}");
        }

        if (! $this->option('dry-run')) {
            $this->info("送信完了: {$sentTotal}件");
        }

        return self::SUCCESS;
    }

    /**
     * 「原神: 日課3件」のような行を組み立てる。通知すべきものが無ければ空配列。
     *
     * @return list<string>
     */
    private function pendingLines(User $user, Carbon $now): array
    {
        $games = GameRoutine::with('tasks')
            ->where('user_id', $user->id)
            ->where('notify', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        // 判定に必要な期間キーをまとめて引く（N+1回避）
        $keys = [];
        foreach ($games as $game) {
            foreach ($game->tasks as $task) {
                $keys[] = $game->periodKey($task->cadence, $now);
            }
        }

        $done = RoutineCompletion::where('user_id', $user->id)
            ->whereIn('period_key', array_unique($keys))
            ->get()
            ->map(fn ($c) => $c->routine_task_id.'|'.$c->period_key)
            ->flip();

        $lines = [];

        foreach ($games as $game) {
            $parts = [];

            foreach (['daily' => '日課', 'weekly' => '週課', 'monthly' => '月課'] as $cadence => $label) {
                $tasks = $game->tasks->where('cadence', $cadence);

                if ($tasks->isEmpty()) {
                    continue;
                }

                // リセットのN時間前ちょうどか？（毎時実行なので誤差に強い round を使う）
                $hoursLeft = (int) round($now->diffInMinutes($game->nextResetAt($cadence, $now), false) / 60);

                if (! $this->option('force') && $hoursLeft !== (int) $user->routine_notify_before) {
                    continue;
                }

                $key = $game->periodKey($cadence, $now);
                $remaining = $tasks->reject(fn ($task) => $done->has($task->id.'|'.$key))->count();

                if ($remaining > 0) {
                    $parts[] = "{$label}{$remaining}";
                }
            }

            if ($parts) {
                $lines[] = $game->name.': '.implode('/', $parts);
            }
        }

        // 通知本文が長くなりすぎないように丸める
        if (count($lines) > 4) {
            $rest = count($lines) - 4;
            $lines = array_slice($lines, 0, 4);
            $lines[] = "他{$rest}件";
        }

        return $lines;
    }
}
