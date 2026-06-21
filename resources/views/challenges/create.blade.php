@extends('layouts.app')
@section('title', 'チャレンジ作成')

@section('content')
<div class="mx-auto max-w-2xl">
    <h2 class="mb-4 text-2xl font-bold">🏁 チャレンジ作成</h2>
    <form method="POST" action="{{ route('challenges.store') }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">タイトル</label>
            <input type="text" name="title" value="{{ old('title') }}" required placeholder="例: 夏までに-3kgチャレンジ"
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">競う指標</label>
            <select name="metric" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
                <option value="weight_loss">減量率(%) — 体重を多く減らした人が勝ち</option>
                <option value="exercise_minutes">運動時間(分) — 期間中の合計運動時間が多い人が勝ち</option>
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">開始日</label>
                <input type="date" name="starts_on" value="{{ old('starts_on', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">終了日</label>
                <input type="date" name="ends_on" value="{{ old('ends_on', now()->addDays(30)->toDateString()) }}" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">説明(任意)</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">{{ old('description') }}</textarea>
        </div>
        <button class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700">作成して参加(+10pt)</button>
    </form>
</div>
@endsection
