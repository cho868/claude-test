@extends('layouts.app')
@section('title', $diary->displayTitle())

@section('content')
@php $canEdit = $diary->canBeEditedBy(auth()->user()); @endphp

<div class="mx-auto max-w-3xl">
    <x-page-header :title="$diary->displayTitle()" icon="📔"
        back="{{ $diary->user_id === auth()->id() ? route('diaries.index', ['m' => $diary->entry_date->format('Y-m')]) : route('diaries.feed') }}"
        :subtitle="$diary->entry_date->format('Y年n月j日 (D)')">
        <x-slot:actions>
            @if ($canEdit)
                <x-btn href="{{ route('diaries.edit', $diary) }}" variant="secondary">✏️ 編集</x-btn>
                <form method="POST" action="{{ route('diaries.destroy', $diary) }}"
                      onsubmit="return confirm('この日記を削除しますか?')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100">削除</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <span class="flex items-center gap-1.5">
            <x-avatar :user="$diary->user" :size="24" />
            <span class="text-slate-600">{{ $diary->user->name }}</span>
        </span>
        @if ($diary->moodIcon())
            <span class="rounded-full bg-white px-2.5 py-1 text-xs shadow-sm">{{ $diary->moodIcon() }} {{ $diary->moodLabel() }}</span>
        @endif
        @if ($diary->weatherIcon())
            <span class="rounded-full bg-white px-2.5 py-1 text-xs shadow-sm">{{ $diary->weatherIcon() }} {{ $diary->weatherLabel() }}</span>
        @endif
        <span class="rounded-full px-2.5 py-1 text-xs {{ $diary->isShared() ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
            {{ $diary->isShared() ? '身内に共有' : '🔒 自分のみ' }}
        </span>
    </div>

    <article class="prose prose-slate max-w-none rounded-2xl bg-white p-6 shadow-sm sm:p-8">
        {!! $diary->renderedBody() !!}
    </article>

    <div class="mt-4 flex items-center justify-between gap-2 text-sm">
        @if ($prev)
            <a href="{{ route('diaries.show', $prev) }}" class="rounded-lg bg-white px-3 py-2 shadow-sm hover:bg-slate-50">
                ← {{ $prev->entry_date->format('n/j') }}
            </a>
        @else
            <span></span>
        @endif
        @if ($next)
            <a href="{{ route('diaries.show', $next) }}" class="rounded-lg bg-white px-3 py-2 shadow-sm hover:bg-slate-50">
                {{ $next->entry_date->format('n/j') }} →
            </a>
        @else
            <span></span>
        @endif
    </div>
</div>
@endsection
