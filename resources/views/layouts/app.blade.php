<!DOCTYPE html>
<html lang="ja" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '身内ポータル') | {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>[x-cloak]{display:none}</style>
</head>
<body class="h-full bg-slate-100 text-slate-800">
<div class="min-h-full">
    <nav class="bg-slate-900 text-slate-100">
        <div class="mx-auto max-w-6xl px-4">
            <div class="flex h-14 items-center justify-between">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-tight">
                        🏠 {{ config('app.name') }}
                    </a>
                    <div class="hidden gap-1 md:flex text-sm">
                        @php
                            $nav = [
                                'tournaments.index' => ['🏆', 'トーナメント'],
                                'tierlists.index'   => ['📊', 'ソート'],
                                'memos.index'       => ['📝', 'メモ'],
                                'sleep.index'       => ['😴', '睡眠'],
                                'games.index'       => ['🎮', 'ゲーム時間'],
                                'surveys.index'     => ['🗳️', 'アンケート'],
                                'schedule.index'    => ['📅', '予定'],
                            ];
                        @endphp
                        @foreach ($nav as $route => [$icon, $label])
                            <a href="{{ route($route) }}"
                               class="rounded px-3 py-1.5 hover:bg-slate-700 {{ request()->routeIs(Str::before($route, '.').'*') ? 'bg-slate-700' : '' }}">
                                {{ $icon }} {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 text-sm hover:opacity-80">
                            <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                            <span class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-slate-900">
                                {{ number_format(auth()->user()->points) }}pt
                            </span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded bg-slate-700 px-3 py-1.5 text-sm hover:bg-slate-600">ログアウト</button>
                        </form>
                    @endauth
                </div>
            </div>
            <div class="flex gap-1 overflow-x-auto pb-2 text-xs md:hidden">
                @foreach ($nav as $route => [$icon, $label])
                    <a href="{{ route($route) }}" class="whitespace-nowrap rounded px-2 py-1 hover:bg-slate-700">{{ $icon }} {{ $label }}</a>
                @endforeach
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-6xl px-4 py-8 text-center text-xs text-slate-400">
        {{ config('app.name') }} — 身内専用ポータル
    </footer>
</div>
</body>
</html>
