@extends('layouts.app')
@section('title', 'Tierリスト')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <h2 class="text-2xl font-bold">📊 Tierリスト</h2>
    <div class="flex gap-2">
        <a href="{{ route('tierlists.create', ['mode' => 'template']) }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold hover:bg-slate-200">＋ テンプレート作成(項目のみ)</a>
        <a href="{{ route('tierlists.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">＋ Tierリスト作成</a>
    </div>
</div>

{{-- テンプレート --}}
<h3 class="mb-2 mt-2 font-bold text-slate-600">🧩 テンプレート（項目セット）</h3>
<div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($templates as $tpl)
        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <a href="{{ route('tierlists.show', $tpl) }}" class="font-bold hover:underline">{{ $tpl->title }}</a>
                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">テンプレ</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ count($tpl->allItems()) }}項目 ・ {{ $tpl->user->name }} ・ 作成 {{ $tpl->rankings_count }}件</p>
            <a href="{{ route('tierlists.create', ['from' => $tpl->id]) }}"
               class="mt-3 block rounded-lg bg-emerald-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-500">
                このテンプレで自分のランキングを作る
            </a>
        </div>
    @empty
        <p class="text-sm text-slate-400">まだテンプレートがありません。「＋ テンプレート作成」から作れます。</p>
    @endforelse
</div>

{{-- ランキング(作成されたTierリスト) --}}
<h3 class="mb-2 font-bold text-slate-600">🏷️ みんなのTierリスト</h3>
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($rankings as $list)
        <a href="{{ route('tierlists.show', $list) }}" class="rounded-2xl bg-white p-4 shadow-sm hover:shadow-md">
            <h3 class="font-bold">{{ $list->title }}</h3>
            <p class="mt-1 text-xs text-slate-500">
                {{ $list->user->name }}
                @if ($list->template) ・ 元: {{ $list->template->title }} @endif
            </p>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach (collect($list->tiers)->take(5) as $tier)
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $tier['label'] }}: {{ count($tier['items'] ?? []) }}</span>
                @endforeach
            </div>
        </a>
    @empty
        <p class="text-sm text-slate-400">まだありません。</p>
    @endforelse
</div>

<div class="mt-6">{{ $rankings->links() }}</div>
@endsection
