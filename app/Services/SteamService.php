<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Steam Web API を使ってプレイ時間を取り込む。
 *
 * 利用には .env の STEAM_API_KEY と、各ユーザーの steam_id(64bit) が必要。
 * API キーは https://steamcommunity.com/dev/apikey で取得できる。
 */
class SteamService
{
    public static function isConfigured(): bool
    {
        return ! empty(config('services.steam.key'));
    }

    private function key(): ?string
    {
        return config('services.steam.key');
    }

    /**
     * 複数ユーザーの現在のオンライン状態/プレイ中ゲームを取得（最大100件・60秒キャッシュ）。
     * 返り値: steamid => ['personaname','avatar','state'(int),'game'(?string)]
     */
    public function playerSummaries(array $steamids): array
    {
        $steamids = array_values(array_filter($steamids));
        if (! self::isConfigured() || empty($steamids)) {
            return [];
        }

        $cacheKey = 'steam:summaries:' . md5(implode(',', $steamids));

        return Cache::remember($cacheKey, 60, function () use ($steamids) {
            $out = [];
            foreach (array_chunk($steamids, 100) as $chunk) {
                try {
                    $res = Http::timeout(12)->get(
                        'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/',
                        ['key' => $this->key(), 'steamids' => implode(',', $chunk)],
                    );
                    foreach ($res->json('response.players', []) as $p) {
                        $out[$p['steamid']] = [
                            'personaname' => $p['personaname'] ?? '',
                            'avatar' => $p['avatarmedium'] ?? null,
                            'state' => (int) ($p['personastate'] ?? 0),
                            'game' => $p['gameextrainfo'] ?? null,
                        ];
                    }
                } catch (\Throwable $e) {
                    // skip
                }
            }

            return $out;
        });
    }

    /**
     * 所持ゲーム（全期間プレイ時間つき）。プロフィール公開が必要。6時間キャッシュ。
     * 返り値: appid => ['name','playtime'(分)]
     */
    public function ownedGames(string $steamid): array
    {
        if (! self::isConfigured() || empty($steamid)) {
            return [];
        }

        return Cache::remember('steam:owned:' . $steamid, 21600, function () use ($steamid) {
            try {
                $res = Http::timeout(15)->get(
                    'https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/',
                    ['key' => $this->key(), 'steamid' => $steamid, 'include_appinfo' => 1, 'include_played_free_games' => 1],
                );
                $games = [];
                foreach ($res->json('response.games', []) as $g) {
                    $games[(string) $g['appid']] = [
                        'name' => $g['name'] ?? ('AppID ' . $g['appid']),
                        'playtime' => (int) ($g['playtime_forever'] ?? 0),
                    ];
                }

                return $games;
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /**
     * 特定ゲームの実績取得状況。10分キャッシュ。
     * 返り値: ['achieved'=>int,'total'=>int,'pct'=>int] / 取得不可は null
     */
    public function achievements(string $steamid, string $appid): ?array
    {
        if (! self::isConfigured() || empty($steamid) || empty($appid)) {
            return null;
        }

        return Cache::remember("steam:ach:{$steamid}:{$appid}", 600, function () use ($steamid, $appid) {
            try {
                $res = Http::timeout(12)->get(
                    'https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v1/',
                    ['key' => $this->key(), 'steamid' => $steamid, 'appid' => $appid],
                );
                if (! $res->json('playerstats.success')) {
                    return null;
                }
                $list = $res->json('playerstats.achievements', []);
                $total = count($list);
                if ($total === 0) {
                    return null;
                }
                $achieved = collect($list)->where('achieved', 1)->count();

                return ['achieved' => $achieved, 'total' => $total, 'pct' => (int) round($achieved / $total * 100)];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * 入力(64bit ID / バニティ名 / プロフィールURL)を 64bit SteamID に解決する。
     * 解決できなければ null。
     */
    public function resolveSteamId(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // プロフィールURLから抽出
        if (preg_match('#steamcommunity\.com/profiles/(\d{17})#', $input, $m)) {
            return $m[1];
        }
        if (preg_match('#steamcommunity\.com/id/([^/?\s]+)#', $input, $m)) {
            $input = $m[1]; // バニティ名として後段で解決
        }

        // すでに 64bit ID
        if (preg_match('/^\d{17}$/', $input)) {
            return $input;
        }

        // バニティ名 → ID 解決（APIキーが必要）
        if (! self::isConfigured()) {
            return null;
        }

        try {
            $res = Http::timeout(10)->get(
                'https://api.steampowered.com/ISteamUser/ResolveVanityURL/v0001/',
                ['key' => config('services.steam.key'), 'vanityurl' => $input],
            );
            if ($res->ok() && (int) $res->json('response.success') === 1) {
                return $res->json('response.steamid');
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * 直近 2 週間のプレイ実績を取り込む。
     * 同じゲーム×日付の記録は二重登録しない。
     *
     * @return int 取り込んだ件数
     */
    public function syncRecentlyPlayed(User $user): int
    {
        $response = Http::timeout(15)->get(
            'https://api.steampowered.com/IPlayerService/GetRecentlyPlayedGames/v0001/',
            [
                'key' => config('services.steam.key'),
                'steamid' => $user->steam_id,
                'format' => 'json',
            ],
        );

        $response->throw();

        $games = $response->json('response.games', []);
        $today = Carbon::today();
        $count = 0;

        foreach ($games as $game) {
            $minutes = (int) ($game['playtime_2weeks'] ?? 0);
            if ($minutes <= 0) {
                continue;
            }

            GameSession::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'source' => 'steam',
                    'external_id' => (string) ($game['appid'] ?? ''),
                    'played_on' => $today,
                ],
                [
                    'game_name' => $game['name'] ?? ('AppID ' . ($game['appid'] ?? '?')),
                    'minutes' => $minutes,
                ],
            );
            $count++;
        }

        return $count;
    }
}
