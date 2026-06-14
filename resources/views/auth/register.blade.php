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
            <label class="block text-sm font-medium text-slate-700">メールアドレス</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
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
        <button class="w-full rounded-lg bg-slate-900 py-2.5 font-semibold text-white hover:bg-slate-700">
            登録してはじめる
        </button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">
        すでにアカウントがある? <a href="{{ route('login') }}" class="font-semibold text-slate-900 hover:underline">ログイン</a>
    </p>
@endsection
