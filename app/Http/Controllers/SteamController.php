<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SteamService;
use Illuminate\Http\Request;

class SteamController extends Controller
{
    public function __construct(private SteamService $steam) {}

    /** Steam ID を登録している身内メンバー */
    private function members()
    {
        return User::whereNotNull('steam_id')->where('steam_id', '!=', '')
            ->orderBy('name')->get(['id', 'name', 'steam_id', 'avatar_style', 'avatar_emoji', 'avatar_color', 'avatar_variant', 'avatar_seed']);
    }

    /** 2人以上が所持する共通ゲーム（appid降順の集計） */
    private function commonGames($members): array
    {
        if (! SteamService::isConfigured() || $members->count() < 2) {
            return [];
        }
        $tally = [];
        foreach ($members as $u) {
            foreach ($this->steam->ownedGames($u->steam_id) as $appid => $g) {
                $tally[$appid]['name'] = $g['name'];
                $tally[$appid]['owners'][] = $u->name;
            }
        }

        return collect($tally)
            ->map(fn ($g, $appid) => [
                'appid' => (string) $appid,
                'name' => $g['name'],
                'owners' => $g['owners'],
                'count' => count($g['owners']),
            ])
            ->filter(fn ($g) => $g['count'] >= 2)
            ->sortBy('name')
            ->sortByDesc('count')
            ->values()
            ->take(60)
            ->all();
    }

    public function index()
    {
        $members = $this->members();
        $configured = SteamService::isConfigured();

        $summaries = $configured ? $this->steam->playerSummaries($members->pluck('steam_id')->all()) : [];

        // いまプレイ中 / オンライン状態
        $now = $members->map(function ($u) use ($summaries) {
            $s = $summaries[$u->steam_id] ?? null;

            return [
                'user' => $u,
                'state' => $s['state'] ?? 0,
                'game' => $s['game'] ?? null,
                'persona' => $s['personaname'] ?? null,
            ];
        });

        // 自分の全期間プレイ時間 TOP
        $me = auth()->user();
        $myGames = [];
        if ($configured && ! empty($me->steam_id)) {
            $myGames = collect($this->steam->ownedGames($me->steam_id))
                ->map(fn ($g, $appid) => ['appid' => (string) $appid, 'name' => $g['name'], 'playtime' => $g['playtime']])
                ->filter(fn ($g) => $g['playtime'] > 0)
                ->sortByDesc('playtime')
                ->values()
                ->take(15)
                ->all();
        }

        return view('steam.index', [
            'configured' => $configured,
            'members' => $members,
            'now' => $now,
            'common' => $this->commonGames($members),
            'myGames' => $myGames,
            'hasMySteam' => ! empty($me->steam_id),
            'memberCount' => $members->count(),
        ]);
    }

    public function achievements(Request $request)
    {
        $appid = preg_replace('/\D/', '', (string) $request->query('appid', ''));
        $members = $this->members();
        $configured = SteamService::isConfigured();

        $rows = [];
        $gameName = null;
        if ($configured && $appid !== '') {
            foreach ($members as $u) {
                $a = $this->steam->achievements($u->steam_id, $appid);
                if ($a) {
                    $rows[] = ['user' => $u] + $a;
                }
                // ゲーム名を所持情報から拾う
                if (! $gameName) {
                    $owned = $this->steam->ownedGames($u->steam_id);
                    $gameName = $owned[$appid]['name'] ?? null;
                }
            }
            usort($rows, fn ($a, $b) => $b['pct'] <=> $a['pct']);
        }

        return view('steam.achievements', [
            'configured' => $configured,
            'appid' => $appid,
            'gameName' => $gameName,
            'rows' => $rows,
            'hasMembers' => $members->isNotEmpty(),
            'presets' => $this->commonGames($members),
        ]);
    }

    public function sales()
    {
        // セールはストアの公開エンドポイント（APIキー不要）
        return view('steam.sales', [
            'specials' => $this->steam->featuredSpecials(),
        ]);
    }
}
