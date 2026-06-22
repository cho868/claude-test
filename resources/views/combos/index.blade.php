@extends('layouts.app')
@section('title', 'スト6コンボ表')

@section('content')
<h2 class="mb-1 text-2xl font-bold">🎯 スト6 コンボ表</h2>
<p class="mb-4 text-sm text-slate-500">始動技ごとに、通常ヒット / カウンター / パニッシュカウンター で何を入れるかをまとめます。</p>

{{-- 追加フォーム --}}
<details class="mb-6 rounded-2xl bg-white p-5 shadow-sm">
    <summary class="cursor-pointer font-semibold">＋ コンボを追加</summary>
    <form method="POST" action="{{ route('combos.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
        @csrf
        <input type="text" name="character" list="chars" placeholder="キャラ(例: リュウ)" required class="rounded-lg border-slate-300 text-sm shadow-sm">
        <datalist id="chars">@foreach ($characters as $c)<option value="{{ $c }}">@endforeach</datalist>
        <input type="text" name="starter" placeholder="始動技/状況(例: 屈中P)" required class="rounded-lg border-slate-300 text-sm shadow-sm">
        <select name="hit_type" class="rounded-lg border-slate-300 text-sm shadow-sm">
            <option value="normal">通常ヒット</option>
            <option value="counter">カウンター</option>
            <option value="punish">パニッシュカウンター</option>
        </select>
        <input type="text" name="damage" placeholder="ダメージ(任意)" class="rounded-lg border-slate-300 text-sm shadow-sm">
        <input type="text" name="combo" placeholder="コンボ表記(例: 屈中P > 弱昇龍)" required class="rounded-lg border-slate-300 text-sm shadow-sm sm:col-span-2">
        <input type="text" name="note" placeholder="メモ(任意)" class="rounded-lg border-slate-300 text-sm shadow-sm sm:col-span-2">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" checked class="rounded border-slate-300"> 身内に公開</label>
        <div class="sm:col-span-2"><button class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-700">追加</button></div>
    </form>
</details>

{{-- キャラ絞り込み --}}
@if ($characters->isNotEmpty())
    <div class="mb-4 flex flex-wrap gap-1 text-sm">
        <a href="{{ route('combos.index') }}" class="rounded-lg px-3 py-1.5 {{ !$character ? 'bg-slate-900 text-white' : 'bg-white' }}">すべて</a>
        @foreach ($characters as $c)
            <a href="{{ route('combos.index', ['character' => $c]) }}" class="rounded-lg px-3 py-1.5 {{ $character === $c ? 'bg-slate-900 text-white' : 'bg-white' }}">{{ $c }}</a>
        @endforeach
    </div>
@endif

@php
    $cols = ['normal' => ['通常ヒット','bg-slate-100'], 'counter' => ['カウンター','bg-amber-100'], 'punish' => ['パニカン','bg-rose-100']];
@endphp

@forelse ($grouped as $charName => $byStarter)
    <h3 class="mb-2 mt-4 text-lg font-bold">{{ $charName }}</h3>
    <div class="mb-4 overflow-x-auto rounded-2xl bg-white p-4 shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs text-slate-500">
                    <th class="w-32 py-2">始動</th>
                    @foreach ($cols as [$label, $bg])<th class="py-2">{{ $label }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($byStarter as $starter => $entries)
                    <tr class="border-b align-top last:border-0">
                        <td class="py-2 font-semibold">{{ $starter }}</td>
                        @foreach ($cols as $type => [$label, $bg])
                            <td class="py-2 pr-3">
                                @foreach ($entries->where('hit_type', $type) as $e)
                                    <div class="group mb-1 rounded-lg {{ $bg }} px-2 py-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <span>{{ $e->combo }}@if ($e->damage)<span class="ml-1 text-xs font-bold text-slate-500">{{ $e->damage }}</span>@endif</span>
                                            @if ($e->user_id === auth()->id() || auth()->user()->is_admin)
                                                <form method="POST" action="{{ route('combos.destroy', $e) }}" onsubmit="return confirm('削除?')">@csrf @method('DELETE')<button class="text-xs text-slate-400 hover:text-rose-500">×</button></form>
                                            @endif
                                        </div>
                                        @if ($e->note)<div class="text-xs text-slate-500">{{ $e->note }}</div>@endif
                                    </div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p class="text-slate-400">まだコンボがありません。「＋ コンボを追加」から登録してください。</p>
@endforelse
@endsection
