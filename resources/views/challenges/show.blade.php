@extends('layouts.app')
@section('title', $challenge->title)

@section('content')
@php
    $isOwner = $challenge->user_id === auth()->id() || auth()->user()->is_admin;
    $st = $challenge->status();
    $isWeight = $challenge->metric === 'weight_loss';
    $medals = ['🥇', '🥈', '🥉'];
@endphp

<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-start justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold">🏁 {{ $challenge->title }}</h2>
            <p class="text-sm text-slate-500">
                {{ $challenge->metricLabel() }} ・ {{ $challenge->starts_on->format('Y/n/j') }}〜{{ $challenge->ends_on->format('n/j') }} ・
                <span class="font-semibold">{{ $st === 'active' ? '開催中' : ($st === 'upcoming' ? '開催前' : '終了') }}</span>
            </p>
            @if ($challenge->description)<p class="mt-2 text-sm text-slate-600 whitespace-pre-wrap">{{ $challenge->description }}</p>@endif
        </div>
        <div class="flex shrink-0 gap-2">
            @if ($joined)
                <form method="POST" action="{{ route('challenges.leave', $challenge) }}">@csrf
                    <button class="rounded-lg bg-slate-100 px-3 py-2 text-sm hover:bg-slate-200">参加をやめる</button>
                </form>
            @else
                <form method="POST" action="{{ route('challenges.join', $challenge) }}">@csrf
                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">参加する</button>
                </form>
            @endif
            @if ($isOwner)
                <form method="POST" action="{{ route('challenges.destroy', $challenge) }}" onsubmit="return confirm('削除しますか?')">@csrf @method('DELETE')
                    <button class="rounded-lg bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">削除</button>
                </form>
            @endif
        </div>
    </div>

    <p class="mb-3 rounded-xl bg-blue-50 px-4 py-2 text-sm text-blue-800">
        記録は <a href="{{ route('fitness.index') }}" class="font-semibold underline">フィットネス</a> から。
        {{ $isWeight ? '期間中の体重を2日以上記録すると順位に反映されます。' : '期間中の運動時間が合計されます。' }}
    </p>

    {{-- ランキング --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">🏆 ランキング（{{ $challenge->participants->count() }}人）</h3>
        @php $best = $standings->max('value') ?: 1; @endphp
        @forelse ($standings as $i => $row)
            @php $pct = $best > 0 ? max(2, round($row['value'] / $best * 100)) : 0; @endphp
            <div class="mb-3 {{ $row['user']->id === auth()->id() ? 'rounded-lg bg-amber-50 p-2' : '' }}">
                <div class="mb-1 flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 font-medium">
                        <span class="inline-block w-6">{{ $medals[$i] ?? ($i + 1) }}</span>
                        <x-avatar :user="$row['user']" :size="24" />
                        {{ $row['user']->name }}
                    </span>
                    <span class="text-slate-500">
                        {{ $isWeight ? sprintf('%+.1f%%', $row['value']) : ($row['value'] . '分') }}
                        <span class="ml-1 text-xs text-slate-400">{{ $row['detail'] }}</span>
                    </span>
                </div>
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $i === 0 ? 'bg-amber-400' : 'bg-emerald-500' }}" style="width: {{ $row['value'] > 0 ? $pct : 0 }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-400">まだ参加者がいません。「参加する」から参加しましょう。</p>
        @endforelse
    </div>
</div>
@endsection
