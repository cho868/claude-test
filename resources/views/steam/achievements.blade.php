@extends('layouts.app')
@section('title', '実績コンプ率')

@section('content')
<x-page-header title="実績コンプ率" icon="✅" back="{{ route('steam.index') }}"
    subtitle="特定ゲームの実績達成率を身内で比較" />

<div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
    <form method="GET" action="{{ route('steam.achievements') }}" class="flex flex-wrap items-end gap-2">
        <div>
            <label class="block text-sm font-medium text-slate-700">App ID</label>
            <input type="text" name="appid" value="{{ $appid }}" placeholder="例: 1245620（ELDEN RING）"
                   class="mt-1 w-48 rounded-lg border-slate-300 text-sm shadow-sm">
        </div>
        <x-btn type="submit">比較する</x-btn>
        <span class="text-xs text-slate-400">App ID は store.steampowered.com/app/<b>数字</b>/ の数字部分</span>
    </form>
</div>

@unless ($configured)
    <div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-800">サーバーの <code>STEAM_API_KEY</code> が未設定です。</div>
@elseif (! $hasMembers)
    <p class="text-slate-400">Steam ID を登録している身内がいません。</p>
@elseif ($appid === '')
    <p class="text-slate-400">App ID を入れて「比較する」を押してください。</p>
@elseif (empty($rows))
    <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm">
        このゲームの実績データが取得できませんでした。<br>
        （このゲームに実績が無い／メンバーが未所持・未プレイ／プロフィールやゲーム詳細が非公開、のいずれか）
    </div>
@else
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">🏆 {{ $gameName ?? ('App ' . $appid) }} の実績達成率</h3>
        @foreach ($rows as $i => $r)
            <div class="mb-3">
                <div class="mb-1 flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 font-medium">
                        <span class="w-5 text-right text-slate-400">{{ $i + 1 }}</span>
                        <x-avatar :user="$r['user']" :size="24" />
                        {{ $r['user']->name }}
                    </span>
                    <span class="text-slate-500">{{ $r['pct'] }}%（{{ $r['achieved'] }}/{{ $r['total'] }}）</span>
                </div>
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full {{ $i === 0 ? 'bg-amber-400' : 'bg-emerald-500' }}" style="width: {{ $r['pct'] }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
@endunless
@endsection
