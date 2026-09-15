<!DOCTYPE html>
<html lang="ja" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ログイン') | {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- PWA: ホーム画面に追加するとアプリのように開き、Web Pushで通知を受け取れる --}}
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ポータル">
</head>
<body class="flex min-h-full items-center justify-center bg-gradient-to-br from-slate-900 to-slate-700 px-4 py-12">
    <div class="w-full max-w-md">
        <h1 class="mb-6 text-center text-3xl font-bold text-white">🏠 {{ config('app.name') }}</h1>
        <div class="rounded-2xl bg-white p-8 shadow-xl">
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
        </div>
    </div>
</body>
</html>
