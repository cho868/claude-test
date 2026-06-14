@extends('layouts.app')
@section('title', 'トーナメント')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">🏆 トーナメント</h2>
    <a href="{{ route('tournaments.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">＋ 新規作成</a>
</div>

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($tournaments as $t)
        <a href="{{ route('tournaments.show', $t) }}" class="rounded-2xl bg-white p-4 shadow-sm hover:shadow-md">
            <div class="flex items-center justify-between">
                <h3 class="font-bold">{{ $t->name }}</h3>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $t->status === 'finished' ? '終了' : '進行中' }}</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ count($t->participants ?? []) }}人 / {{ $t->format === 'double' ? 'ダブル' : 'シングル' }} ・ {{ $t->user->name }}</p>
        </a>
    @empty
        <p class="text-slate-400">まだトーナメントがありません。</p>
    @endforelse
</div>

<div class="mt-6">{{ $tournaments->links() }}</div>
@endsection
