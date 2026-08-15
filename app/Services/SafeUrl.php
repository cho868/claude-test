<?php

namespace App\Services;

/**
 * 外部URLを取りに行くツール（OGP確認・SSLチェック・ページキャプチャ）の共通ガード。
 *
 * サーバーから任意のURLを取得する機能は SSRF（内部ネットワークへの踏み台化）の温床になる。
 * このサーバーは家庭内LANにいるため、ルーターの管理画面や他の家電を叩かれると実害が出る。
 * そこで「http(s) のみ」「名前解決した先がプライベートIPなら拒否」を必ず通す。
 */
class SafeUrl
{
    /**
     * @return array{ok: bool, url?: string, host?: string, ip?: string, message?: string}
     */
    public static function check(string $input): array
    {
        $url = trim($input);
        if ($url === '') {
            return ['ok' => false, 'message' => 'URLを入力してください。'];
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;   // スキーム省略はhttpsとみなす
        }

        $parts = parse_url($url);
        if (! $parts || empty($parts['host'])) {
            return ['ok' => false, 'message' => 'URLの形式が正しくありません。'];
        }
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return ['ok' => false, 'message' => 'http / https のURLだけ指定できます。'];
        }

        $host = $parts['host'];

        // IPv4/IPv6 直指定も、ホスト名も、最終的に解決先IPで判定する
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
            foreach ($records as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
            }
            $ips = array_values(array_filter($ips));
            if (! $ips) {
                $resolved = gethostbyname($host);
                if ($resolved !== $host) {
                    $ips[] = $resolved;
                }
            }
        }

        if (! $ips) {
            return ['ok' => false, 'message' => "ホスト名を解決できませんでした: {$host}"];
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return [
                    'ok' => false,
                    'message' => '内部ネットワーク宛のURLは指定できません（安全のため）。',
                ];
            }
        }

        return ['ok' => true, 'url' => $url, 'host' => $host, 'ip' => $ips[0]];
    }
}
