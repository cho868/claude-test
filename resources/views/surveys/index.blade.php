@extends('layouts.app')
@section('title', 'アンケート')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">🗳️ アンケート</h2>
    <a href="{{ route('surveys.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">＋ 作成</a>
</div>

<div class="grid gap-3 sm:grid-cols-2">
    @forelse ($surveys as $survey)
        <a href="{{ route('surveys.show', $survey) }}" class="rounded-2xl bg-white p-4 shadow-sm hover:shadow-md">
            <div class="flex items-center justify-between">
                <h3 class="font-bold">{{ $survey->title }}</h3>
                @if ($survey->is_closed)<span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs">締切</span>
                @else<span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">募集中</span>@endif
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ $survey->user->name }} ・ {{ $survey->votes_count }}票</p>
        </a>
    @empty
        <p class="text-slate-400">まだアンケートがありません。</p>
    @endforelse
</div>

<div class="mt-6">{{ $surveys->links() }}</div>
@endsection
