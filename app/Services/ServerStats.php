<?php

namespace App\Services;

/**
 * サーバーのリソース状況を取得する（Linux 前提。取得不可な項目は null）。
 * PHP 標準関数と /proc を使うため追加権限は不要。
 */
class ServerStats
{
    public function all(): array
    {
        return [
            'hostname' => gethostname() ?: '不明',
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'os' => $this->os(),
            'uptime' => $this->uptime(),
            'cpus' => $this->cpuCount(),
            'load' => $this->load(),
            'disk' => $this->disk(),
            'memory' => $this->memory(),
            'swap' => $this->swap(),
            'db_bytes' => $this->dbSize(),
            'storage_bytes' => $this->dirSize(storage_path('app')),
        ];
    }

    /** ルートパーティションの使用状況 */
    public function disk(): ?array
    {
        $total = @disk_total_space('/');
        $free = @disk_free_space('/');
        if (! $total) {
            return null;
        }
        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => (int) round($used / $total * 100),
        ];
    }

    public function memory(): ?array
    {
        $info = @file_get_contents('/proc/meminfo');
        if (! $info) {
            return null;
        }
        preg_match('/MemTotal:\s+(\d+)/', $info, $t);
        preg_match('/MemAvailable:\s+(\d+)/', $info, $a);
        if (empty($t) || empty($a)) {
            return null;
        }
        $total = ((int) $t[1]) * 1024;
        $avail = ((int) $a[1]) * 1024;
        $used = $total - $avail;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $avail,
            'percent' => $total ? (int) round($used / $total * 100) : 0,
        ];
    }

    public function swap(): ?array
    {
        $info = @file_get_contents('/proc/meminfo');
        if (! $info) {
            return null;
        }
        preg_match('/SwapTotal:\s+(\d+)/', $info, $t);
        preg_match('/SwapFree:\s+(\d+)/', $info, $f);
        if (empty($t)) {
            return null;
        }
        $total = ((int) $t[1]) * 1024;
        if ($total === 0) {
            return ['total' => 0, 'used' => 0, 'percent' => 0];
        }
        $free = ((int) ($f[1] ?? 0)) * 1024;
        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'percent' => (int) round($used / $total * 100),
        ];
    }

    /** [1分, 5分, 15分] のロードアベレージ */
    public function load(): ?array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;

        return $load ? array_map(fn ($v) => round($v, 2), $load) : null;
    }

    public function cpuCount(): int
    {
        $info = @file_get_contents('/proc/cpuinfo');
        if ($info && preg_match_all('/^processor\s*:/m', $info, $m)) {
            return count($m[0]);
        }

        return 1;
    }

    public function uptime(): ?string
    {
        $data = @file_get_contents('/proc/uptime');
        if (! $data) {
            return null;
        }
        $seconds = (int) floatval(explode(' ', $data)[0]);
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);

        return ($d > 0 ? "{$d}日 " : '') . "{$h}時間 {$m}分";
    }

    private function os(): string
    {
        $rel = @file_get_contents('/etc/os-release');
        if ($rel && preg_match('/PRETTY_NAME="?([^"\n]+)"?/', $rel, $m)) {
            return $m[1];
        }

        return php_uname('s');
    }

    private function dbSize(): ?int
    {
        $path = config('database.connections.sqlite.database');
        if (is_string($path) && is_file($path)) {
            return filesize($path) ?: null;
        }

        return null;
    }

    private function dirSize(string $dir): ?int
    {
        if (! is_dir($dir)) {
            return null;
        }
        $size = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($it as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /** バイトを人間可読に */
    public static function human(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, $i >= 3 ? 2 : 1) . ' ' . $units[$i];
    }
}
