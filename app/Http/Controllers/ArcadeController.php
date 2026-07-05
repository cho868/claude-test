<?php

namespace App\Http\Controllers;

use App\Models\GameScore;
use App\Models\PointLog;
use App\Models\User;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ミニゲーム（アーケード）。スコアはミリ秒＝小さいほど良い。
 */
class ArcadeController extends Controller
{
    public const GAMES = [
        'reaction' => ['icon' => '⚡', 'name' => '反射神経', 'desc' => '緑になった瞬間タップ！5回の平均タイム'],
        'numbers' => ['icon' => '🔢', 'name' => '数字タッチ', 'desc' => '1→25 を順にタップ。タイムアタック'],
    ];

    // 不正値を弾く下限/上限（ミリ秒）
    private const LIMITS = [
        'reaction' => [80, 3000],
        'numbers' => [3000, 600000],
    ];

    public function index()
    {
        $boards = [];
        foreach (self::GAMES as $key => $meta) {
            $best = GameScore::selectRaw('user_id, MIN(score) as best')
                ->where('game', $key)
                ->groupBy('user_id')
                ->orderBy('best')
                ->take(10)
                ->get();
            $users = User::whereIn('id', $best->pluck('user_id'))->get()->keyBy('id');
            $boards[$key] = $best
                ->map(fn ($r) => ['user' => $users[$r->user_id] ?? null, 'best' => (int) $r->best])
                ->filter(fn ($r) => $r['user'])
                ->values();
        }

        $myBest = GameScore::selectRaw('game, MIN(score) as best')
            ->where('user_id', auth()->id())
            ->groupBy('game')
            ->pluck('best', 'game');

        return view('arcade.index', [
            'games' => self::GAMES,
            'boards' => $boards,
            'myBest' => $myBest,
        ]);
    }

    public function store(Request $request, PointService $points)
    {
        $validated = $request->validate([
            'game' => ['required', Rule::in(array_keys(self::GAMES))],
            'score' => ['required', 'integer', 'min:1'],
        ]);

        [$min, $max] = self::LIMITS[$validated['game']];
        if ($validated['score'] < $min || $validated['score'] > $max) {
            return response()->json(['ok' => false, 'message' => 'スコアが範囲外です。'], 422);
        }

        $user = $request->user();

        $prevBest = GameScore::where('user_id', $user->id)
            ->where('game', $validated['game'])->min('score');

        GameScore::create([
            'user_id' => $user->id,
            'game' => $validated['game'],
            'score' => $validated['score'],
        ]);

        // 1ゲームにつき1日1回だけ +5pt（遊び得の防止）
        $reason = 'arcade:' . $validated['game'];
        $earned = 0;
        $alreadyToday = PointLog::where('user_id', $user->id)
            ->where('reason', $reason)
            ->whereDate('created_at', today())
            ->exists();
        if (! $alreadyToday) {
            $gameName = self::GAMES[$validated['game']]['name'];
            $points->award($user, 5, $reason, "ミニゲーム「{$gameName}」で遊んだ");
            $earned = 5;
        }

        return response()->json([
            'ok' => true,
            'isBest' => $prevBest === null || $validated['score'] < $prevBest,
            'best' => min($validated['score'], $prevBest ?? PHP_INT_MAX),
            'earned' => $earned,
        ]);
    }
}
