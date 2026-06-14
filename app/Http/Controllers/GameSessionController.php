<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Services\PointService;
use App\Services\SteamService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GameSessionController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $sessions = $user->gameSessions()
            ->orderByDesc('played_on')
            ->take(50)
            ->get();

        // ゲーム別の合計プレイ時間ランキング
        $byGame = $user->gameSessions()
            ->selectRaw('game_name, SUM(minutes) as total')
            ->groupBy('game_name')
            ->orderByDesc('total')
            ->get();

        $totalMinutes = (int) $user->gameSessions()->sum('minutes');

        // 身内全体のゲーム時間ランキング(今月)
        $monthlyRanking = GameSession::selectRaw('user_id, SUM(minutes) as total')
            ->where('played_on', '>=', Carbon::now()->startOfMonth())
            ->groupBy('user_id')
            ->with('user')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        return view('games.index', [
            'sessions' => $sessions,
            'byGame' => $byGame,
            'totalMinutes' => $totalMinutes,
            'monthlyRanking' => $monthlyRanking,
            'steamConfigured' => SteamService::isConfigured(),
        ]);
    }

    public function store(Request $request, PointService $points)
    {
        $validated = $request->validate([
            'game_name' => ['required', 'string', 'max:255'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'played_on' => ['required', 'date'],
        ]);

        $request->user()->gameSessions()->create($validated + ['source' => 'manual']);

        $points->award($request->user(), 3, 'game_log', 'ゲーム時間記録');

        return back()->with('status', 'プレイ時間を記録しました(+3pt)');
    }

    /**
     * Steam の所持ゲーム / プレイ時間を取り込む(STEAM_API_KEY と steam_id が必要)。
     */
    public function syncSteam(Request $request, SteamService $steam)
    {
        $user = $request->user();

        if (! SteamService::isConfigured()) {
            return back()->withErrors(['steam' => 'Steam API キー(STEAM_API_KEY)が未設定です。.env を設定してください。']);
        }

        if (! $user->steam_id) {
            return back()->withErrors(['steam' => 'プロフィールに Steam ID を登録してください。']);
        }

        try {
            $imported = $steam->syncRecentlyPlayed($user);
        } catch (\Throwable $e) {
            return back()->withErrors(['steam' => 'Steam 連携に失敗しました: ' . $e->getMessage()]);
        }

        return back()->with('status', "Steam から {$imported} 件のプレイ記録を取り込みました。");
    }

    public function destroy(GameSession $game)
    {
        abort_unless($game->user_id === auth()->id(), 403);
        $game->delete();

        return back()->with('status', '記録を削除しました。');
    }
}
