<?php

namespace App\Http\Controllers;

use App\Models\GameRoutine;
use App\Models\RoutineCompletion;
use App\Models\RoutineTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SocialGameController extends Controller
{
    public function index()
    {
        $games = GameRoutine::with('tasks')
            ->where('user_id', auth()->id())
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        // 現在の3つの期間キーに該当する完了をまとめて取得
        $now = Carbon::now();
        $keys = [$now->format('Y-m-d'), $now->isoFormat('GGGG-[W]WW'), $now->format('Y-m')];
        $taskIds = $games->flatMap->tasks->pluck('id');

        $done = RoutineCompletion::whereIn('routine_task_id', $taskIds)
            ->whereIn('period_key', $keys)
            ->get()
            ->map(fn ($c) => $c->routine_task_id . '|' . $c->period_key)
            ->flip();

        return view('social.index', compact('games', 'done'));
    }

    public function storeGame(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $request->user()->gameRoutines()->create($data);

        return back()->with('status', 'ゲームを追加しました。');
    }

    public function destroyGame(GameRoutine $game)
    {
        $this->authorizeGame($game);
        $game->delete();

        return back()->with('status', '削除しました。');
    }

    public function storeTask(Request $request, GameRoutine $game)
    {
        $this->authorizeGame($game);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'cadence' => ['required', 'in:daily,weekly,monthly'],
        ]);
        $game->tasks()->create($data);

        return back()->with('status', '課題を追加しました。');
    }

    public function destroyTask(RoutineTask $task)
    {
        $this->authorizeTask($task);
        $task->delete();

        return back()->with('status', '削除しました。');
    }

    public function toggle(RoutineTask $task)
    {
        $this->authorizeTask($task);
        $key = $task->currentPeriodKey();

        $existing = RoutineCompletion::where('routine_task_id', $task->id)
            ->where('period_key', $key)->first();

        if ($existing) {
            $existing->delete();
        } else {
            RoutineCompletion::create([
                'routine_task_id' => $task->id,
                'user_id' => auth()->id(),
                'period_key' => $key,
            ]);
        }

        return back();
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
