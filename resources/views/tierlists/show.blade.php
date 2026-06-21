@extends('layouts.app')
@section('title', $tierList->title)

@section('content')
@php
    $isOwner = $tierList->user_id === auth()->id() || auth()->user()->is_admin;
    $colors = ['#ef4444','#f59e0b','#eab308','#22c55e','#3b82f6','#8b5cf6'];
    $pool = $tierList->pool ?? [];
@endphp

<div class="mb-4 flex items-center justify-between gap-3">
    <div>
        <h2 class="text-2xl font-bold">
            📊 {{ $tierList->title }}
            @if ($tierList->is_template)<span class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 align-middle text-xs text-indigo-700">テンプレート</span>@endif
        </h2>
        <p class="text-sm text-slate-500">
            {{ $tierList->user->name }} ・ {{ $tierList->is_public ? '公開' : '非公開' }}
            @if ($tierList->template) ・ 元: <a href="{{ route('tierlists.show', $tierList->template) }}" class="hover:underline">{{ $tierList->template->title }}</a>@endif
        </p>
        @if ($tierList->description)<p class="mt-1 text-sm text-slate-600">{{ $tierList->description }}</p>@endif
    </div>
    <div class="flex shrink-0 gap-2">
        <a href="{{ route('tierlists.create', ['from' => $tierList->id]) }}"
           class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">この項目で自分のを作る</a>
        @if ($isOwner)
            <a href="{{ route('tierlists.edit', $tierList) }}" class="rounded-lg bg-slate-100 px-3 py-2 text-sm hover:bg-slate-200">編集</a>
            <form method="POST" action="{{ route('tierlists.destroy', $tierList) }}" onsubmit="return confirm('削除しますか?')">
                @csrf @method('DELETE')
                <button class="rounded-lg bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">削除</button>
            </form>
        @endif
    </div>
</div>

@if ($tierList->is_template)
    {{-- テンプレートは項目一覧を表示 --}}
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <h3 class="mb-2 text-sm font-bold text-slate-500">項目（{{ count($tierList->allItems()) }}）</h3>
        <div class="flex flex-wrap gap-2">
            @forelse ($tierList->allItems() as $item)
                <span class="rounded bg-slate-100 px-2 py-1 text-sm">{{ $item }}</span>
            @empty
                <span class="text-xs text-slate-300">（項目なし）</span>
            @endforelse
        </div>
    </div>
@else
    <div class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
        @foreach ($tierList->tiers as $i => $tier)
            <div class="flex items-stretch gap-2">
                <div class="flex w-14 items-center justify-center rounded-lg font-bold text-white" style="background-color: {{ $colors[$i % count($colors)] }}">
                    {{ $tier['label'] }}
                </div>
                <div class="flex min-h-[44px] flex-1 flex-wrap items-center gap-2 rounded-lg bg-slate-50 p-2">
                    @forelse ($tier['items'] ?? [] as $item)
                        <span class="rounded bg-white px-2 py-1 text-sm shadow border border-slate-200">{{ $item }}</span>
                    @empty
                        <span class="text-xs text-slate-300">（なし）</span>
                    @endforelse
                </div>
            </div>
        @endforeach

        @if (! empty($pool))
            <div class="flex items-stretch gap-2 pt-1">
                <div class="flex w-14 items-center justify-center rounded-lg bg-slate-300 text-center text-xs font-bold text-white">未分類</div>
                <div class="flex min-h-[44px] flex-1 flex-wrap items-center gap-2 rounded-lg bg-slate-50 p-2">
                    @foreach ($pool as $item)
                        <span class="rounded bg-white px-2 py-1 text-sm shadow border border-slate-200">{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif
@endsection
