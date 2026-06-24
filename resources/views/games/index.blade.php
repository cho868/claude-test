@extends('layouts.app')
@section('title', 'ゲーム時間')

@section('content')
@php
    $fmt = fn ($min) => intdiv($min, 60) . '時間' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . '分';
@endphp

<x-page-header title="ゲームプレイ時間" icon="🎮" />

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6">
        {{-- 手動記録 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">✍️ 手動で記録</h3>
            <form method="POST" action="{{ route('games.store') }}" class="space-y-3 text-sm">
                @csrf
                <input type="text" name="game_name" placeholder="ゲーム名" required class="w-full rounded-lg border-slate-300 shadow-sm">
                <div class="flex gap-2">
                    <input type="number" name="minutes" placeholder="分" min="1" max="1440" required class="w-24 rounded-lg border-slate-300 shadow-sm">
                    <input type="date" name="played_on" value="{{ now()->toDateString() }}" required class="flex-1 rounded-lg border-slate-300 shadow-sm">
                </div>
                <button class="w-full rounded-lg bg-slate-900 py-2 font-semibold text-white hover:bg-slate-700">記録 (+3pt)</button>
            </form>
        </div>

        {{-- Steam連携 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-2 font-bold">🟦 Steam 連携</h3>
            @if ($steamConfigured)
                <p class="mb-3 text-xs text-slate-500">プロフィールの Steam ID から直近2週間のプレイ実績を取り込みます。</p>
                <form method="POST" action="{{ route('games.sync-steam') }}">
                    @csrf
                    <button class="w-full rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white hover:bg-blue-500">Steam から取り込む</button>
                </form>
            @else
                <p class="text-xs text-slate-500">
                    <code>STEAM_API_KEY</code> が未設定です。<code>.env</code> に設定すると Steam 連携が有効になります。
                    Discord 連携もトークン設定で拡張可能です(README 参照)。
                </p>
            @endif
        </div>

        {{-- 今月の身内ランキング --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">🏅 今月のプレイ時間ランキング</h3>
            <ol class="space-y-1.5 text-sm">
                @forelse ($monthlyRanking as $i => $row)
                    <li class="flex justify-between">
                        <span>{{ $i + 1 }}. {{ $row->user->name ?? '不明' }}</span>
                        <span class="font-semibold">{{ $fmt($row->total) }}</span>
                    </li>
                @empty
                    <li class="text-slate-400">記録なし</li>
                @endforelse
            </ol>
        </div>
    </div>

    <div class="lg:col-span-2">
        {{-- ゲーム別合計 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-bold">ゲーム別 合計時間</h3>
                <span class="text-sm text-slate-500">総計 <b>{{ $fmt($totalMinutes) }}</b></span>
            </div>
            <div class="space-y-2">
                @forelse ($byGame as $g)
                    @php $pct = $totalMinutes > 0 ? round($g->total / $totalMinutes * 100) : 0; @endphp
                    <div>
                        <div class="mb-0.5 flex justify-between text-sm">
                            <span>{{ $g->game_name }}</span>
                            <span class="text-slate-500">{{ $fmt($g->total) }}</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-violet-500" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">まだ記録がありません。</p>
                @endforelse
            </div>
        </div>

        {{-- 履歴 --}}
        <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-2 font-bold">記録履歴</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs text-slate-400">
                        <th class="py-1">日付</th><th>ゲーム</th><th>時間</th><th>元</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $s)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5">{{ $s->played_on->format('n/j') }}</td>
                            <td>{{ $s->game_name }}</td>
                            <td>{{ $fmt($s->minutes) }}</td>
                            <td><span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $s->source }}</span></td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('games.destroy', $s) }}" onsubmit="return confirm('削除しますか?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-400 hover:underline">削除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-center text-slate-400">記録がありません</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
