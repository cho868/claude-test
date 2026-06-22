@extends('layouts.app')
@section('title', 'ソシャゲ管理')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">📋 ソシャゲ管理</h2>
</div>
<p class="mb-4 text-sm text-slate-500">ゲームごとに日課・週課・月課を登録してチェック。日付が変われば自動でリセットされます（日課=毎日 / 週課=毎週(月曜始まり) / 月課=毎月）。</p>

{{-- ゲーム追加 --}}
<form method="POST" action="{{ route('social.games.store') }}" class="mb-6 flex gap-2">
    @csrf
    <input type="text" name="name" placeholder="ゲーム名を追加(例: 原神)" required class="w-64 rounded-lg border-slate-300 text-sm shadow-sm">
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">＋ ゲーム追加</button>
</form>

@php
    $cadences = ['daily' => ['🔆','日課'], 'weekly' => ['📅','週課'], 'monthly' => ['🗓️','月課']];
@endphp

<div class="grid gap-4 lg:grid-cols-2">
    @forelse ($games as $game)
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-bold">🎮 {{ $game->name }}</h3>
                <form method="POST" action="{{ route('social.games.destroy', $game) }}" onsubmit="return confirm('「{{ $game->name }}」を削除しますか?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-rose-400 hover:underline">ゲーム削除</button>
                </form>
            </div>

            @foreach ($cadences as $key => [$icon, $label])
                @php $tasks = $game->tasks->where('cadence', $key); @endphp
                <div class="mb-3">
                    <p class="mb-1 text-xs font-bold text-slate-500">{{ $icon }} {{ $label }}</p>
                    @forelse ($tasks as $task)
                        @php $isDone = $done->has($task->id . '|' . $task->currentPeriodKey()); @endphp
                        <div class="flex items-center gap-2 py-0.5">
                            <form method="POST" action="{{ route('social.tasks.toggle', $task) }}">
                                @csrf
                                <button type="submit" class="flex h-5 w-5 items-center justify-center rounded border {{ $isDone ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 bg-white' }}">
                                    @if ($isDone) ✓ @endif
                                </button>
                            </form>
                            <span class="flex-1 text-sm {{ $isDone ? 'text-slate-400 line-through' : '' }}">{{ $task->title }}</span>
                            <form method="POST" action="{{ route('social.tasks.destroy', $task) }}" onsubmit="return confirm('削除?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-slate-300 hover:text-rose-400">×</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-xs text-slate-300">なし</p>
                    @endforelse
                </div>
            @endforeach

            {{-- 課題追加 --}}
            <form method="POST" action="{{ route('social.tasks.store', $game) }}" class="mt-2 flex gap-2 border-t pt-3">
                @csrf
                <input type="text" name="title" placeholder="課題を追加" required class="flex-1 rounded-lg border-slate-300 text-sm shadow-sm">
                <select name="cadence" class="rounded-lg border-slate-300 text-sm shadow-sm">
                    <option value="daily">日課</option>
                    <option value="weekly">週課</option>
                    <option value="monthly">月課</option>
                </select>
                <button class="rounded-lg bg-slate-700 px-3 text-sm text-white hover:bg-slate-600">＋</button>
            </form>
        </div>
    @empty
        <p class="text-slate-400">まだゲームがありません。上の入力から追加してください。</p>
    @endforelse
</div>
@endsection
