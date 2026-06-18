@extends('layouts.app')
@section('title', '資料')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">📚 資料 / ナレッジ</h2>
    <a href="{{ route('documents.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">＋ 記事を書く</a>
</div>

{{-- カテゴリ絞り込み --}}
@if ($categories->isNotEmpty())
    <div class="mb-4 flex flex-wrap gap-1 text-sm">
        <a href="{{ route('documents.index') }}"
           class="rounded-lg px-3 py-1.5 {{ !$category ? 'bg-slate-900 text-white' : 'bg-white' }}">すべて</a>
        @foreach ($categories as $c)
            <a href="{{ route('documents.index', ['category' => $c]) }}"
               class="rounded-lg px-3 py-1.5 {{ $category === $c ? 'bg-slate-900 text-white' : 'bg-white' }}">{{ $c }}</a>
        @endforeach
    </div>
@endif

<div class="space-y-3">
    @forelse ($documents as $doc)
        <a href="{{ route('documents.show', $doc) }}" class="block rounded-2xl bg-white p-5 shadow-sm hover:shadow-md">
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $doc->category }}</span>
                @unless ($doc->is_public)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">非公開</span>@endunless
            </div>
            <h3 class="mt-1.5 text-lg font-bold">{{ $doc->title }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ $doc->excerpt() }}</p>
            <p class="mt-2 text-xs text-slate-400">{{ $doc->user->name }} ・ {{ $doc->updated_at->format('Y/n/j') }} ・ 👁 {{ $doc->views }}</p>
        </a>
    @empty
        <div class="rounded-2xl bg-white p-8 text-center text-slate-400">
            まだ資料がありません。最初の記事を書いてみましょう！
        </div>
    @endforelse
</div>

<div class="mt-6">{{ $documents->links() }}</div>
@endsection
