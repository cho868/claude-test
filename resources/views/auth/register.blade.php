@extends('layouts.guest')
@section('title', '新規登録')

@section('content')
    <h2 class="mb-6 text-center text-xl font-semibold">新規登録</h2>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">名前</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">ログインID</label>
            <input type="text" name="username" value="{{ old('username') }}" required
                   autocapitalize="none" autocomplete="username"
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            <p class="mt-1 text-xs text-slate-500">半角英数字・ハイフン・アンダースコア（3〜32文字）。ログインに使います。</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">パスワード</label>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">パスワード(確認)</label>
            <input type="password" name="password_confirmation" required
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        @if (! empty(config('portal.invite_code')))
            <div>
                <label class="block text-sm font-medium text-slate-700">招待コード</label>
                <input type="text" name="invite_code" value="{{ old('invite_code') }}" required
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <p class="mt-1 text-xs text-slate-500">身内から共有された招待コードを入力してください。</p>
            </div>
        @endif
        <button class="w-full rounded-lg bg-slate-900 py-2.5 font-semibold text-white hover:bg-slate-700">
            登録してはじめる
        </button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">
        すでにアカウントがある? <a href="{{ route('login') }}" class="font-semibold text-slate-900 hover:underline">ログイン</a>
    </p>
@endsection
