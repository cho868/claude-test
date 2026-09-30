<?php

namespace App\Http\Controllers;

use App\Console\Commands\RemindRoutines;
use App\Models\Task;
use App\Services\DiscordWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TaskController extends Controller
{
    /** 見出しの順番と表示名 */
    public const BUCKETS = [
        'overdue' => ['🔥', '期限切れ'],
        'today' => ['📌', '今日まで'],
        'week' => ['📅', '1週間以内'],
        'later' => ['🗓', 'それ以降'],
        'none' => ['📝', '期限なし'],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();
        $tab = $request->query('tab') === 'shared' ? 'shared' : 'mine';
        $list = $request->query('list');

        // 共有タブは他の人が共有した分（本人のは「自分」タブで見られる）
        $base = $tab === 'shared'
            ? Task::with('user')->where('visibility', 'members')->where('user_id', '!=', $user->id)
            : Task::where('user_id', $user->id);

        $open = (clone $base)->whereNull('done_at')
            ->when($list, fn ($q) => $q->where('list', $list))
            ->ordered()->get();

        $done = (clone $base)->whereNotNull('done_at')
            ->when($list, fn ($q) => $q->where('list', $list))
            ->latest('done_at')->take(30)->get();

        return view('tasks.index', [
            'tab' => $tab,
            'list' => $list,
            'today' => $today,
            'buckets' => $open->groupBy(fn (Task $t) => $t->bucket($today)),
            'openCount' => $open->count(),
            'done' => $done,
            'doneTotal' => (clone $base)->whereNotNull('done_at')->count(),
            'lists' => (clone $base)->whereNotNull('list')->distinct()->orderBy('list')->pluck('list'),
            'myLists' => Task::where('user_id', $user->id)->whereNotNull('list')->distinct()->orderBy('list')->pluck('list'),
        ]);
    }

    public function store(Request $request)
    {
        $request->user()->tasks()->create($this->validated($request));

        return back()->with('status', 'タスクを追加しました。');
    }

    /** まとめて貼り付け。1行=1タスク、先頭の日付が期限になる（書式は Task::parseBulk）。 */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:10000'],
            'list' => ['nullable', 'string', 'max:40'],
            'visibility' => ['required', 'in:private,members'],
        ]);

        $items = Task::parseBulk($data['text'], $data['list'] ?? null);

        if (! $items) {
            return back()->withErrors(['text' => 'タスクとして読み取れる行がありませんでした。'])->withInput();
        }
        if (count($items) > 100) {
            return back()->withErrors(['text' => '一度に追加できるのは100件までです。'])->withInput();
        }

        foreach ($items as $item) {
            $request->user()->tasks()->create($item + ['visibility' => $data['visibility']]);
        }

        return redirect()->route('tasks.index')->with('status', count($items).'件のタスクを追加しました。');
    }

    public function update(Request $request, Task $task)
    {
        abort_unless($task->canBeEditedBy($request->user()), 403);
        $task->update($this->validated($request));

        return back()->with('status', '保存しました。');
    }

    /**
     * 完了の付け外し。楽観的UIから呼ばれるので、送られてきた done に「合わせる」冪等な作り
     * （連打や再送で裏返らないように）。
     */
    public function toggle(Request $request, Task $task): JsonResponse
    {
        abort_unless($task->canBeEditedBy($request->user()), 403);

        $want = $request->has('done') ? $request->boolean('done') : ! $task->isDone();

        if ($want !== $task->isDone()) {
            $task->update(['done_at' => $want ? now() : null]);
        }

        return response()->json(['done' => $want]);
    }

    public function destroy(Request $request, Task $task)
    {
        abort_unless($task->canBeEditedBy($request->user()), 403);
        $task->delete();

        return back()->with('status', '削除しました。');
    }

    /** 完了済みをまとめて片付ける（リスト指定があればそのリストだけ） */
    public function clearDone(Request $request)
    {
        $count = $request->user()->tasks()->whereNotNull('done_at')
            ->when($request->input('list'), fn ($q, $list) => $q->where('list', $list))
            ->delete();

        return back()->with('status', "完了済みを{$count}件片付けました。");
    }

    /** 通知の設定（何時に送るか / Discord Webhook） */
    public function settings(Request $request)
    {
        $data = $request->validate([
            'task_notify_hour' => ['nullable', 'integer', 'between:0,23'],
            // 宛先を Discord に固定する（SSRF対策。任意のURLにはサーバーから投稿させない）
            'discord_webhook_url' => ['nullable', 'string', 'max:300', function ($attr, $value, $fail) {
                if (! DiscordWebhook::isValidUrl($value)) {
                    $fail('Discord の Webhook URL（https://discord.com/api/webhooks/…）を貼り付けてください。');
                }
            }],
            'discord_clear' => ['nullable', 'boolean'],
        ]);

        $update = ['task_notify_hour' => $data['task_notify_hour'] ?? null];

        // 空欄なら今の設定を保つ（URLは画面に出さないので、毎回入れ直させない）
        if ($request->boolean('discord_clear')) {
            $update['discord_webhook_url'] = null;
        } elseif (filled($data['discord_webhook_url'] ?? null)) {
            $update['discord_webhook_url'] = trim($data['discord_webhook_url']);
        }

        $request->user()->update($update);

        return back()->with('status', '通知設定を保存しました。');
    }

    /** Discord にいまのまとめを試しに送る */
    public function discordTest(Request $request, DiscordWebhook $discord)
    {
        $user = $request->user();

        if (! $user->discord_webhook_url) {
            return back()->withErrors(['discord_webhook_url' => '先に Webhook URL を保存してください。']);
        }

        $open = $user->tasks()->whereNull('done_at')->ordered()->get();
        $content = $open->isEmpty()
            ? '✅ 身内ポータルのタスク通知のテストです（いま未完了のタスクはありません）。'
            : RemindRoutines::taskDigest($open, now());

        return $discord->send($user->discord_webhook_url, $content)
            ? back()->with('status', 'Discord に送信しました。')
            : back()->withErrors(['discord_webhook_url' => 'Discord に送れませんでした。Webhook が削除されていないか確認してください。']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'list' => ['nullable', 'string', 'max:40'],
            'visibility' => ['required', 'in:private,members'],
        ]);
        $data['list'] = filled($data['list'] ?? null) ? trim($data['list']) : null;

        return $data;
    }
}
