@extends('layouts.app')
@section('title', 'ホワイトボード')

@section('content')
<x-page-header title="ホワイトボード" icon="🖊️" subtitle="手書きメモを身内で共有。スマホで書いて、家で確認。">
    <x-slot:actions>
        <x-btn href="{{ route('whiteboards.create') }}">＋ 新しく書く</x-btn>
    </x-slot:actions>
</x-page-header>

@if ($boards->isEmpty())
    <div class="rounded-2xl bg-white p-10 text-center shadow-sm">
        <p class="text-4xl">🖊️</p>
        <p class="mt-2 text-slate-500">まだホワイトボードがありません。</p>
        <a href="{{ route('whiteboards.create') }}" class="mt-3 inline-block font-semibold text-slate-900 hover:underline">最初の一枚を書く →</a>
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($boards as $board)
            <a href="{{ route('whiteboards.show', $board) }}"
               class="group overflow-hidden rounded-2xl bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="aspect-[4/3] overflow-hidden border-b bg-slate-50">
                    <img src="{{ $board->image_data }}" alt="{{ $board->title }}" loading="lazy"
                         class="h-full w-full object-contain">
                </div>
                <div class="flex items-center gap-2 p-3">
                    <x-avatar :user="$board->user" :size="24" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold group-hover:text-slate-900">{{ $board->title }}</p>
                        <p class="text-xs text-slate-400">{{ $board->user->name }} ・ {{ $board->updated_at->format('n/j H:i') }}</p>
                    </div>
                    @unless ($board->is_public)<span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">自分のみ</span>@endunless
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
