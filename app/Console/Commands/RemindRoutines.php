<?php

namespace App\Console\Commands;

use App\Models\GameRoutine;
use App\Models\RoutineCompletion;
use App\Models\Task;
use App\Models\User;
use App\Services\DiscordWebhook;
use App\Services\PushService;
use App\Services\SetupStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

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

    protected $description = 'ソシャゲの未完了をリセット前に、タスクは指定時刻に（Push/Discord）通知する';

    public function handle(PushService $push, DiscordWebhook $discord): int
    {
        $now = Carbon::now();
        $sentTotal = 0;

        // Push は VAPID 鍵が無いと送れないが、Discord のまとめはそれとは関係なく送る
        if ($push->isConfigured() || $this->option('dry-run')) {
            $sentTotal += $this->remindRoutines($push, $now);
        } else {
            $this->warn('VAPID鍵が未設定のため Push は送りません（`php artisan push:vapid` で生成して .env に設定）。');
        }

        $sentTotal += $this->remindTasks($push, $discord, $now);

        if (! $this->option('dry-run')) {
            $this->stamp($now, $sentTotal);
            $this->info("送信完了: {$sentTotal}件");
        }

        return self::SUCCESS;
    }

    /** ソシャゲ日課: リセットのN時間前に未完了があれば Push */
    private function remindRoutines(PushService $push, Carbon $now): int
    {
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

        return $sentTotal;
    }

    /**
     * タスクの通知。本人が決めた時刻（task_notify_hour）の回にだけ動く。
     *  - Push: 今日が期限のもの・期限切れのものが残っている日だけ1回鳴らす
     *  - Discord: Webhook を登録していれば、未完了がある限り毎日まとめを送る
     * 日課と同じく「実行時刻」で重複を避けるので送信履歴は残さない。
     */
    private function remindTasks(PushService $push, DiscordWebhook $discord, Carbon $now): int
    {
        $users = User::query()
            ->when(! $this->option('force'), fn ($q) => $q->where('task_notify_hour', $now->hour))
            ->when($this->option('force'), fn ($q) => $q->whereNotNull('task_notify_hour'))
            ->when($this->option('user'), fn ($q, $name) => $q->where('username', $name))
            ->whereHas('tasks', fn ($q) => $q->whereNull('done_at'))
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            $open = Task::where('user_id', $user->id)->whereNull('done_at')->ordered()->get();

            if ($user->discord_webhook_url) {
                $content = self::taskDigest($open, $now);

                if ($this->option('dry-run')) {
                    $this->line("[{$user->username}] Discord:\n{$content}");
                } elseif ($discord->send($user->discord_webhook_url, $content)) {
                    $sent++;
                    $this->line("[{$user->username}] Discord にまとめを送信");
                } else {
                    $this->warn("[{$user->username}] Discord への送信に失敗（Webhook が消されていないか確認）");
                }
            }

            if ($push->isConfigured() || $this->option('dry-run')) {
                $sent += $this->pushDueTasks($push, $user, $open, $now);
            }
        }

        return $sent;
    }

    /** 今日が期限・期限切れがあれば Push を1回（無ければ黙る） */
    private function pushDueTasks(PushService $push, User $user, Collection $open, Carbon $now): int
    {
        $tasks = $open->filter(fn (Task $t) => $t->due_date && $t->due_date->lte($now->copy()->startOfDay()));

        if ($tasks->isEmpty()) {
            return 0;
        }

        $today = $tasks->filter(fn (Task $t) => $t->due_date->isSameDay($now));
        $overdue = $tasks->count() - $today->count();

        $parts = $today->take(3)->pluck('title')->all();
        if ($today->count() > 3) {
            $parts[] = '他'.($today->count() - 3).'件';
        }
        if ($overdue > 0) {
            $parts[] = "期限切れ{$overdue}件";
        }

        $payload = [
            'title' => $today->isNotEmpty() ? "✅ 今日が期限のタスク {$today->count()}件" : '🔥 期限切れのタスクがあります',
            'body' => implode(' / ', $parts),
            'url' => route('tasks.index'),
            'tag' => 'task-remind',
        ];

        if ($this->option('dry-run')) {
            $this->line("[{$user->username}] {$payload['title']} — {$payload['body']}");

            return 0;
        }

        $count = $push->sendToUser($user, $payload);
        $this->line("[{$user->username}] タスク {$count}端末に送信: {$payload['body']}");

        return $count;
    }

    /**
     * Discord に送る毎日のまとめ。期限切れ → 今日 → 1週間以内 を並べ、先の予定と期限なしは件数だけ。
     *
     * @param  Collection<int, Task>  $open  未完了タスク（期限順）
     */
    public static function taskDigest(Collection $open, Carbon $now): string
    {
        $today = $now->copy()->startOfDay();
        $groups = $open->groupBy(fn (Task $t) => $t->bucket($today));

        $lines = ['**✅ '.$now->isoFormat('M/D(ddd)').' のタスク**（残り'.$open->count().'件）'];

        foreach (['overdue' => '🔥 期限切れ', 'today' => '📌 今日まで', 'week' => '📅 1週間以内'] as $key => $label) {
            if (! $groups->has($key)) {
                continue;
            }
            $lines[] = '';
            $lines[] = "__{$label}__";
            foreach ($groups[$key]->take(10) as $task) {
                $list = $task->list ? "［{$task->list}］" : '';
                $lines[] = "・{$task->title} {$list}（{$task->dueLabel($today)}）";
            }
            if ($groups[$key]->count() > 10) {
                $lines[] = '・他'.($groups[$key]->count() - 10).'件';
            }
        }

        $later = $groups->get('later', collect())->count();
        $none = $groups->get('none', collect())->count();
        if ($later || $none) {
            $lines[] = '';
            $lines[] = trim(($later ? "🗓 それ以降 {$later}件　" : '').($none ? "📝 期限なし {$none}件" : ''));
        }

        $lines[] = '';
        $lines[] = route('tasks.index');

        return implode("\n", $lines);
    }

    /**
     * 最終実行を1ファイルに記録する（管理画面の「セットアップ状況」がこれを見て
     * cron が生きているか判定する）。毎時1回・数十バイトの上書きなので、
     * SDへの負担はセッションやログに比べれば誤差の範囲。
     */
    private function stamp(Carbon $now, int $sent): void
    {
        File::put(
            storage_path('app/'.SetupStatus::REMIND_STAMP),
            json_encode(['at' => $now->toIso8601String(), 'sent' => $sent], JSON_UNESCAPED_UNICODE)
        );
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
