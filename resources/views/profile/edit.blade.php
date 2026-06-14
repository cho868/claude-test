@extends('layouts.app')
@section('title', 'プロフィール')

@section('content')
<div class="mx-auto max-w-2xl">
    <h2 class="mb-4 text-2xl font-bold">⚙️ プロフィール設定</h2>

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-slate-700">名前</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">メールアドレス</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">アバター画像URL</label>
                <input type="url" name="avatar" value="{{ old('avatar', $user->avatar) }}" placeholder="https://..."
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>

            <div class="rounded-xl bg-slate-50 p-4">
                <h3 class="mb-3 text-sm font-bold text-slate-700">🔗 連携</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Discord ID</label>
                        <input type="text" name="discord_id" value="{{ old('discord_id', $user->discord_id) }}" placeholder="123456789012345678"
                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Steam ID (64bit)</label>
                        <input type="text" name="steam_id" value="{{ old('steam_id', $user->steam_id) }}" placeholder="7656119..."
                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <p class="mt-1 text-xs text-slate-500">設定すると「ゲーム時間」画面から Steam のプレイ実績を取り込めます。</p>
                    </div>
                </div>
            </div>

            <button class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700">保存</button>
        </form>
    </div>

    <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
        <h3 class="mb-2 font-bold">📊 ステータス</h3>
        <div class="flex flex-wrap gap-4 text-sm text-slate-600">
            <span>現在の称号: <x-title-badge :title="$user->currentTitle()" /></span>
            <span>ポイント: <b>{{ number_format($user->points) }}</b></span>
            <span>連続ログイン: <b>{{ $user->login_streak }}</b> 日</span>
            <span>累計ログイン: <b>{{ $user->total_logins }}</b> 回</span>
        </div>
    </div>
</div>
@endsection
