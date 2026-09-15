<?php

namespace App\Http\Controllers;

use App\Models\GameRoutine;
use App\Models\RoutineCompletion;
use App\Models\RoutineTask;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SocialGameController extends Controller
{
    /**
     * よくある日課セットのテンプレ。ゲーム追加時にワンタップで入る。
     * リセット時刻は [hour, dow(0=日), day] の順。
     */
    public const TEMPLATES = [
        'genshin' => ['name' => '原神', 'icon' => '⚔️', 'reset' => [5, 1, 1], 'tasks' => [
            ['デイリー依頼4つ', 'daily'], ['樹脂を消化', 'daily'], ['採集/鉱石', 'daily'],
            ['週ボス3体', 'weekly'], ['依頼ポイント報酬', 'weekly'],
        ]],
        'fgo' => ['name' => 'FGO', 'icon' => '🔮', 'reset' => [4, 1, 1], 'tasks' => [
            ['ログイン', 'daily'], ['マスターミッション消化', 'daily'], ['種火/修練場', 'daily'],
            ['ウィークリーミッション', 'weekly'],
        ]],
        'uma' => ['name' => 'ウマ娘', 'icon' => '🐎', 'reset' => [5, 1, 1], 'tasks' => [
            ['デイリーレース', 'daily'], ['サークル応援', 'daily'], ['TP消化', 'daily'],
            ['チャンピオンズミーティング確認', 'weekly'],
        ]],
        'proseka' => ['name' => 'プロセカ', 'icon' => '🎵', 'reset' => [4, 1, 1], 'tasks' => [
            ['デイリーミッション', 'daily'], ['ライブボーナス消化', 'daily'],
            ['ウィークリーミッション', 'weekly'],
        ]],
        'blank' => ['name' => '新しいゲーム', 'icon' => '🎮', 'reset' => [5, 1, 1], 'tasks' => [
            ['ログインボーナス', 'daily'], ['デイリーミッション', 'daily'], ['スタミナ消化', 'daily'],
            ['週課ミッション', 'weekly'], ['月課ミッション', 'monthly'],
        ]],
    ];

    public function index(PushService $push)
    {
        $user = auth()->user();

        return view('social.index', [
            'games' => $this->snapshot($user->id),
            'templates' => collect(self::TEMPLATES)->map(fn ($t, $k) => [
                'key' => $k,
                'name' => $t['name'],
                'icon' => $t['icon'],
                'count' => count($t['tasks']),
            ])->values(),
            'pushPublicKey' => $push->publicKey(),
            'pushConfigured' => $push->isConfigured(),
            'subscribedCount' => $user->pushSubscriptions()->count(),
        ]);
    }

    /** 画面がそのまま使える形の全データ。Alpine 側はこれを持って描画する。 */
    private function snapshot(int $userId): array
    {
        $games = GameRoutine::with('tasks')
            ->where('user_id', $userId)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $now = Carbon::now();

        // 現在進行中の期間キーをまとめて取得（ゲームごとにリセット時刻が違うので個別に組む）
        $keys = [];
        foreach ($games as $game) {
            foreach ($game->tasks as $task) {
                $keys[] = $game->periodKey($task->cadence, $now);
            }
        }

        $done = RoutineCompletion::where('user_id', $userId)
            ->whereIn('period_key', array_unique($keys) ?: ['-'])
            ->get()
            ->map(fn ($c) => $c->routine_task_id.'|'.$c->period_key)
            ->flip();

        return $games->map(function (GameRoutine $game) use ($done, $now) {
            return [
                'id' => $game->id,
                'name' => $game->name,
                'icon' => $game->icon ?: '🎮',
                'reset_hour' => $game->reset_hour,
                'reset_dow' => $game->reset_dow,
                'reset_day' => $game->reset_day,
                'notify' => $game->notify,
                'next_reset' => [
                    'daily' => $game->nextResetAt('daily', $now)->toIso8601String(),
                    'weekly' => $game->nextResetAt('weekly', $now)->toIso8601String(),
                    'monthly' => $game->nextResetAt('monthly', $now)->toIso8601String(),
                ],
                'tasks' => $game->tasks->map(fn (RoutineTask $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'cadence' => $task->cadence,
                    'done' => $done->has($task->id.'|'.$game->periodKey($task->cadence, $now)),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    public function storeGame(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', 'max:8'],
            'template' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::TEMPLATES))],
        ]);

        $template = isset($data['template']) ? self::TEMPLATES[$data['template']] : null;

        $game = $request->user()->gameRoutines()->create([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? ($template['icon'] ?? '🎮'),
            'reset_hour' => $template['reset'][0] ?? 5,
            'reset_dow' => $template['reset'][1] ?? 1,
            'reset_day' => $template['reset'][2] ?? 1,
            'sort_order' => (int) $request->user()->gameRoutines()->max('sort_order') + 1,
        ]);

        if ($template) {
            foreach ($template['tasks'] as $i => [$title, $cadence]) {
                $game->tasks()->create(['title' => $title, 'cadence' => $cadence, 'sort_order' => $i]);
            }
        }

        return $this->respond($request, 'ゲームを追加しました。');
    }

    public function updateGame(Request $request, GameRoutine $game)
    {
        $this->authorizeGame($game);

        $game->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:8'],
            'reset_hour' => ['sometimes', 'integer', 'between:0,23'],
            'reset_dow' => ['sometimes', 'integer', 'between:0,6'],
            'reset_day' => ['sometimes', 'integer', 'between:1,28'],
            'notify' => ['sometimes', 'boolean'],
        ]));

        return $this->respond($request, '設定を保存しました。');
    }

    public function destroyGame(Request $request, GameRoutine $game)
    {
        $this->authorizeGame($game);
        $game->delete();

        return $this->respond($request, '削除しました。');
    }

    public function storeTask(Request $request, GameRoutine $game)
    {
        $this->authorizeGame($game);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'cadence' => ['required', 'in:daily,weekly,monthly'],
        ]);
        $data['sort_order'] = (int) $game->tasks()->max('sort_order') + 1;
        $game->tasks()->create($data);

        return $this->respond($request, '課題を追加しました。');
    }

    public function destroyTask(Request $request, RoutineTask $task)
    {
        $this->authorizeTask($task);
        $task->delete();

        return $this->respond($request, '削除しました。');
    }

    /**
     * チェックの付け外し。
     * 楽観的UIから呼ばれるので、送られてきた done の状態に「合わせる」冪等な作りにする
     * （連打や再送で裏返ってしまわないように）。
     */
    public function toggle(Request $request, RoutineTask $task): JsonResponse
    {
        $this->authorizeTask($task);

        $key = $task->currentPeriodKey();
        $existing = RoutineCompletion::where('routine_task_id', $task->id)
            ->where('period_key', $key)->first();

        $want = $request->has('done')
            ? $request->boolean('done')
            : ! $existing;   // 指定が無ければ従来どおりトグル

        if ($want && ! $existing) {
            RoutineCompletion::create([
                'routine_task_id' => $task->id,
                'user_id' => auth()->id(),
                'period_key' => $key,
            ]);
        } elseif (! $want && $existing) {
            $existing->delete();
        }

        return response()->json(['done' => $want, 'period_key' => $key]);
    }

    /** 並べ替え（ゲーム・課題どちらも）。DnD の結果をまとめて1回だけ保存する。 */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'games' => ['array'],
            'games.*' => ['integer'],
            'tasks' => ['array'],
            'tasks.*' => ['integer'],
        ]);

        foreach ($data['games'] ?? [] as $i => $id) {
            GameRoutine::where('id', $id)->where('user_id', auth()->id())->update(['sort_order' => $i]);
        }

        if ($ids = $data['tasks'] ?? []) {
            $owned = RoutineTask::whereIn('id', $ids)
                ->whereHas('routine', fn ($q) => $q->where('user_id', auth()->id()))
                ->pluck('id')->flip();

            foreach ($ids as $i => $id) {
                if ($owned->has($id)) {
                    RoutineTask::where('id', $id)->update(['sort_order' => $i]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /** 通知の好み（何時間前に鳴らすか・そもそも鳴らすか）。 */
    public function updateNotify(Request $request)
    {
        $request->user()->update($request->validate([
            'routine_notify' => ['required', 'boolean'],
            'routine_notify_before' => ['required', 'integer', 'between:1,12'],
        ]));

        return $this->respond($request, '通知設定を保存しました。');
    }

    /** JSON で来たら最新スナップショットを返し、通常のフォーム送信ならリダイレクト。 */
    private function respond(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'games' => $this->snapshot(auth()->id()),
            ]);
        }

        return back()->with('status', $message);
    }

    private function authorizeGame(GameRoutine $game): void
    {
        abort_unless($game->user_id === auth()->id(), 403);
    }

    private function authorizeTask(RoutineTask $task): void
    {
        abort_unless($task->routine->user_id === auth()->id(), 403);
    }
}
