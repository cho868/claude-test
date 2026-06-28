@extends('layouts.app')
@section('title', 'Steamセール')

@section('content')
<x-page-header title="Steam セール" icon="🏷️" back="{{ route('steam.index') }}"
    subtitle="いまセール中のゲーム（Steamストアの特集より・日本円）" />

@if (empty($specials))
    <p class="rounded-2xl bg-white p-6 text-slate-400 shadow-sm">セール情報を取得できませんでした（時間をおいて再読み込みしてください）。</p>
@else
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($specials as $s)
        <a href="https://store.steampowered.com/app/{{ $s['appid'] }}" target="_blank" rel="noopener noreferrer"
           class="overflow-hidden rounded-2xl bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            @if ($s['image'])<img src="{{ $s['image'] }}" alt="" class="h-28 w-full object-cover" loading="lazy">@endif
            <div class="p-3">
                <p class="truncate text-sm font-bold">{{ $s['name'] }}</p>
                <div class="mt-1 flex items-center gap-2">
                    @if ($s['discount'] > 0)<span class="rounded bg-emerald-600 px-1.5 py-0.5 text-xs font-bold text-white">-{{ $s['discount'] }}%</span>@endif
                    @if ($s['original'] > $s['final'])<span class="text-xs text-slate-400 line-through">¥{{ number_format($s['original']) }}</span>@endif
                    <span class="text-sm font-bold">¥{{ number_format($s['final']) }}</span>
                </div>
            </div>
        </a>
    @endforeach
</div>
<p class="mt-3 text-xs text-slate-400">※ Steamストアの特集(specials)より取得。価格は日本円のおおよそ・1時間キャッシュ。クリックでストアへ。</p>
@endif
@endsection
