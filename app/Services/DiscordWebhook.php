<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Discord Webhook への送信役（タスクの毎日のまとめ用）。
 *
 * ユーザー入力のURLにサーバーから投稿するので SSRF の入口になり得る。
 * ここでは宛先が Discord と決まっているため、SafeUrl より強い
 * 「ホストを Discord の Webhook に固定＋リダイレクトを追わない」で守る。
 */
class DiscordWebhook
{
    private const PATTERN = '#^https://(?:(?:ptb|canary)\.)?(?:discord|discordapp)\.com/api/webhooks/\d+/[\w-]+$#';

    /** Discord の1メッセージの上限 */
    private const MAX_LENGTH = 2000;

    public static function isValidUrl(?string $url): bool
    {
        return is_string($url) && preg_match(self::PATTERN, trim($url)) === 1;
    }

    /** 送信できたら true。URLが不正・失敗時は false（例外は投げない） */
    public function send(string $url, string $content): bool
    {
        if (! self::isValidUrl($url)) {
            return false;
        }

        try {
            return Http::timeout(5)
                ->withoutRedirecting()
                ->post(trim($url), [
                    'content' => Str::limit($content, self::MAX_LENGTH - 10),
                    // @everyone などが混ざっていても誰も呼ばない
                    'allowed_mentions' => ['parse' => []],
                ])
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
