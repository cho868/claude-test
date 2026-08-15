@extends('layouts.app')
@section('title', 'モンスター')

@section('content')
<x-page-header title="モンスター" icon="🐣" subtitle="ポータルを使うほど育つ相棒。みんなでボスに挑もう" />

@php
    $me = auth()->user();
    $speciesJson = collect($species)->map(fn ($v, $k) => ['key' => $k] + $v)->values();
@endphp

<div x-data="monsterPage()" x-cloak>

    {{-- ===== みんなの共有ボス ===== --}}
    <div class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 to-slate-700 p-5 text-white shadow-sm">
        <div class="flex flex-wrap items-center gap-4">
            <div class="shrink-0" x-html="svg('{{ $boss->species }}', 4, 7, 96)"></div>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-300">今週のボス（{{ $boss->week }}）</p>
                <h3 class="text-2xl font-bold">{{ $boss->name }}</h3>
                <p class="mt-1 text-xs text-slate-300">
                    みんなが獲得したポイントがそのままダメージになります。今週中に倒そう！
                </p>
            </div>
            <div class="shrink-0 text-right">
                @if ($raid['defeated'])
                    <p class="text-2xl font-extrabold text-amber-300">🎉 撃破！</p>
                @else
                    <p class="text-2xl font-extrabold text-amber-400">{{ $raid['percent'] }}<span class="text-base">%</span></p>
                    <p class="text-xs text-slate-300">残り {{ number_format($raid['remaining']) }}</p>
                @endif
            </div>
        </div>

        <div class="mt-4 h-4 w-full overflow-hidden rounded-full bg-slate-600/50">
            <div class="h-full rounded-full transition-all {{ $raid['defeated'] ? 'bg-gradient-to-r from-amber-300 to-emerald-400' : 'bg-gradient-to-r from-rose-500 to-amber-400' }}"
                 style="width: {{ $raid['percent'] }}%"></div>
        </div>
        <p class="mt-1 text-right text-xs text-slate-300">
            {{ number_format($raid['damage']) }} / {{ number_format($boss->total_hp) }} ダメージ
        </p>

        @if ($raid['contributors']->isNotEmpty())
            <div class="mt-4 border-t border-white/10 pt-3">
                <p class="mb-2 text-xs font-bold text-slate-300">⚔️ 今週の貢献</p>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                    @foreach ($raid['contributors']->take(8) as $c)
                        <span class="flex items-center gap-1.5 text-sm">
                            <x-avatar :user="$c['user']" :size="20" />
                            {{ $c['user']->name }}
                            <b class="text-amber-300">{{ number_format($c['damage']) }}</b>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ===== 自分のモンスター ===== --}}
    @if (! $mine)
        <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="mb-1 font-bold">🥚 相棒を選ぼう</h3>
            <p class="mb-4 text-sm text-slate-500">種族は後から変えられません。能力はポータルの活動量で伸びていきます。</p>
            <form method="POST" action="{{ route('monsters.store') }}">
                @csrf
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($species as $key => $sp)
                        <label class="cursor-pointer">
                            <input type="radio" name="species" value="{{ $key }}" class="peer sr-only" @checked($loop->first)>
                            <div class="rounded-xl border-2 border-slate-200 p-3 text-center transition peer-checked:border-emerald-500 peer-checked:bg-emerald-50">
                                <div class="flex justify-center" x-html="svg('{{ $key }}', 2, 3, 84)"></div>
                                <p class="mt-1 font-bold">{{ $sp['name'] }}</p>
                                <p class="text-xs text-slate-500">
                                    @php
                                        $tags = [];
                                        foreach (['hp' => '体力', 'atk' => '攻撃', 'def' => '防御', 'spd' => '素早さ'] as $k => $label) {
                                            if ($sp[$k] >= 3) $tags[] = "{$label}◎";
                                            elseif ($sp[$k] <= -3) $tags[] = "{$label}△";
                                        }
                                    @endphp
                                    {{ implode(' ', $tags) ?: 'バランス型' }}
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">名前</label>
                        <input type="text" name="name" required maxlength="40" placeholder="モンスターの名前"
                               class="mt-1 w-56 rounded-lg border-slate-300 text-sm shadow-sm">
                    </div>
                    <x-btn type="submit">🥚 仲間にする（+10pt）</x-btn>
                </div>
            </form>
        </div>
    @else
        <div class="mb-6 rounded-2xl bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center gap-5">
                <div class="shrink-0" x-html="svg('{{ $myStats['species'] }}', {{ $myStats['stage'] }}, {{ $me->id }}, 130)"></div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-xl font-bold">{{ $mine->name }}</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $myStats['speciesName'] }}</span>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">Lv.{{ $myStats['level'] }}</span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
                            進化 {{ $myStats['stage'] }}/4
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 sm:grid-cols-4">
                        @foreach ([['❤️ HP', $myStats['hp']], ['⚔️ 攻撃', $myStats['atk']], ['🛡️ 防御', $myStats['def']], ['💨 素早さ', $myStats['spd']]] as [$label, $val])
                            <div>
                                <p class="text-xs text-slate-400">{{ $label }}</p>
                                <p class="font-bold">{{ $val }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3">
                        <div class="mb-1 flex justify-between text-xs text-slate-500">
                            <span>次のレベルまで</span>
                            <span>あと {{ number_format(max(0, $nextLevelPoints - $me->points)) }}pt</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-emerald-400"
                                 style="width: {{ min(100, (int) round($me->points / max(1, $nextLevelPoints) * 100)) }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">
                            ポイントを稼ぐほどレベルが上がります（Lv5/12/25 で進化）
                        </p>
                    </div>
                </div>
                <form method="POST" action="{{ route('monsters.update') }}" class="flex shrink-0 items-end gap-2">
                    @csrf @method('PUT')
                    <div>
                        <label class="block text-xs text-slate-400">名前を変更</label>
                        <input type="text" name="name" value="{{ $mine->name }}" maxlength="40"
                               class="mt-1 w-40 rounded-lg border-slate-300 text-sm shadow-sm">
                    </div>
                    <button class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold hover:bg-slate-200">保存</button>
                </form>
            </div>
        </div>

        {{-- ===== 対戦相手 ===== --}}
        <div class="mb-6 rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-bold">⚔️ 身内と対戦</h3>
                <span class="text-xs text-slate-500">今日あと <b x-text="battlesLeft"></b> 回</span>
            </div>
            @if ($others->isEmpty())
                <p class="text-sm text-slate-400">対戦できる相手がまだいません。身内にモンスターを作ってもらおう！</p>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($others as $o)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 p-3">
                            <div class="shrink-0" x-html="svg('{{ $o['stats']['species'] }}', {{ $o['stats']['stage'] }}, {{ $o['user']->id }}, 64)"></div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ $o['monster']->name }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $o['user']->name }} ・ Lv.{{ $o['stats']['level'] }}
                                </p>
                                <p class="text-xs text-slate-400">
                                    ❤️{{ $o['stats']['hp'] }} ⚔️{{ $o['stats']['atk'] }} 🛡️{{ $o['stats']['def'] }} 💨{{ $o['stats']['spd'] }}
                                </p>
                            </div>
                            <button type="button" @click="fight({{ $o['user']->id }})"
                                    :disabled="battlesLeft <= 0 || busy"
                                    class="shrink-0 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-rose-700 disabled:opacity-40">
                                挑戦
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ===== 最近のバトル ===== --}}
    @if ($recent->isNotEmpty())
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">📜 最近のバトル</h3>
            <div class="space-y-1">
                @foreach ($recent as $b)
                    <div class="flex flex-wrap items-center gap-2 border-b py-1.5 text-sm last:border-0">
                        <span class="text-xs text-slate-400">{{ $b->created_at->format('n/j H:i') }}</span>
                        <span class="{{ $b->winner_id === $b->challenger_id ? 'font-bold text-emerald-600' : '' }}">{{ $b->challenger->name }}</span>
                        <span class="text-xs text-slate-400">vs</span>
                        <span class="{{ $b->winner_id === $b->opponent_id ? 'font-bold text-emerald-600' : '' }}">{{ $b->opponent->name }}</span>
                        <span class="text-xs text-slate-500">
                            @if ($b->winner) → 🏆 {{ $b->winner->name }} @else → 引き分け @endif
                            （{{ $b->turns }}ターン）
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== バトル演出 ===== --}}
    <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
         @click.self="show = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1 text-center">
                    <div class="flex justify-center" x-html="aSvg"></div>
                    <p class="truncate text-sm font-bold" x-text="aName"></p>
                    <div class="mt-1 h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" :style="`width:${aPct}%`"></div>
                    </div>
                    <p class="text-xs text-slate-500" x-text="`${hpA} / ${maxA}`"></p>
                </div>
                <span class="shrink-0 text-2xl font-black text-rose-500">VS</span>
                <div class="min-w-0 flex-1 text-center">
                    <div class="flex justify-center" x-html="bSvg"></div>
                    <p class="truncate text-sm font-bold" x-text="bName"></p>
                    <div class="mt-1 h-2.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" :style="`width:${bPct}%`"></div>
                    </div>
                    <p class="text-xs text-slate-500" x-text="`${hpB} / ${maxB}`"></p>
                </div>
            </div>

            <div class="h-40 overflow-y-auto rounded-xl bg-slate-50 p-3 text-sm" x-ref="logBox">
                <template x-for="(l, i) in shown" :key="i">
                    <p x-html="l" class="border-b border-slate-100 py-0.5 last:border-0"></p>
                </template>
            </div>

            <div class="mt-4 flex items-center justify-between">
                <p class="text-lg font-bold" x-text="verdict"></p>
                <button type="button" @click="show = false"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">閉じる</button>
            </div>
        </div>
    </div>
</div>

<script>
{!! file_get_contents(resource_path('views/monsters/_svg.js')) !!}

function monsterPage() {
  return {
    show: false, busy: false, battlesLeft: {{ $battlesLeft }},
    aName: '', bName: '', aSvg: '', bSvg: '',
    hpA: 0, hpB: 0, maxA: 1, maxB: 1, shown: [], verdict: '',
    get aPct() { return Math.max(0, this.hpA / this.maxA * 100); },
    get bPct() { return Math.max(0, this.hpB / this.maxB * 100); },
    svg(species, stage, seed, size) { return monsterSVG(species, stage, seed, size); },

    async fight(userId) {
      if (this.busy || this.battlesLeft <= 0) return;
      this.busy = true;
      try {
        const res = await fetch(`/monsters/battle/${userId}`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        });
        const d = await res.json();
        if (!d.ok) { alert(d.message || 'バトルできませんでした'); return; }
        this.battlesLeft = d.left;
        await this.play(d);
      } catch (e) {
        alert('通信に失敗しました');
      } finally { this.busy = false; }
    },

    async play(d) {
      this.aName = `${d.a.name}（${d.aName}）`;
      this.bName = `${d.b.name}（${d.bName}）`;
      this.aSvg = monsterSVG(d.a.species, d.a.stage, 1, 90);
      this.bSvg = monsterSVG(d.b.species, d.b.stage, 2, 90);
      this.maxA = d.a.hp; this.maxB = d.b.hp;
      this.hpA = d.a.hp;  this.hpB = d.b.hp;
      this.shown = []; this.verdict = ''; this.show = true;

      for (const l of d.log) {
        const atkName = l.by === 'a' ? d.a.name : d.b.name;
        let line;
        if (l.miss) {
          line = `<span class="text-slate-400">T${l.t} ${atkName} の攻撃！ …外した</span>`;
        } else {
          const c = l.crit ? '<b class="text-amber-600">クリティカル！</b> ' : '';
          line = `T${l.t} ${atkName} の攻撃！ ${c}<b class="text-rose-600">${l.dmg}</b> ダメージ`;
        }
        this.shown.push(line);
        this.hpA = l.hpA; this.hpB = l.hpB;
        await new Promise(r => setTimeout(r, 260));
        this.$nextTick(() => { this.$refs.logBox.scrollTop = this.$refs.logBox.scrollHeight; });
      }

      this.verdict = d.winner === 'a' ? '🏆 勝利！' : d.winner === 'b' ? '💀 敗北…' : '🤝 引き分け';
    },
  };
}
</script>
@endsection
