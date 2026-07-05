@extends('layouts.guest')
@section('title', 'パスワード再設定')

@section('content')
    <h2 class="mb-2 text-center text-xl font-semibold">パスワード再設定</h2>
    <p class="mb-6 text-center text-sm text-slate-500">新しいパスワードを設定してください。</p>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="block text-sm font-medium text-slate-700">ログインID</label>
            <input type="text" name="username" value="{{ old('username', $username) }}" required
                   autocapitalize="none" autocomplete="username"
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">新しいパスワード</label>
            <input type="password" name="password" required autofocus
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">新しいパスワード（確認）</label>
            <input type="password" name="password_confirmation" required
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <button class="w-full rounded-lg bg-slate-900 py-2.5 font-semibold text-white hover:bg-slate-700">
            パスワードを変更する
        </button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="{{ route('login') }}" class="font-semibold text-slate-900 hover:underline">ログインへ戻る</a>
    </p>
@endsection
