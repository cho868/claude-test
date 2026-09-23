<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 「いまどこまで設定が終わっているか」を自動で判定する。
 *
 * 数日空けてから開いても、何が済んでいて次に何を打てばいいかが分かるようにするのが目的。
 * 手で付け外しする SetupTask のチェックリストとは別物で、こちらは**実物を見て判定する**。
 *
 * サーバー上のファイルを読むだけで、状態は一切保存しない。
 */
class SetupStatus
{
    /** cron の最終実行を記録するファイル（RemindRoutines が毎時これを上書きする） */
    public const REMIND_STAMP = 'routines-remind.json';

    /** 毎時実行なので、これを超えて実行が無ければ止まっているとみなす */
    private const REMIND_STALE_MINUTES = 90;

    /** @return list<array{title:string,items:list<array<string,mixed>>}> */
    public function all(): array
    {
        return [
            ['title' => '🔔 通知（Web Push）', 'items' => $this->notificationChecks()],
            ['title' => '📱 PWA', 'items' => [$this->pwaCheck()]],
            ['title' => '🚀 デプロイ', 'items' => [$this->revisionCheck(), $this->migrationCheck()]],
            ['title' => '🔐 基本設定', 'items' => $this->basicChecks()],
        ];
    }

    /** 未解決（ng/warn）の件数。見出しに出して「残り何個か」を一目で分かるようにする。 */
    public function pendingCount(array $groups): int
    {
        return collect($groups)->flatMap(fn ($g) => $g['items'])
            ->whereIn('state', ['ng', 'warn'])
            ->count();
    }

    // ---------------------------------------------------------------- 通知

    private function notificationChecks(): array
    {
        $configured = filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'));

        $items = [[
            'label' => 'VAPID鍵',
            'state' => $configured ? 'ok' : 'ng',
            'detail' => $configured ? '設定済み' : '未設定。これが無いと通知を1通も送れない',
            'hint' => $configured ? null : "php artisan push:vapid\n# 出た2行を .env に貼って\nphp artisan config:cache",
        ]];

        $items[] = $this->remindCronCheck();

        $devices = PushSubscription::count();
        $items[] = [
            'label' => '通知を受け取る端末',
            'state' => $devices > 0 ? 'ok' : 'warn',
            'detail' => $devices > 0
                ? "{$devices}台が登録済み"
                : 'まだ0台。スマホを「ホーム画面に追加」してから、ソシャゲ日課 → ⚙️設定 →「この端末で通知を受け取る」で登録する（iPhoneはホーム画面から開かないと登録できない）',
            'hint' => null,
        ];

        $on = User::where('routine_notify', true)->count();
        $items[] = [
            'label' => '通知ONのユーザー',
            'state' => 'info',
            'detail' => "{$on}人",
            'hint' => null,
        ];

        return $items;
    }

    private function remindCronCheck(): array
    {
        $stamp = $this->readStamp();
        $cron = '0 * * * * cd '.base_path().' && /usr/bin/php artisan routines:remind >/dev/null 2>&1';

        if (! $stamp) {
            return [
                'label' => '定期実行（cron）',
                'state' => 'ng',
                'detail' => 'まだ一度も動いていない。cron が未登録の可能性',
                'hint' => "sudo crontab -e\n{$cron}",
            ];
        }

        $at = Carbon::parse($stamp['at']);
        $minutes = (int) $at->diffInMinutes(Carbon::now());
        $stale = $minutes > self::REMIND_STALE_MINUTES;
        $sent = $stamp['sent'] ?? 0;

        return [
            'label' => '定期実行（cron）',
            'state' => $stale ? 'warn' : 'ok',
            'detail' => '最終実行 '.$at->diffForHumans()."（{$at->format('n/j H:i')}・前回の送信 {$sent}件）"
                .($stale ? ' — 毎時動くはずなので止まっている可能性' : ''),
            'hint' => $stale ? "sudo crontab -l    # 登録されているか確認\n{$cron}" : null,
        ];
    }

    /** @return array{at:string,sent:int}|null */
    private function readStamp(): ?array
    {
        $path = storage_path('app/'.self::REMIND_STAMP);

        if (! is_readable($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return isset($data['at']) ? $data : null;
    }

    // ---------------------------------------------------------------- PWA

    private function pwaCheck(): array
    {
        $files = ['manifest.json', 'sw.js', 'offline.html', 'icons/icon-192.png', 'icons/icon-512.png'];
        $missing = array_values(array_filter($files, fn ($f) => ! file_exists(public_path($f))));

        return [
            'label' => 'ホーム画面に追加できるか',
            'state' => $missing ? 'ng' : 'ok',
            'detail' => $missing
                ? '足りないファイル: '.implode(', ', $missing)
                : 'manifest / Service Worker / アイコン すべて配信できている',
            'hint' => $missing ? 'php scripts/make-icons.php   # アイコンが無い場合' : null,
        ];
    }

    // ------------------------------------------------------------ デプロイ

    /** いま動いているコミット。git コマンドを叩かず .git を直接読む（権限で転ばないように） */
    private function revisionCheck(): array
    {
        $head = $this->readGitFile('HEAD');

        if (! $head) {
            return ['label' => '動いているコミット', 'state' => 'info', 'detail' => '不明（.git が読めない）', 'hint' => null];
        }

        $branch = str_starts_with($head, 'ref: ') ? basename($head) : '(detached)';
        $sha = str_starts_with($head, 'ref: ')
            ? $this->readGitFile(substr($head, 5)) ?? $this->shaFromPackedRefs(substr($head, 5))
            : $head;

        $refPath = base_path('.git/'.substr($head, 5));
        $updated = is_file($refPath) ? Carbon::createFromTimestamp(filemtime($refPath)) : null;

        return [
            'label' => '動いているコミット',
            'state' => 'info',
            'detail' => substr((string) $sha, 0, 7)." ({$branch})"
                .($updated ? ' — 最終更新 '.$updated->diffForHumans().'（'.$updated->format('n/j H:i').'）' : ''),
            'hint' => null,
        ];
    }

    private function readGitFile(string $relative): ?string
    {
        $path = base_path('.git/'.ltrim($relative, '/'));

        return is_readable($path) ? trim((string) file_get_contents($path)) : null;
    }

    /** ref ファイルが無い場合は packed-refs 側を見る */
    private function shaFromPackedRefs(string $ref): ?string
    {
        $packed = $this->readGitFile('packed-refs');

        if (! $packed) {
            return null;
        }

        foreach (explode("\n", $packed) as $line) {
            if (str_ends_with(trim($line), ' '.$ref)) {
                return strtok(trim($line), ' ');
            }
        }

        return null;
    }

    private function migrationCheck(): array
    {
        $files = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($p) => basename($p, '.php'));

        try {
            $applied = DB::table('migrations')->pluck('migration');
        } catch (\Throwable $e) {
            return ['label' => 'マイグレーション', 'state' => 'info', 'detail' => '確認できませんでした', 'hint' => null];
        }

        $pending = $files->diff($applied)->values();

        return [
            'label' => 'マイグレーション',
            'state' => $pending->isEmpty() ? 'ok' : 'ng',
            'detail' => $pending->isEmpty()
                ? "全{$files->count()}件が適用済み"
                : '未適用が'.$pending->count().'件: '.$pending->implode(', '),
            'hint' => $pending->isEmpty() ? null : 'sudo bash deploy/deploy-app.sh main',
        ];
    }

    // -------------------------------------------------------------- 基本設定

    private function basicChecks(): array
    {
        $debug = (bool) config('app.debug');
        $url = (string) config('app.url');
        $invite = config('portal.invite_code');

        return [
            [
                'label' => 'APP_DEBUG',
                'state' => $debug ? 'warn' : 'ok',
                'detail' => $debug ? 'true（公開サーバーでは false にする。エラー画面に内部情報が出る）' : 'false',
                'hint' => $debug ? "# .env\nAPP_DEBUG=false\nphp artisan config:cache" : null,
            ],
            [
                'label' => '公開URL',
                'state' => str_starts_with($url, 'https://') ? 'ok' : 'warn',
                'detail' => $url ?: '未設定',
                'hint' => str_starts_with($url, 'https://') ? null : "# .env\nAPP_URL=https://〜.ts.net\nSESSION_SECURE_COOKIE=true",
            ],
            [
                'label' => '新規登録の招待コード',
                'state' => filled($invite) ? 'ok' : 'warn',
                'detail' => filled($invite) ? '設定済み（登録に招待コードが必要）' : '未設定。URLを知っていれば誰でも登録できる',
                'hint' => filled($invite) ? null : "# .env\nREGISTRATION_INVITE_CODE=好きな合言葉\nphp artisan config:cache",
            ],
        ];
    }
}
