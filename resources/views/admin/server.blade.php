@extends('layouts.app')
@section('title', 'サーバー状況')

@php
    use App\Services\ServerStats;
    $barColor = fn ($p) => $p >= 90 ? 'bg-rose-500' : ($p >= 70 ? 'bg-amber-500' : 'bg-emerald-500');
@endphp

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">🖥️ サーバー状況</h2>
    <div class="flex items-center gap-3">
        <span class="text-xs text-slate-400">15秒ごとに自動更新</span>
        <a href="{{ route('admin.server') }}" class="rounded-lg bg-slate-100 px-3 py-2 text-sm hover:bg-slate-200">↻ 更新</a>
        <a href="{{ route('admin.index') }}" class="text-sm text-slate-500 hover:underline">← 管理</a>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    {{-- ディスク容量（メイン） --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="mb-3 font-bold">💽 ディスク容量</h3>
        @if ($s['disk'])
            @php $d = $s['disk']; @endphp
            <div class="mb-1 flex items-end justify-between">
                <span class="text-3xl font-extrabold {{ $d['percent'] >= 90 ? 'text-rose-600' : ($d['percent'] >= 70 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $d['percent'] }}%</span>
                <span class="text-sm text-slate-500">{{ ServerStats::human($d['used']) }} / {{ ServerStats::human($d['total']) }}</span>
            </div>
            <div class="h-4 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $barColor($d['percent']) }}" style="width: {{ $d['percent'] }}%"></div>
            </div>
            <p class="mt-2 text-sm text-slate-500">空き容量: <b>{{ ServerStats::human($d['free']) }}</b></p>
        @else
            <p class="text-slate-400">取得できませんでした。</p>
        @endif
    </div>

    {{-- メモリ --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="mb-3 font-bold">🧠 メモリ</h3>
        @if ($s['memory'])
            @php $m = $s['memory']; @endphp
            <div class="mb-1 flex items-end justify-between">
                <span class="text-3xl font-extrabold {{ $m['percent'] >= 90 ? 'text-rose-600' : ($m['percent'] >= 70 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $m['percent'] }}%</span>
                <span class="text-sm text-slate-500">{{ ServerStats::human($m['used']) }} / {{ ServerStats::human($m['total']) }}</span>
            </div>
            <div class="h-4 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $barColor($m['percent']) }}" style="width: {{ $m['percent'] }}%"></div>
            </div>
            <p class="mt-2 text-sm text-slate-500">空き: <b>{{ ServerStats::human($m['free']) }}</b></p>
            @if ($s['swap'] && $s['swap']['total'] > 0)
                <div class="mt-3">
                    <div class="mb-1 flex justify-between text-xs text-slate-500">
                        <span>スワップ</span><span>{{ ServerStats::human($s['swap']['used']) }} / {{ ServerStats::human($s['swap']['total']) }}</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $barColor($s['swap']['percent']) }}" style="width: {{ $s['swap']['percent'] }}%"></div>
                    </div>
                </div>
            @endif
        @else
            <p class="text-slate-400">取得できませんでした。</p>
        @endif
    </div>

    {{-- CPU負荷 --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="mb-3 font-bold">⚙️ CPU負荷（ロードアベレージ）</h3>
        @if ($s['load'])
            @php
                $cores = max(1, $s['cpus']);
                $load1 = $s['load'][0];
                $loadPct = (int) min(100, round($load1 / $cores * 100));
            @endphp
            <div class="mb-1 flex items-end justify-between">
                <span class="text-3xl font-extrabold {{ $loadPct >= 90 ? 'text-rose-600' : ($loadPct >= 70 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $load1 }}</span>
                <span class="text-sm text-slate-500">{{ $cores }} コア中（1分平均）</span>
            </div>
            <div class="h-4 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $barColor($loadPct) }}" style="width: {{ $loadPct }}%"></div>
            </div>
            <p class="mt-2 text-sm text-slate-500">1分 / 5分 / 15分: <b>{{ $s['load'][0] }}</b> / {{ $s['load'][1] }} / {{ $s['load'][2] }}</p>
        @else
            <p class="text-slate-400">取得できませんでした。</p>
        @endif
    </div>

    {{-- ストレージ内訳 & システム --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="mb-3 font-bold">📦 アプリのデータ & システム</h3>
        <dl class="grid grid-cols-2 gap-y-2 text-sm">
            <dt class="text-slate-500">DBサイズ(SQLite)</dt><dd class="text-right font-semibold">{{ ServerStats::human($s['db_bytes']) }}</dd>
            <dt class="text-slate-500">アップロード等(storage)</dt><dd class="text-right font-semibold">{{ ServerStats::human($s['storage_bytes']) }}</dd>
            <dt class="text-slate-500">稼働時間</dt><dd class="text-right font-semibold">{{ $s['uptime'] ?? '—' }}</dd>
            <dt class="text-slate-500">ホスト名</dt><dd class="text-right font-semibold">{{ $s['hostname'] }}</dd>
            <dt class="text-slate-500">OS</dt><dd class="text-right font-semibold">{{ $s['os'] }}</dd>
            <dt class="text-slate-500">PHP / Laravel</dt><dd class="text-right font-semibold">{{ $s['php'] }} / {{ $s['laravel'] }}</dd>
        </dl>
    </div>
</div>

{{-- 自動更新（JSで再取得・ページ全体をリロード） --}}
<script>setTimeout(() => location.reload(), 15000);</script>
@endsection
