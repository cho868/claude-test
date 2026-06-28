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
        <h3 class="mb-3 font-bold">🤝 みんなが持ってるゲーム <span class="text-xs font-normal text-slate-400">（2人以上）</span></h3>
        @forelse ($common as $g)
            <a href="https://store.steampowered.com/app/{{ $g['appid'] }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center justify-between gap-2 border-b py-1.5 text-sm last:border-0 hover:text-slate-900">
                <span class="truncate">{{ $g['name'] }}</span>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs {{ $g['count'] === $memberCount ? 'bg-emerald-100 text-emerald-700 font-bold' : 'bg-slate-100 text-slate-600' }}"
                      title="{{ implode('、', $g['owners']) }}">
                    {{ $g['count'] === $memberCount ? '全員' : $g['count'] . '人' }}
                </span>
            </a>
        @empty
            <p class="text-sm text-slate-400">{{ $configured ? '共通の所持ゲームが見つかりません（各自プロフィール公開＋Steam ID登録が必要）。' : 'STEAM_API_KEY 未設定です。' }}</p>
        @endforelse
    </div>
</div>
@endif
@endsection
