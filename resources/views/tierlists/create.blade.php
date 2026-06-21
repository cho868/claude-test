@extends('layouts.app')
@php
    $editing = isset($tierList);
    $mode = $mode ?? 'ranking';                 // 'template' | 'ranking'
    $initialPool = $initialPool ?? [];
    $templateId = $templateId ?? null;
    $templateTitle = $templateTitle ?? null;
    $isTemplate = $mode === 'template';
    $heading = $isTemplate
        ? ($editing ? 'テンプレート編集' : 'Tierリスト テンプレート作成（項目のみ）')
        : ($editing ? 'Tierリスト編集' : 'Tierリスト作成');
@endphp
@section('title', $heading)

@section('content')
<div class="mx-auto max-w-4xl">
    <h2 class="mb-1 text-2xl font-bold">📊 {{ $heading }}</h2>
    @if ($isTemplate)
        <p class="mb-4 text-sm text-slate-500">項目だけ登録します（Tier分けは不要）。あとで「このテンプレで自分のランキングを作る」から各自がTier分けできます。</p>
    @elseif ($templateTitle)
        <p class="mb-4 text-sm text-slate-500">テンプレート「{{ $templateTitle }}」の項目を読み込みました。各ティアへドラッグして自分のランキングを作りましょう。</p>
    @else
        <p class="mb-4 text-sm text-slate-500">項目を各ティアへドラッグ。未分類のまま残してもそのまま保存されます。</p>
    @endif

    <form method="POST" action="{{ $editing ? route('tierlists.update', $tierList) : route('tierlists.store') }}"
          class="space-y-4 rounded-2xl bg-white p-6 shadow-sm" onsubmit="serializeAll()">
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($isTemplate)<input type="hidden" name="is_template" value="1">@endif
        @if ($templateId)<input type="hidden" name="template_id" value="{{ $templateId }}">@endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">タイトル</label>
                <input type="text" name="title" value="{{ old('title', $editing ? $tierList->title : '') }}" required
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <label class="mt-6 flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_public" value="1" class="rounded border-slate-300"
                    {{ old('is_public', $editing ? $tierList->is_public : true) ? 'checked' : '' }}>
                公開する(身内に共有)
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">説明(任意)</label>
            <input type="text" name="description" value="{{ old('description', $editing ? $tierList->description : '') }}"
                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
        </div>

        <div class="rounded-xl bg-slate-50 p-4">
            <label class="block text-sm font-medium text-slate-700">項目を追加(改行・カンマ区切りでまとめて追加OK)</label>
            <div class="mt-1 flex gap-2">
                <textarea id="newItems" rows="2" placeholder="キャラA&#10;キャラB&#10;キャラC"
                          class="flex-1 rounded-lg border-slate-300 font-mono text-sm shadow-sm"></textarea>
                <button type="button" onclick="addItems()" class="shrink-0 rounded-lg bg-slate-700 px-4 text-sm text-white hover:bg-slate-600">＋追加</button>
            </div>
            <p class="mt-1 text-xs text-slate-400">Ctrl+Enter でも追加。各チップの「×」で削除@unless($isTemplate)、ドラッグで各ティアへ移動@endunless。</p>

            {{-- Tier 行（テンプレートモードでは隠す） --}}
            <div id="tiers" class="mt-4 space-y-2 {{ $isTemplate ? 'hidden' : '' }}"></div>

            <div class="mt-4">
                <p class="mb-1 text-xs font-bold text-slate-500">{{ $isTemplate ? '項目一覧' : '未分類(ここから各ティアへドラッグ／残してもOK)' }}</p>
                <div id="pool" data-tier="pool" class="flex min-h-[48px] flex-wrap gap-2 rounded-lg border-2 border-dashed border-slate-300 p-2"></div>
            </div>
        </div>

        <input type="hidden" name="tiers" id="tiersInput">
        <input type="hidden" name="pool" id="poolInput">
        <button class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700">保存</button>
    </form>
</div>

<script>
    const TIER_COLORS = ['#ef4444','#f59e0b','#eab308','#22c55e','#3b82f6','#8b5cf6'];
    @php
        $initialTiers = $editing ? $tierList->tiers : [
            ['label' => 'S', 'items' => []],
            ['label' => 'A', 'items' => []],
            ['label' => 'B', 'items' => []],
            ['label' => 'C', 'items' => []],
            ['label' => 'D', 'items' => []],
        ];
    @endphp
    const initial = @json($initialTiers);
    const INITIAL_POOL = @json($initialPool);

    const tiersEl = document.getElementById('tiers');
    const poolEl = document.getElementById('pool');
    let dragged = null;

    function makeChip(text) {
        const chip = document.createElement('span');
        chip.dataset.chip = '1';
        chip.dataset.label = text;
        chip.className = 'inline-flex items-center gap-1 cursor-move rounded bg-white px-2 py-1 text-sm shadow border border-slate-200';
        chip.draggable = true;
        chip.ondragstart = () => dragged = chip;

        const label = document.createElement('span');
        label.textContent = text;

        const del = document.createElement('button');
        del.type = 'button';
        del.textContent = '×';
        del.className = 'ml-1 leading-none text-slate-400 hover:text-rose-500';
        del.title = '削除';
        del.onclick = (e) => { e.stopPropagation(); chip.remove(); };

        chip.append(label, del);
        return chip;
    }

    function makeDropZone(el) {
        el.ondragover = (e) => e.preventDefault();
        el.ondrop = (e) => { e.preventDefault(); if (dragged) el.appendChild(dragged); };
    }

    function buildTiers() {
        tiersEl.innerHTML = '';
        initial.forEach((tier, i) => {
            const row = document.createElement('div');
            row.className = 'flex items-stretch gap-2';
            const label = document.createElement('input');
            label.value = tier.label;
            label.className = 'w-14 rounded-lg text-center font-bold text-white border-0';
            label.style.backgroundColor = TIER_COLORS[i % TIER_COLORS.length];
            label.dataset.role = 'label';

            const zone = document.createElement('div');
            zone.className = 'flex min-h-[44px] flex-1 flex-wrap items-center gap-2 rounded-lg bg-white p-2';
            zone.dataset.tier = 'tier';
            makeDropZone(zone);
            (tier.items || []).forEach(item => zone.appendChild(makeChip(item)));

            row.append(label, zone);
            row.dataset.row = '1';
            tiersEl.appendChild(row);
        });
    }

    function addItems() {
        const ta = document.getElementById('newItems');
        const parts = ta.value.split(/\r?\n|,|、/).map(s => s.trim()).filter(Boolean);
        parts.forEach(p => poolEl.appendChild(makeChip(p)));
        ta.value = '';
        ta.focus();
    }
    document.getElementById('newItems').addEventListener('keydown', e => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); addItems(); }
    });

    function serializeAll() {
        // Tier
        const result = [];
        tiersEl.querySelectorAll('[data-row]').forEach(row => {
            const label = row.querySelector('[data-role=label]').value || '?';
            const items = [...row.querySelector('[data-tier]').querySelectorAll('[data-chip]')].map(c => c.dataset.label);
            result.push({ label, items });
        });
        document.getElementById('tiersInput').value = JSON.stringify(result);
        // 未分類(pool)
        const pool = [...poolEl.querySelectorAll('[data-chip]')].map(c => c.dataset.label);
        document.getElementById('poolInput').value = JSON.stringify(pool);
    }

    makeDropZone(poolEl);
    buildTiers();
    INITIAL_POOL.forEach(it => poolEl.appendChild(makeChip(it)));
</script>
@endsection
