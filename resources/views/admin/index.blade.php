@extends('layouts.app')
@section('title', '管理')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">🛠️ 管理ダッシュボード</h2>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.bot') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold hover:bg-slate-200">🤖 Bot設定</a>
        <a href="{{ route('admin.server') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold hover:bg-slate-200">🖥️ サーバー状況</a>
        <a href="{{ route('admin.users') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">👥 ユーザー管理</a>
    </div>
</div>

{{-- サーバーリソース概要 --}}
@php $barColor = fn ($p) => $p >= 90 ? 'bg-rose-500' : ($p >= 70 ? 'bg-amber-500' : 'bg-emerald-500'); @endphp
<a href="{{ route('admin.server') }}" class="mb-6 block rounded-2xl bg-white p-5 shadow-sm hover:shadow-md">
    <div class="grid gap-5 sm:grid-cols-2">
        @foreach (['disk' => ['💽','ディスク'], 'memory' => ['🧠','メモリ']] as $k => [$icon, $label])
            <div>
                <div class="mb-1 flex justify-between text-sm">
                    <span class="font-semibold">{{ $icon }} {{ $label }}</span>
                    @if ($server[$k])
                        <span class="text-slate-500">{{ \App\Services\ServerStats::human($server[$k]['used']) }} / {{ \App\Services\ServerStats::human($server[$k]['total']) }}（{{ $server[$k]['percent'] }}%）</span>
                    @else
                        <span class="text-slate-400">取得不可</span>
                    @endif
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $server[$k] ? $barColor($server[$k]['percent']) : 'bg-slate-300' }}" style="width: {{ $server[$k]['percent'] ?? 0 }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-right text-xs text-slate-400">詳しいサーバー状況を見る →</p>
</a>

{{-- 統計 --}}
<div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @php
        $cards = [
            ['👥', 'ユーザー', $stats['users']],
            ['🛡️', '管理者', $stats['admins']],
            ['📚', '資料', $stats['documents']],
            ['🗳️', 'アンケート', $stats['surveys']],
            ['📅', '予定', $stats['events']],
            ['🎮', 'ゲーム分', $stats['game_minutes']],
        ];
    @endphp
    @foreach ($cards as [$icon, $label, $value])
        <div class="rounded-2xl bg-white p-4 text-center shadow-sm">
            <div class="text-2xl">{{ $icon }}</div>
            <div class="text-xl font-bold">{{ number_format($value) }}</div>
            <div class="text-xs text-slate-500">{{ $label }}</div>
        </div>
    @endforeach
</div>

{{-- セットアップ状況（実物を見て自動判定。手動チェックリストとは別物） --}}
@php
    $badge = [
        'ok'   => ['✅', 'bg-emerald-50 text-emerald-700'],
        'warn' => ['⚠️', 'bg-amber-50 text-amber-700'],
        'ng'   => ['❌', 'bg-rose-50 text-rose-700'],
        'info' => ['ℹ️', 'bg-slate-50 text-slate-500'],
    ];
@endphp
<div class="mb-6 rounded-2xl bg-white p-6 shadow-sm" x-data="{ open: {{ $setupPending > 0 ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 text-left">
        <div class="min-w-0">
            <h3 class="text-lg font-bold">🩺 セットアップ状況</h3>
            <p class="text-xs text-slate-400">サーバーの実物を見て判定</p>
        </div>
        <span class="flex shrink-0 items-center gap-2">
            @if ($setupPending > 0)
                <span class="whitespace-nowrap rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-700">
                    要対応 {{ $setupPending }}件
                </span>
            @else
                <span class="whitespace-nowrap rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">
                    すべて完了
                </span>
            @endif
            <span class="text-slate-400" x-text="open ? '▾' : '▸'"></span>
        </span>
    </button>

    <div x-show="open" x-cloak class="mt-4 space-y-5">
        @foreach ($setup as $group)
            <div>
                <p class="mb-2 text-sm font-bold text-slate-500">{{ $group['title'] }}</p>
                <div class="space-y-2">
                    @foreach ($group['items'] as $item)
                        @php [$icon, $tone] = $badge[$item['state']] ?? $badge['info']; @endphp
                        <div class="rounded-xl border border-slate-100 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $tone }}">{{ $icon }}</span>
                                <span class="text-sm font-semibold">{{ $item['label'] }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ $item['detail'] }}</p>
                            @if (! empty($item['hint']))
                                <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-slate-100">{{ $item['hint'] }}</pre>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        <p class="text-xs text-slate-400">
            ※ 開くたびに判定し直します。ラズパイで作業したあとリロードすれば、緑に変わったかどうかがそのまま分かります。
        </p>
    </div>
</div>

{{-- セットアップ / セキュリティ チェックリスト --}}
<div class="rounded-2xl bg-white p-6 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-lg font-bold">✅ セットアップ / セキュリティ チェックリスト</h3>
        <span class="text-sm text-slate-500">{{ $taskDone }} / {{ $taskTotal }} 完了</span>
    </div>
    <div class="mb-4 h-2 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $taskTotal ? round($taskDone / $taskTotal * 100) : 0 }}%"></div>
    </div>

    @foreach ($tasks as $category => $items)
        <h4 class="mt-4 mb-1 text-sm font-bold text-slate-500">{{ $category }}</h4>
        <div class="divide-y">
            @foreach ($items as $task)
                <div class="flex items-start gap-3 py-2">
                    <form method="POST" action="{{ route('admin.tasks.toggle', $task) }}" class="pt-0.5">
                        @csrf
                        <button type="submit" title="完了/未完了を切替"
                                class="flex h-5 w-5 items-center justify-center rounded border {{ $task->done ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 bg-white' }}">
                            @if ($task->done) ✓ @endif
                        </button>
                    </form>
                    <div class="flex-1">
                        <p class="text-sm font-medium {{ $task->done ? 'text-slate-400 line-through' : 'text-slate-800' }}">{{ $task->title }}</p>
                        @if ($task->description)<p class="text-xs text-slate-400">{{ $task->description }}</p>@endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
    <p class="mt-4 text-xs text-slate-400">詳しい手順は資料(管理者のみ)「セキュリティ：懸念点・対策・監視」「通知と死活監視」を参照。</p>
</div>
@endsection
