<?php

namespace App\Http\Controllers;

use App\Services\SafeUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class ToolsController extends Controller
{
    /** ツール一覧（ハブ） */
    public function index()
    {
        return view('tools.index');
    }

    public function qr()      { return view('tools.qr'); }
    public function lottery() { return view('tools.lottery'); }
    public function base64()  { return view('tools.base64'); }
    public function ogpForm() { return view('tools.ogp'); }
    public function sslForm() { return view('tools.ssl'); }
    public function capture() { return view('tools.capture', ['chromium' => $this->chromiumPath()]); }

    /** IPアドレス確認（サーバーから見た接続元 + ブラウザ情報） */
    public function ip(Request $request)
    {
        return view('tools.ip', [
            'ip' => $request->ip(),
            'ips' => $request->ips(),                    // プロキシ経由の経路
            'ua' => (string) $request->userAgent(),
            'lang' => (string) $request->header('Accept-Language'),
            'proto' => $request->isSecure() ? 'HTTPS' : 'HTTP',
        ]);
    }

    /** OGP確認: 対象ページのメタ情報を取得して SNS での見え方を再現 */
    public function ogpFetch(Request $request)
    {
        $check = SafeUrl::check((string) $request->input('url'));
        if (! $check['ok']) {
            return response()->json(['ok' => false, 'message' => $check['message']], 422);
        }

        try {
            $res = Http::timeout(12)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; UchiwaPortal-OGP/1.0)',
            ])->get($check['url']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => '取得に失敗しました: ' . $e->getMessage()], 422);
        }

        if (! $res->successful()) {
            return response()->json(['ok' => false, 'message' => "HTTP {$res->status()} が返りました"], 422);
        }

        $html = mb_convert_encoding($res->body(), 'UTF-8', 'UTF-8, SJIS, EUC-JP, ASCII');
        $meta = $this->parseMeta($html);

        // og:image は相対パスのことがあるので絶対URLに直す
        if (! empty($meta['og:image'])) {
            $meta['og:image'] = $this->absoluteUrl($meta['og:image'], $check['url']);
        }

        return response()->json([
            'ok' => true,
            'url' => $check['url'],
            'meta' => $meta,
            'title' => $meta['og:title'] ?? $meta['_title'] ?? '',
            'desc' => $meta['og:description'] ?? $meta['description'] ?? '',
            'image' => $meta['og:image'] ?? '',
            'siteName' => $meta['og:site_name'] ?? parse_url($check['url'], PHP_URL_HOST),
            'card' => $meta['twitter:card'] ?? '',
        ]);
    }

    /** SSLチェッカー: 証明書の発行者・期限・対象ドメインを確認 */
    public function sslCheck(Request $request)
    {
        $check = SafeUrl::check((string) $request->input('url'));
        if (! $check['ok']) {
            return response()->json(['ok' => false, 'message' => $check['message']], 422);
        }
        $host = $check['host'];
        $port = (int) (parse_url($check['url'], PHP_URL_PORT) ?: 443);

        $ctx = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'verify_peer' => false,        // 期限切れ等でも情報は見たいので検証は自前で行う
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}", $errno, $errstr, 10,
            STREAM_CLIENT_CONNECT, $ctx,
        );
        if (! $client) {
            return response()->json(['ok' => false, 'message' => "接続できませんでした: {$errstr}"], 422);
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if (! $cert) {
            return response()->json(['ok' => false, 'message' => '証明書を解析できませんでした'], 422);
        }

        $now = time();
        $daysLeft = (int) floor(($cert['validTo_time_t'] - $now) / 86400);
        $names = [];
        if (! empty($cert['extensions']['subjectAltName'])) {
            foreach (explode(',', $cert['extensions']['subjectAltName']) as $n) {
                $n = trim(str_replace('DNS:', '', $n));
                if ($n !== '') {
                    $names[] = $n;
                }
            }
        }
        $matches = collect($names)->contains(fn ($n) => $n === $host
            || (str_starts_with($n, '*.') && str_ends_with($host, substr($n, 1))));

        $chain = [];
        foreach ($params['options']['ssl']['peer_certificate_chain'] ?? [] as $c) {
            $p = openssl_x509_parse($c);
            $chain[] = $p['subject']['CN'] ?? ($p['name'] ?? '?');
        }

        return response()->json([
            'ok' => true,
            'host' => $host,
            'valid' => $now >= $cert['validFrom_time_t'] && $now <= $cert['validTo_time_t'] && $matches,
            'expired' => $now > $cert['validTo_time_t'],
            'nameMatches' => $matches,
            'subject' => $cert['subject']['CN'] ?? '-',
            'issuer' => $cert['issuer']['O'] ?? ($cert['issuer']['CN'] ?? '-'),
            'from' => date('Y-m-d H:i', $cert['validFrom_time_t']),
            'to' => date('Y-m-d H:i', $cert['validTo_time_t']),
            'daysLeft' => $daysLeft,
            'sigAlg' => $cert['signatureTypeSN'] ?? '-',
            'names' => $names,
            'chain' => $chain,
        ]);
    }

    /** Webページ → PDF / 画像（ヘッドレスChromiumを利用） */
    public function captureRun(Request $request)
    {
        $bin = $this->chromiumPath();
        if (! $bin) {
            return response()->json([
                'ok' => false,
                'message' => 'サーバーに Chromium が入っていません。' .
                             'sudo apt-get install -y chromium （または chromium-browser）で導入できます。',
            ], 503);
        }

        $check = SafeUrl::check((string) $request->input('url'));
        if (! $check['ok']) {
            return response()->json(['ok' => false, 'message' => $check['message']], 422);
        }

        $type = $request->input('type') === 'pdf' ? 'pdf' : 'png';
        $width = min(2000, max(320, (int) $request->input('width', 1200)));
        $full = $request->boolean('fullpage', true);

        $out = tempnam(sys_get_temp_dir(), 'cap') . '.' . $type;
        $profile = sys_get_temp_dir() . '/chromium-profile-' . getmypid();

        $args = [
            $bin, '--headless=new', '--disable-gpu', '--no-sandbox', '--hide-scrollbars',
            '--disable-dev-shm-usage', '--no-first-run', '--disable-extensions',
            '--user-data-dir=' . $profile,
            '--virtual-time-budget=8000',        // JS描画をある程度待つ
            '--window-size=' . $width . ',900',
        ];
        $args[] = $type === 'pdf'
            ? '--print-to-pdf=' . $out
            : '--screenshot=' . $out . ($full ? ' --full-page-screenshot' : '');
        if ($type === 'png' && $full) {
            $args[] = '--full-page-screenshot';
        }
        $args[] = $check['url'];

        try {
            // ラズパイでも詰まらないよう時間制限をかける
            $result = Process::timeout(60)->run($args);
        } catch (\Throwable $e) {
            @unlink($out);
            return response()->json(['ok' => false, 'message' => '変換がタイムアウトしました（重いページの可能性）'], 422);
        } finally {
            // プロファイルの残骸を消す（SDカードを汚さない）
            if (is_dir($profile)) {
                @exec('rm -rf ' . escapeshellarg($profile));
            }
        }

        if (! is_file($out) || filesize($out) === 0) {
            @unlink($out);

            return response()->json([
                'ok' => false,
                'message' => '変換に失敗しました。' . mb_substr($result->errorOutput(), 0, 200),
            ], 422);
        }

        $data = file_get_contents($out);
        @unlink($out);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $check['host']) . '.' . $type;

        return response()->json([
            'ok' => true,
            'name' => $name,
            'size' => strlen($data),
            'dataUrl' => 'data:' . ($type === 'pdf' ? 'application/pdf' : 'image/png')
                . ';base64,' . base64_encode($data),
        ]);
    }

    // ===== 内部ヘルパー =====

    private function chromiumPath(): ?string
    {
        foreach (['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable'] as $bin) {
            $path = trim((string) @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null'));
            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }

    private function parseMeta(string $html): array
    {
        $meta = [];
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $meta['_title'] = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES, 'UTF-8');
        }
        // <meta property="og:xxx" content="..."> / name="..." の両方に対応
        if (preg_match_all('#<meta\s+[^>]*>#i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                $key = null;
                if (preg_match('#(?:property|name)\s*=\s*["\']([^"\']+)["\']#i', $tag, $k)) {
                    $key = strtolower($k[1]);
                }
                if ($key && preg_match('#content\s*=\s*["\']([^"\']*)["\']#i', $tag, $c)) {
                    $meta[$key] = html_entity_decode($c[1], ENT_QUOTES, 'UTF-8');
                }
            }
        }

        return $meta;
    }

    private function absoluteUrl(string $src, string $base): string
    {
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        $p = parse_url($base);
        $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($src, '//')) {
            return $p['scheme'] . ':' . $src;
        }
        if (str_starts_with($src, '/')) {
            return $root . $src;
        }

        return $root . rtrim(dirname($p['path'] ?? '/'), '/') . '/' . $src;
    }
}
