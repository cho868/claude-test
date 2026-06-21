@extends('layouts.app')
@section('title', 'チャレンジ')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">🏁 チャレンジ</h2>
    <div class="flex gap-2">
        <a href="{{ route('fitness.index') }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold hover:bg-slate-200">💪 記録する</a>
        <a href="{{ route('challenges.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">＋ 作成</a>
    </div>
</div>

<div class="grid gap-3 sm:grid-cols-2">
    @forelse ($challenges as $c)
        @php $st = $c->status(); @endphp
        <a href="{{ route('challenges.show', $c) }}" class="rounded-2xl bg-white p-4 shadow-sm hover:shadow-md">
            <div class="flex items-center justify-between">
                <h3 class="font-bold">{{ $c->title }}</h3>
                <span class="rounded-full px-2 py-0.5 text-xs {{ $st === 'active' ? 'bg-emerald-100 text-emerald-700' : ($st === 'upcoming' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600') }}">
                    {{ $st === 'active' ? '開催中' : ($st === 'upcoming' ? '開催前' : '終了') }}
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                {{ $c->metricLabel() }} ・ {{ $c->participants_count }}人 ・
                {{ $c->starts_on->format('n/j') }}〜{{ $c->ends_on->format('n/j') }}
            </p>
        </a>
    @empty
        <p class="text-slate-400">まだチャレンジがありません。「＋ 作成」から始めましょう。</p>
    @endforelse
</div>

<div class="mt-6">{{ $challenges->links() }}</div>
@endsection
