<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BotController extends Controller
{
    /** 機能トグル（キー => 説明） */
    public const FEATURES = [
        'homecoming' => '帰宅 → おかえり',
        'youtubeReaction' => 'YouTube URL に ❤️',
        'attachmentReaction' => '添付に ❤️',
        'games' => 'DOAXVV/ブレードストレンジャーズ紹介',
        'tabelog' => '食べログ自動仕分け',
        'onsen' => '温泉URL収集（Puppeteer）',
        'coin' => 'コイントス',
        'voiceNotify' => '通話開始/終了通知',
        'gas' => 'LINE/GAS連携',
    ];

    /** メッセージ（キー => 説明） */
    public const MESSAGES = [
        'wakeup' => 'お目覚めメッセージ',
        'vcOpen' => '通話開演アナウンス',
        'unknownCommand' => '未知コマンドの返答',
    ];

    public const ACTIVITY_TYPES = ['PLAYING', 'STREAMING', 'LISTENING', 'WATCHING', 'COMPETING'];

    public function index()
    {
        $cfg = config('services.discord_bot');

        if (empty($cfg['key'])) {
            return view('admin.bot', [
                'configured' => false, 'settings' => null, 'error' => null,
                'features' => self::FEATURES, 'messages' => self::MESSAGES, 'types' => self::ACTIVITY_TYPES,
            ]);
        }

        $settings = null;
        $error = null;
        try {
            $res = Http::withHeaders(['x-admin-key' => $cfg['key']])
                ->timeout(8)
                ->get(rtrim($cfg['url'], '/') . '/admin/settings');
            $res->throw();
            $settings = $res->json();
        } catch (\Throwable $e) {
            $error = $this->friendlyError($e);
        }

        return view('admin.bot', [
            'configured' => true, 'settings' => $settings, 'error' => $error,
            'features' => self::FEATURES, 'messages' => self::MESSAGES, 'types' => self::ACTIVITY_TYPES,
        ]);
    }

    public function update(Request $request)
    {
        $cfg = config('services.discord_bot');
        if (empty($cfg['key'])) {
            return back()->withErrors(['bot' => 'BOT_ADMIN_KEY が未設定です。サーバーの .env に設定してください。']);
        }

        $data = $request->validate([
            'activity_name' => ['nullable', 'string', 'max:128'],
            'activity_type' => ['required', 'in:' . implode(',', self::ACTIVITY_TYPES)],
            'messages' => ['array'],
            'messages.*' => ['nullable', 'string', 'max:2000'],
        ]);

        // 機能トグルは未チェック=false を明示するため全キーを組み立てる
        $features = [];
        foreach (array_keys(self::FEATURES) as $key) {
            $features[$key] = $request->boolean("features.{$key}");
        }

        // 既知のメッセージキーのみ送る
        $messages = [];
        foreach (array_keys(self::MESSAGES) as $key) {
            $messages[$key] = (string) ($data['messages'][$key] ?? '');
        }

        $payload = [
            'activity' => [
                'name' => $data['activity_name'] ?? '',
                'type' => $data['activity_type'],
            ],
            'features' => $features,
            'messages' => $messages,
        ];

        try {
            $res = Http::withHeaders(['x-admin-key' => $cfg['key']])
                ->timeout(8)
                ->post(rtrim($cfg['url'], '/') . '/admin/settings', $payload);
            $res->throw();
        } catch (\Throwable $e) {
            return back()->withErrors(['bot' => 'Bot への保存に失敗しました: ' . $this->friendlyError($e)]);
        }

        return back()->with('status', 'Bot の設定を更新しました（ステータスは即時、機能/文面は次のイベントから反映）。');
    }

    private function friendlyError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'cURL error 7') || str_contains($msg, 'Connection refused')) {
            return 'Bot に接続できません（localhost:3000 が起動しているか確認してください）。';
        }
        if (str_contains($msg, '403')) {
            return '認証エラー（ADMIN_KEY が一致していません）。';
        }

        return mb_strimwidth($msg, 0, 200, '…');
    }
}
