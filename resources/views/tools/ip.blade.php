@extends('layouts.app')
@section('title', 'IPアドレス確認')

@section('content')
<x-page-header title="IPアドレス確認" icon="📡" back="{{ route('tools.index') }}"
    subtitle="サーバーから見た接続元と、ブラウザ・端末の情報" />

<div x-data="ipTool()" x-cloak class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">🌐 接続元</h3>
        <div class="rounded-xl bg-slate-900 p-4 text-center">
            <p class="text-xs text-slate-400">あなたのIPアドレス</p>
            <p class="mt-1 break-all font-mono text-2xl font-bold text-emerald-400">{{ $ip }}</p>
        </div>
        <dl class="mt-3 space-y-1.5 text-sm">
            @if (count($ips) > 1)
                <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-400">経路</dt>
                    <dd class="break-all font-mono text-xs">{{ implode(' → ', $ips) }}</dd></div>
            @endif
            <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-400">接続方式</dt><dd>{{ $proto }}</dd></div>
            <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-400">言語設定</dt>
                <dd class="break-all text-xs">{{ $lang ?: '-' }}</dd></div>
            <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-400">User-Agent</dt>
                <dd class="break-all text-xs">{{ $ua }}</dd></div>
        </dl>
        <p class="mt-3 text-xs text-slate-400">
            ※ Tailscale経由の場合、ここに出るのは中継経由のアドレスになることがあります。
        </p>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">💻 端末・ブラウザ</h3>
        <dl class="space-y-1.5 text-sm">
            <template x-for="row in rows" :key="row[0]">
                <div class="flex gap-2 border-b border-slate-50 py-1 last:border-0">
                    <dt class="w-32 shrink-0 text-slate-400" x-text="row[0]"></dt>
                    <dd class="break-all" x-text="row[1]"></dd>
                </div>
            </template>
        </dl>
    </div>
</div>

<script>
function ipTool() {
  return {
    rows: [],
    init() {
      const n = navigator, s = screen;
      const conn = n.connection || {};
      this.rows = [
        ['画面サイズ', `${s.width}×${s.height}（表示領域 ${innerWidth}×${innerHeight}）`],
        ['ピクセル比', devicePixelRatio + 'x'],
        ['色深度', s.colorDepth + 'bit'],
        ['OS/プラットフォーム', n.platform || n.userAgentData?.platform || '不明'],
        ['言語', n.language + '（' + (n.languages || []).join(', ') + '）'],
        ['タイムゾーン', Intl.DateTimeFormat().resolvedOptions().timeZone],
        ['CPUコア数', n.hardwareConcurrency ?? '不明'],
        ['メモリ(目安)', n.deviceMemory ? n.deviceMemory + 'GB以上' : '不明'],
        ['タッチ対応', (n.maxTouchPoints > 0) ? 'あり（' + n.maxTouchPoints + '点）' : 'なし'],
        ['回線種別', conn.effectiveType || '不明'],
        ['Cookie', n.cookieEnabled ? '有効' : '無効'],
        ['オンライン', n.onLine ? 'はい' : 'いいえ'],
        ['ダークモード', matchMedia('(prefers-color-scheme: dark)').matches ? 'ON' : 'OFF'],
      ];
    },
  };
}
</script>
@endsection
