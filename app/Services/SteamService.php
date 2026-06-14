<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Carbon;
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
