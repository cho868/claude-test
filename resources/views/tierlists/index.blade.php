@extends('layouts.app')
@section('title', 'ソート/ランキング')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">📊 ソート / ランキング</h2>
    <a href="{{ route('tierlists.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">＋ 新規作成</a>
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($tierLists as $list)
        <a href="{{ route('tierlists.show', $list) }}" class="rounded-2xl bg-white p-4 shadow-sm hover:shadow-md">
            <h3 class="font-bold">{{ $list->title }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ $list->user->name }} ・ {{ $list->is_public ? '公開' : '非公開' }}</p>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach (collect($list->tiers)->take(5) as $tier)
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $tier['label'] }}: {{ count($tier['items'] ?? []) }}</span>
                @endforeach
            </div>
        </a>
    @empty
        <p class="text-slate-400">まだありません。</p>
    @endforelse
</div>

<div class="mt-6">{{ $tierLists->links() }}</div>
@endsection
