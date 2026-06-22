<?php

namespace App\Http\Controllers;

use App\Models\MatchRecord;
use App\Services\PointService;
use Illuminate\Http\Request;

class MatchRecordController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $game = $request->query('game');

        $query = MatchRecord::where('user_id', $user->id);
        if ($game) {
            $query->where('game', $game);
        }
        $records = (clone $query)->orderByDesc('played_on')->orderByDesc('id')->take(50)->get();

        // ゲーム別の戦績
        $all = MatchRecord::where('user_id', $user->id)->get();
        $byGame = $all->groupBy('game')->map(function ($recs, $g) {
            $win = $recs->where('result', 'win')->count();
            $loss = $recs->where('result', 'loss')->count();
            $draw = $recs->where('result', 'draw')->count();
            $decided = $win + $loss;

            return [
                'game' => $g,
                'win' => $win, 'loss' => $loss, 'draw' => $draw,
                'total' => $recs->count(),
                'winrate' => $decided > 0 ? round($win / $decided * 100) : 0,
            ];
        })->sortByDesc('total')->values();

        // 全体勝率
        $win = $all->where('result', 'win')->count();
        $loss = $all->where('result', 'loss')->count();
        $overall = [
            'win' => $win,
            'loss' => $loss,
            'draw' => $all->where('result', 'draw')->count(),
            'winrate' => ($win + $loss) > 0 ? round($win / ($win + $loss) * 100) : 0,
        ];

        // 現在の連勝（直近=id降順から連続でwin）
        $streak = 0;
        foreach ($all->sortByDesc('id') as $r) {
            if ($r->result === 'win') {
                $streak++;
            } else {
                break;
            }
        }

        $games = $all->pluck('game')->unique()->sort()->values();

        return view('matches.index', compact('records', 'byGame', 'overall', 'streak', 'games', 'game'));
    }

    public function store(Request $request, PointService $points)
    {
        $data = $request->validate([
            'game' => ['required', 'string', 'max:50'],
            'result' => ['required', 'in:win,loss,draw'],
            'opponent' => ['nullable', 'string', 'max:50'],
            'score' => ['nullable', 'string', 'max:20'],
            'played_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->matchRecords()->create($data);
        $points->award($request->user(), 2, 'match_log', '戦績記録');

        return back()->with('status', '戦績を記録しました(+2pt)');
    }

    public function destroy(MatchRecord $match)
    {
        abort_unless($match->user_id === auth()->id(), 403);
        $match->delete();

        return back()->with('status', '記録を削除しました。');
    }
}
