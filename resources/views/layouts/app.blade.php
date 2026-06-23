<!DOCTYPE html>
<html lang="ja" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '身内ポータル') | {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none}</style>
</head>
<body class="h-full bg-slate-100 text-slate-800">
<div class="min-h-full">
    <nav class="bg-slate-900 text-slate-100">
        <div class="mx-auto max-w-6xl px-4">
            <div class="flex min-h-14 flex-wrap items-center justify-between gap-2 py-2">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-tight">
                        🏠 {{ config('app.name') }}
                    </a>
                    @php
                        $menu = [
                            'ゲーム' => ['🎮', [
                                'tournaments.index' => ['🏆', 'トーナメント'],
                                'tierlists.index'   => ['📊', 'Tierリスト'],
                                'games.index'       => ['🎮', 'ゲーム時間'],
                                'matches.index'     => ['⚔️', '戦績'],
                                'social.index'      => ['📋', 'ソシャゲ管理'],
                                'pokemon.index'     => ['🔴', 'ポケモン計算'],
                            ]],
                            'からだ' => ['💪', [
                                'sleep.index'   => ['😴', '睡眠'],
                                'fitness.index' => ['💪', 'フィットネス'],
                            ]],
                            'みんな' => ['👥', [
                                'surveys.index'   => ['🗳️', 'アンケート'],
                                'schedule.index'  => ['📅', '予定'],
                                'documents.index' => ['📚', '資料'],
                                'memos.index'     => ['📝', 'メモ'],
                                'links.index'     => ['🔗', 'リンク集'],
                            ]],
                        ];
                    @endphp
                    <div class="flex flex-wrap items-center gap-1 text-sm">
                        @foreach ($menu as $cat => [$catIcon, $items])
                            @php $catActive = collect($items)->keys()->contains(fn ($r) => request()->routeIs(Str::before($r, '.').'*')); @endphp
                            <div x-data="{open:false}" @click.away="open=false" class="relative">
                                <button @click="open=!open"
                                        class="rounded px-3 py-1.5 hover:bg-slate-700 {{ $catActive ? 'bg-slate-700' : '' }}">
                                    {{ $catIcon }} {{ $cat }} <span class="text-[10px]">▼</span>
                                </button>
                                <div x-show="open" x-cloak @click="open=false"
                                     class="absolute left-0 z-50 mt-1 w-52 overflow-hidden rounded-lg bg-white py-1 text-slate-800 shadow-xl ring-1 ring-black/5">
                                    @foreach ($items as $route => [$icon, $label])
                                        <a href="{{ route($route) }}"
                                           class="block px-4 py-2 hover:bg-slate-100 {{ request()->routeIs(Str::before($route, '.').'*') ? 'bg-slate-100 font-semibold' : '' }}">
                                            {{ $icon }} {{ $label }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        @if (auth()->user()?->is_admin)
                            <a href="{{ route('admin.index') }}"
                               class="rounded px-3 py-1.5 text-amber-300 hover:bg-slate-700 {{ request()->routeIs('admin.*') ? 'bg-slate-700' : '' }}">
                                🛠️ 管理
                            </a>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 text-sm hover:opacity-80">
                            <x-avatar :user="auth()->user()" :size="28" />
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
