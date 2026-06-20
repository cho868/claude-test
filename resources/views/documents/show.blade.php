@extends('layouts.app')
@section('title', $document->title)

@section('content')
@php $isOwner = $document->user_id === auth()->id() || auth()->user()->is_admin; @endphp

<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11/build/styles/github.min.css">

<div class="mx-auto max-w-3xl">
    <a href="{{ route('documents.index') }}" class="text-sm text-slate-500 hover:underline">← 資料一覧</a>

    <div class="mt-2 mb-4 flex items-start justify-between gap-4">
        <div>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $document->category }}</span>
            <h1 class="mt-1.5 text-3xl font-bold">{{ $document->title }}</h1>
            <p class="mt-1 text-sm text-slate-400">
                {{ $document->user->name }} ・ {{ $document->updated_at->format('Y/n/j H:i') }} ・ 👁 {{ $document->views }}
                @if ($document->visibility === 'admin')<span class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-rose-700">🔒 管理者のみ</span>
                @elseif ($document->visibility === 'private')<span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-700">自分のみ</span>@endif
            </p>
        </div>
        @if ($isOwner)
            <div class="flex shrink-0 gap-2">
                <a href="{{ route('documents.edit', $document) }}" class="rounded-lg bg-slate-100 px-3 py-2 text-sm hover:bg-slate-200">編集</a>
                <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('削除しますか?')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">削除</button>
                </form>
            </div>
        @endif
    </div>

    <article class="prose prose-slate max-w-none rounded-2xl bg-white p-6 shadow-sm sm:p-8">
        {!! $document->renderedBody() !!}
    </article>
</div>

<script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11/build/highlight.min.js"></script>
<script>document.querySelectorAll('article pre code').forEach(el => hljs.highlightElement(el));</script>
@endsection
