@extends('layouts.app')
@section('title', 'Steam')

@php
    $stateLabel = [0 => 'オフライン', 1 => 'オンライン', 2 => '取り込み中', 3 => '離席', 4 => 'スヌーズ', 5 => 'トレード希望', 6 => '対戦希望'];
@endphp

@section('content')
<x-page-header title="Steam" icon="🕹️" subtitle="身内のプレイ状況・共通ゲーム・実績・セール">
    <x-slot:actions>
        <x-btn href="{{ route('steam.achievements') }}" variant="secondary">✅ 実績コンプ率</x-btn>
        <x-btn href="{{ route('steam.sales') }}" variant="secondary">🏷️ セール情報</x-btn>
    </x-slot:actions>
</x-page-header>

@unless ($configured)
    <div class="mb-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800">
        サーバーの <code>STEAM_API_KEY</code> が未設定です。設定すると「いまプレイ中」「共通所持ゲーム」「実績」が使えます（セール情報はキー無しでも見られます）。
    </div>
@endunless

@if ($memberCount === 0)
    <p class="rounded-2xl bg-white p-6 text-slate-400 shadow-sm">Steam ID を登録している身内がまだいません。各自プロフィールから登録してください。</p>
@else
<div class="grid gap-6 lg:grid-cols-2">
    {{-- いま誰がプレイ中 --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">🎮 いま誰がプレイ中</h3>
        <div class="space-y-2">
            @foreach ($now->sortByDesc(fn ($n) => $n['game'] ? 2 : ($n['state'] > 0 ? 1 : 0)) as $n)
                <div class="flex items-center gap-3 border-b py-2 last:border-0">
                    <x-avatar :user="$n['user']" :size="32" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $n['user']->name }}</p>
                        @if ($n['game'])
                            <p class="truncate text-xs font-semibold text-emerald-600">🎮 {{ $n['game'] }} をプレイ中</p>
                        @else
                            <p class="text-xs text-slate-400">{{ $stateLabel[$n['state']] ?? 'オフライン' }}</p>
                        @endif
                    </div>
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $n['game'] ? 'bg-emerald-500' : ($n['state'] > 0 ? 'bg-sky-400' : 'bg-slate-300') }}"></span>
                </div>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-slate-400">※ Steamプロフィール（プレイ中の情報）が公開の人のみ反映。60秒キャッシュ。</p>
    </div>

    {{-- 共通の所持ゲーム --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-1 font-bold">🤝 みんなが持ってるゲーム <span class="text-xs font-normal text-slate-400">（2人以上）</span></h3>
        <p class="mb-3 text-xs text-slate-400">ゲーム名をクリックでワンクリック実績比較。🏬 でストアへ。</p>
        @forelse ($common as $g)
            <div class="flex items-center justify-between gap-2 border-b py-1.5 text-sm last:border-0">
                <a href="{{ route('steam.achievements', ['appid' => $g['appid']]) }}"
                   class="flex min-w-0 flex-1 items-center gap-1.5 hover:text-slate-900"
                   title="{{ $g['name'] }} の実績を比較">
                    <span class="text-xs text-slate-300 group-hover:text-emerald-500">✅</span>
                    <span class="truncate">{{ $g['name'] }}</span>
                </a>
                <div class="flex shrink-0 items-center gap-2">
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $g['count'] === $memberCount ? 'bg-emerald-100 text-emerald-700 font-bold' : 'bg-slate-100 text-slate-600' }}"
                          title="{{ implode('、', $g['owners']) }}">
                        {{ $g['count'] === $memberCount ? '全員' : $g['count'] . '人' }}
                    </span>
                    <a href="https://store.steampowered.com/app/{{ $g['appid'] }}" target="_blank" rel="noopener noreferrer"
                       class="text-slate-300 hover:text-slate-600" title="ストアで見る">🏬</a>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-400">{{ $configured ? '共通の所持ゲームが見つかりません（各自プロフィール公開＋Steam ID登録が必要）。' : 'STEAM_API_KEY 未設定です。' }}</p>
        @endforelse
    </div>
</div>

{{-- 自分の全期間プレイ時間 TOP --}}
@if ($configured)
<div class="mt-6 rounded-2xl bg-white p-5 shadow-sm">
    <h3 class="mb-3 font-bold">🎯 あなたのプレイ時間 TOP <span class="text-xs font-normal text-slate-400">（全期間）</span></h3>
    @if (! $hasMySteam)
        <p class="text-sm text-slate-400">プロフィールに Steam ID（バニティ名/URLでも可）を登録すると、自分の総プレイ時間ランキングが表示されます。
            <a href="{{ route('profile.edit') }}" class="font-medium text-emerald-600 hover:underline">プロフィールを編集</a>
        </p>
    @elseif (empty($myGames))
        <p class="text-sm text-slate-400">プレイ時間を取得できませんでした（ゲーム詳細が非公開、または未プレイ）。Steamのプライバシー設定で「ゲームの詳細」を公開にしてください。</p>
    @else
        @php $maxPlay = $myGames[0]['playtime'] ?: 1; @endphp
        <div class="space-y-2.5">
            @foreach ($myGames as $i => $g)
                <div>
                    <div class="mb-0.5 flex items-center justify-between gap-2 text-sm">
                        <a href="{{ route('steam.achievements', ['appid' => $g['appid']]) }}"
                           class="flex min-w-0 items-center gap-1.5 hover:text-slate-900" title="{{ $g['name'] }} の実績を比較">
                            <span class="w-5 shrink-0 text-right text-slate-400">{{ $i + 1 }}</span>
                            <span class="truncate font-medium">{{ $g['name'] }}</span>
                        </a>
                        <span class="shrink-0 text-slate-500">{{ number_format($g['playtime'] / 60, 1) }}h</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $i === 0 ? 'bg-amber-400' : 'bg-emerald-500' }}" style="width: {{ round($g['playtime'] / $maxPlay * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-slate-400">※ ゲーム名クリックで実績比較。6時間キャッシュ。</p>
    @endif
</div>
@endif
@endif
@endsection
