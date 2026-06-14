@extends('layouts.app')
@section('title', 'トーナメント作成')

@section('content')
<div class="mx-auto max-w-2xl">
    <h2 class="mb-4 text-2xl font-bold">🏆 トーナメント作成</h2>
    <form method="POST" action="{{ route('tournaments.store') }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">大会名</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">形式</label>
            <select name="format" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
                <option value="single">シングルイリミネーション</option>
                <option value="double">ダブルイリミネーション</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">説明(任意)</label>
            <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">{{ old('description') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">参加者(1行に1人)</label>
            <textarea name="participants_text" rows="6" required placeholder="たろう&#10;はなこ&#10;じろう&#10;..."
                      class="mt-1 w-full rounded-lg border-slate-300 font-mono shadow-sm">{{ old('participants_text') }}</textarea>
            <p class="mt-1 text-xs text-slate-500">2人以上。人数が2の累乗でない場合は自動でBYE(不戦勝)を補います。</p>
        </div>
        <button class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700">対戦表を生成</button>
    </form>
</div>
@endsection
