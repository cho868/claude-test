@extends('layouts.app')
@section('title', 'ミニゲーム')

@section('content')
<x-page-header title="ミニゲーム" icon="👾" subtitle="サクッと遊んで身内ランキングで競おう（1ゲーム1日1回 +5pt）" />

@php
    $fmt = fn ($ms) => $ms >= 10000 ? number_format($ms / 1000, 2) . '秒' : number_format($ms) . 'ms';
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    {{-- ⚡ 反射神経 --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm"
         x-data="reactionGame()">
        <div class="mb-1 flex items-center justify-between">
            <h3 class="font-bold">⚡ 反射神経</h3>
            <span class="text-xs text-slate-400">自己ベスト:
                <b>{{ isset($myBest['reaction']) ? number_format($myBest['reaction']) . 'ms' : '—' }}</b></span>
        </div>
        <p class="mb-3 text-xs text-slate-500">緑になった瞬間タップ！ 5回の<b>平均タイム</b>で勝負。フライングはやり直し。</p>

        <button @click="tap()"
                class="flex h-44 w-full select-none items-center justify-center rounded-xl text-lg font-bold transition-colors"
                :class="{
                    'bg-slate-100 text-slate-600': phase === 'idle' || phase === 'done',
                    'bg-rose-500 text-white': phase === 'wait',
                    'bg-emerald-500 text-white': phase === 'go',
                }">
            <span x-show="phase === 'idle'">タップしてスタート</span>
            <span x-show="phase === 'wait'">待って…（緑になったら！）</span>
            <span x-show="phase === 'go'">今だ！！</span>
            <span x-show="phase === 'done'" x-text="doneLabel"></span>
        </button>

        <div class="mt-3 flex items-center justify-between text-sm">
            <span class="text-slate-500">ラウンド <b x-text="results.length"></b>/5
                <template x-for="r in results"><span class="ml-1 text-xs text-slate-400" x-text="r + 'ms'"></span></template>
            </span>
            <span class="font-semibold text-emerald-600" x-text="message"></span>
        </div>

        {{-- ランキング --}}
        <div class="mt-4 border-t pt-3">
            <p class="mb-2 text-xs font-bold text-slate-400">🏅 ランキング（平均タイムの自己ベスト）</p>
            @forelse ($boards['reaction'] as $i => $r)
                <div class="flex items-center justify-between py-1 text-sm">
                    <span class="flex items-center gap-2">
                        <span class="w-5 text-right text-slate-400">{{ $i + 1 }}</span>
                        <x-avatar :user="$r['user']" :size="22" />
                        {{ $r['user']->name }}
                    </span>
                    <b>{{ number_format($r['best']) }}ms</b>
                </div>
            @empty
                <p class="text-sm text-slate-400">まだ記録がありません。一番乗りしよう！</p>
            @endforelse
        </div>
    </div>

    {{-- 🔢 数字タッチ --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm"
         x-data="numbersGame()">
        <div class="mb-1 flex items-center justify-between">
            <h3 class="font-bold">🔢 数字タッチ</h3>
            <span class="text-xs text-slate-400">自己ベスト:
                <b>{{ isset($myBest['numbers']) ? number_format($myBest['numbers'] / 1000, 2) . '秒' : '—' }}</b></span>
        </div>
        <p class="mb-3 text-xs text-slate-500"><b>1→25</b> を順番にタップ。最初のタップからのタイムアタック。</p>

        <div x-show="!started" class="flex h-64 items-center justify-center rounded-xl bg-slate-100">
            <button @click="start()" class="rounded-lg bg-slate-900 px-6 py-2.5 font-semibold text-white hover:bg-slate-700">スタート</button>
        </div>
        <div x-show="started" x-cloak>
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="text-slate-500">つぎ: <b class="text-lg" x-text="next"></b></span>
                <span class="font-mono text-slate-600" x-text="elapsedLabel"></span>
            </div>
            <div class="grid grid-cols-5 gap-1.5">
                <template x-for="n in cells" :key="n">
                    <button @click="hit(n)"
                            class="aspect-square select-none rounded-lg text-base font-bold transition"
                            :class="n < next ? 'bg-emerald-100 text-emerald-300' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 active:scale-95'"
                            x-text="n"></button>
                </template>
            </div>
            <p class="mt-2 text-right text-sm font-semibold text-emerald-600" x-text="message"></p>
        </div>

        {{-- ランキング --}}
        <div class="mt-4 border-t pt-3">
            <p class="mb-2 text-xs font-bold text-slate-400">🏅 ランキング（自己ベスト）</p>
            @forelse ($boards['numbers'] as $i => $r)
                <div class="flex items-center justify-between py-1 text-sm">
                    <span class="flex items-center gap-2">
                        <span class="w-5 text-right text-slate-400">{{ $i + 1 }}</span>
                        <x-avatar :user="$r['user']" :size="22" />
                        {{ $r['user']->name }}
                    </span>
                    <b>{{ number_format($r['best'] / 1000, 2) }}秒</b>
                </div>
            @empty
                <p class="text-sm text-slate-400">まだ記録がありません。一番乗りしよう！</p>
            @endforelse
        </div>
    </div>
</div>

<script>
async function saveScore(game, score) {
    const res = await fetch('{{ route('arcade.score') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ game, score }),
    });
    if (!res.ok) return { ok: false };
    return res.json();
}

function reactionGame() {
    return {
        phase: 'idle', results: [], message: '', doneLabel: '', timer: null, goAt: 0,
        tap() {
            if (this.phase === 'idle' || this.phase === 'done') {
                this.results = []; this.message = ''; this.round();
            } else if (this.phase === 'wait') {
                clearTimeout(this.timer);
                this.message = 'フライング！このラウンドはやり直し';
                this.round();
            } else if (this.phase === 'go') {
                const ms = Math.round(performance.now() - this.goAt);
                this.results.push(ms);
                this.message = '';
                if (this.results.length >= 5) { this.finish(); } else { this.round(); }
            }
        },
        round() {
            this.phase = 'wait';
            this.timer = setTimeout(() => {
                this.phase = 'go';
                this.goAt = performance.now();
            }, 1000 + Math.random() * 3000);
        },
        async finish() {
            const avg = Math.round(this.results.reduce((a, b) => a + b, 0) / this.results.length);
            this.phase = 'done';
            this.doneLabel = `平均 ${avg}ms！`;
            const r = await saveScore('reaction', avg);
            if (r.ok) {
                this.message = (r.isBest ? '🎉 自己ベスト更新！' : '保存しました') + (r.earned ? ` +${r.earned}pt` : '');
                if (r.isBest) setTimeout(() => location.reload(), 1500);
            }
        },
    };
}

function numbersGame() {
    return {
        started: false, cells: [], next: 1, startAt: 0, elapsedLabel: '0.00', message: '', ticker: null,
        start() {
            this.cells = Array.from({ length: 25 }, (_, i) => i + 1);
            for (let i = this.cells.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [this.cells[i], this.cells[j]] = [this.cells[j], this.cells[i]];
            }
            this.next = 1; this.startAt = 0; this.message = ''; this.elapsedLabel = '0.00';
            this.started = true;
        },
        hit(n) {
            if (n !== this.next) return;
            if (this.next === 1) {
                this.startAt = performance.now();
                this.ticker = setInterval(() => {
                    this.elapsedLabel = ((performance.now() - this.startAt) / 1000).toFixed(2);
                }, 50);
            }
            this.next++;
            if (this.next > 25) this.finish();
        },
        async finish() {
            clearInterval(this.ticker);
            const ms = Math.round(performance.now() - this.startAt);
            this.elapsedLabel = (ms / 1000).toFixed(2);
            this.started = true;
            const r = await saveScore('numbers', ms);
            if (r.ok) {
                this.message = (r.isBest ? '🎉 自己ベスト更新！' : '保存しました') + (r.earned ? ` +${r.earned}pt` : '');
                if (r.isBest) setTimeout(() => location.reload(), 1500);
            }
        },
    };
}
</script>
@endsection
