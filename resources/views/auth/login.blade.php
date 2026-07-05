@extends('layouts.guest')
@section('title', 'ログイン')

@section('content')
    <h2 class="mb-6 text-center text-xl font-semibold">ログイン</h2>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">ログインID</label>
            <input type="text" name="username" value="{{ old('username') }}" required autofocus
                   autocapitalize="none" autocomplete="username"
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">パスワード</label>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300"> ログイン状態を保持
        </label>
        <button class="w-full rounded-lg bg-slate-900 py-2.5 font-semibold text-white hover:bg-slate-700">
            ログイン
        </button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">
        アカウントが無い? <a href="{{ route('register') }}" class="font-semibold text-slate-900 hover:underline">新規登録</a>
    </p>
@endsection
