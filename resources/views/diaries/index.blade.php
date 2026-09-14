@extends('layouts.app')
@section('title', '日記')

@section('content')
@php
    $prevMonth = $month->copy()->subMonth()->format('Y-m');
    $nextMonth = $month->copy()->addMonth()->format('Y-m');
    $today = \Illuminate\Support\Carbon::today();
@endphp

<x-page-header title="日記" icon="📔" subtitle="Markdown で書ける日記。既定は自分だけに見えます。">
    <x-slot:actions>
        <x-btn href="{{ route('diaries.feed') }}" variant="secondary">👥 みんなの日記</x-btn>
        @if ($todayEntry)
            <x-btn href="{{ route('diaries.show', $todayEntry) }}" variant="secondary">📖 今日の日記を見る</x-btn>
        @endif
        <x-btn href="{{ route('diaries.create') }}">✍️ 日記を書く</x-btn>
    </x-slot:actions>
</x-page-header>

{{-- サマリー --}}
<div class="mb-5 grid gap-3 sm:grid-cols-3">
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-400">🔥 連続記録</p>
        <p class="text-2xl font-extrabold">{{ $streak }}<span class="ml-0.5 text-sm font-semibold text-slate-400">日</span></p>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-400">🗓 {{ $month->format('Y年n月') }}</p>
        <p class="text-2xl font-extrabold">{{ $entries->count() }}<span class="ml-0.5 text-sm font-semibold text-slate-400">件</span></p>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-400">📚 これまでの合計</p>
        <p class="text-2xl font-extrabold">{{ number_format($totalCount) }}<span class="ml-0.5 text-sm font-semibold text-slate-400">件</span></p>
    </div>
</div>

{{-- 月の切り替え --}}
<div class="mb-3 flex items-center justify-between gap-2">
    <a href="{{ route('diaries.index', ['m' => $prevMonth]) }}"
       class="rounded-lg bg-white px-3 py-2 text-sm shadow-sm hover:bg-slate-50">← {{ $month->copy()->subMonth()->format('n月') }}</a>
    <p class="text-lg font-bold">{{ $month->format('Y年n月') }}</p>
    <a href="{{ route('diaries.index', ['m' => $nextMonth]) }}"
       class="rounded-lg bg-white px-3 py-2 text-sm shadow-sm hover:bg-slate-50">{{ $month->copy()->addMonth()->format('n月') }} →</a>
</div>

{{-- カレンダー --}}
<div class="mb-6 overflow-hidden rounded-2xl bg-white p-3 shadow-sm sm:p-4">
    <div class="grid grid-cols-7 gap-1 text-center text-xs font-bold text-slate-400">
        @foreach (['日', '月', '火', '水', '木', '金', '土'] as $i => $w)
            <div class="py-1 {{ $i === 0 ? 'text-rose-400' : ($i === 6 ? 'text-sky-400' : '') }}">{{ $w }}</div>
        @endforeach
    </div>
    @foreach ($calendar as $week)
        <div class="grid grid-cols-7 gap-1">
            @foreach ($week as $day)
                @if (! $day)
                    <div class="aspect-square rounded-lg bg-slate-50/60"></div>
                @else
                    @php
                        $entry = $byDate->get($day->toDateString());
                        $isToday = $day->isSameDay($today);
                        $isFuture = $day->gt($today);
                    @endphp
                    @if ($entry)
                        <a href="{{ route('diaries.show', $entry) }}"
                           title="{{ $entry->displayTitle() }}"
                           class="flex aspect-square flex-col items-center justify-center rounded-lg bg-amber-100 text-amber-900 transition hover:bg-amber-200 {{ $isToday ? 'ring-2 ring-slate-900' : '' }}">
                            <span class="text-[10px] font-bold">{{ $day->day }}</span>
                            <span class="text-base leading-none sm:text-lg">{{ $entry->moodIcon() ?? '📝' }}</span>
                        </a>
                    @elseif ($isFuture)
                        <div class="flex aspect-square items-start justify-center rounded-lg bg-slate-50 pt-1 text-[10px] text-slate-300">{{ $day->day }}</div>
                    @else
                        <a href="{{ route('diaries.create', ['date' => $day->toDateString()]) }}"
                           title="{{ $day->format('n月j日') }}に書く"
                           class="group flex aspect-square flex-col items-center justify-center rounded-lg bg-slate-50 transition hover:bg-slate-200 {{ $isToday ? 'ring-2 ring-slate-900' : '' }}">
                            <span class="text-[10px] font-bold text-slate-400">{{ $day->day }}</span>
                            <span class="text-xs text-slate-300 group-hover:text-slate-500">＋</span>
                        </a>
                    @endif
                @endif
            @endforeach
        </div>
    @endforeach
    <p class="mt-2 text-center text-[11px] text-slate-400">空いている日をタップするとその日付で書けます</p>
</div>

{{-- 一覧 --}}
@if ($entries->isEmpty())
    <div class="rounded-2xl bg-white p-10 text-center shadow-sm">
        <p class="text-4xl">📔</p>
        <p class="mt-2 text-slate-500">{{ $month->format('Y年n月') }}の日記はまだありません。</p>
        <a href="{{ route('diaries.create') }}" class="mt-3 inline-block font-semibold text-slate-900 hover:underline">今日の日記を書く →</a>
    </div>
@else
    <div class="space-y-3">
        @foreach ($entries as $entry)
            <a href="{{ route('diaries.show', $entry) }}"
               class="group block rounded-2xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start gap-3">
                    <div class="w-14 shrink-0 text-center">
                        <p class="text-2xl font-extrabold leading-none">{{ $entry->entry_date->day }}</p>
                        <p class="text-[11px] text-slate-400">{{ $entry->entry_date->format('D') }}</p>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold group-hover:text-slate-900">
                            {{ $entry->moodIcon() }}{{ $entry->weatherIcon() }} {{ $entry->displayTitle() }}
                        </p>
                        <p class="mt-0.5 line-clamp-2 text-sm text-slate-500">{{ $entry->excerpt() }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs {{ $entry->isShared() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ $entry->isShared() ? '身内に共有' : '自分のみ' }}
                    </span>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
