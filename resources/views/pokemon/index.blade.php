@extends('layouts.app')
@section('title', 'ポケモン ダメージ計算')

@section('content')
<div class="mx-auto max-w-3xl">
    <h2 class="mb-1 text-2xl font-bold">🔴 ポケモン ダメージ計算</h2>
    <p class="mb-4 text-sm text-slate-500">
        ポケモンチャンピオンズ仕様（<b>Lv50固定</b>）に合わせた計算機。実数値（攻撃/防御）と技威力を入れると、
        タイプ相性・タイプ一致・急所などを反映してダメージと確定数を表示します。
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        {{-- 入力 --}}
        <div class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
            <div>
                <label class="block text-sm font-medium text-slate-700">レベル</label>
                <input type="number" id="level" value="50" class="mt-1 w-24 rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">技の威力</label>
                <input type="number" id="power" value="80" class="mt-1 w-28 rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">攻撃の実数値（A/C）</label>
                <input type="number" id="atk" value="150" class="mt-1 w-28 rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">相手の防御実数値（B/D）</label>
                <input type="number" id="def" value="100" class="mt-1 w-28 rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">相手のHP実数値（任意・確定数計算用）</label>
                <input type="number" id="hp" value="175" class="mt-1 w-28 rounded-lg border-slate-300 shadow-sm">
            </div>
        </div>

        {{-- タイプ相性・補正 --}}
        <div class="space-y-3 rounded-2xl bg-white p-5 shadow-sm">
            <div>
                <label class="block text-sm font-medium text-slate-700">技のタイプ</label>
                <select id="moveType" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm"></select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">相手タイプ1</label>
                    <select id="defType1" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm"></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">相手タイプ2</label>
                    <select id="defType2" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm"></select>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="stab" class="rounded border-slate-300"> タイプ一致（STAB ×1.5）</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="crit" class="rounded border-slate-300"> 急所（×1.5）</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="burn" class="rounded border-slate-300"> やけど（物理 ×0.5）</label>
            <div>
                <label class="block text-sm font-medium text-slate-700">その他補正（天候・道具など）</label>
                <input type="number" step="0.1" id="other" value="1.0" class="mt-1 w-24 rounded-lg border-slate-300 shadow-sm">
            </div>
        </div>
    </div>

    {{-- 結果 --}}
    <div class="mt-4 rounded-2xl bg-slate-900 p-6 text-center text-white shadow-sm">
        <p class="text-sm text-slate-400">ダメージ（乱数 0.85〜1.00）</p>
        <p id="dmg" class="text-3xl font-extrabold">—</p>
        <p id="dmgPct" class="text-sm text-slate-300"></p>
        <p id="ko" class="mt-2 text-lg font-bold text-amber-300"></p>
        <p id="eff" class="mt-1 text-xs text-slate-400"></p>
    </div>

    <p class="mt-3 text-xs text-slate-400">
        ※ ダメージ式は標準（メイン作品準拠）。チャンピオンズは個体値が廃止され実数値の決まり方が異なりますが、
        本ツールは「<b>実数値</b>」を直接入力するため計算結果は同じです。特性による無効化や固定ダメージ等は未対応。
    </p>
</div>

<script>
    const TYPES = ['ノーマル','ほのお','みず','でんき','くさ','こおり','かくとう','どく','じめん','ひこう','エスパー','むし','いわ','ゴースト','ドラゴン','あく','はがね','フェアリー'];
    // 攻撃タイプ → {効果ばつぐん(2x), いまひとつ(0.5x), 無効(0x)}
    const CHART = {
        'ノーマル':   {x2:[], h:['いわ','はがね'], z:['ゴースト']},
        'ほのお':     {x2:['くさ','こおり','むし','はがね'], h:['ほのお','みず','いわ','ドラゴン'], z:[]},
        'みず':       {x2:['ほのお','じめん','いわ'], h:['みず','くさ','ドラゴン'], z:[]},
        'でんき':     {x2:['みず','ひこう'], h:['でんき','くさ','ドラゴン'], z:['じめん']},
        'くさ':       {x2:['みず','じめん','いわ'], h:['ほのお','くさ','どく','ひこう','むし','ドラゴン','はがね'], z:[]},
        'こおり':     {x2:['くさ','じめん','ひこう','ドラゴン'], h:['ほのお','みず','こおり','はがね'], z:[]},
        'かくとう':   {x2:['ノーマル','こおり','いわ','あく','はがね'], h:['どく','ひこう','エスパー','むし','フェアリー'], z:['ゴースト']},
        'どく':       {x2:['くさ','フェアリー'], h:['どく','じめん','いわ','ゴースト'], z:['はがね']},
        'じめん':     {x2:['ほのお','でんき','どく','いわ','はがね'], h:['くさ','むし'], z:['ひこう']},
        'ひこう':     {x2:['くさ','かくとう','むし'], h:['でんき','いわ','はがね'], z:[]},
        'エスパー':   {x2:['かくとう','どく'], h:['エスパー','はがね'], z:['あく']},
        'むし':       {x2:['くさ','エスパー','あく'], h:['ほのお','かくとう','どく','ひこう','ゴースト','はがね','フェアリー'], z:[]},
        'いわ':       {x2:['ほのお','こおり','ひこう','むし'], h:['かくとう','じめん','はがね'], z:[]},
        'ゴースト':   {x2:['エスパー','ゴースト'], h:['あく'], z:['ノーマル']},
        'ドラゴン':   {x2:['ドラゴン'], h:['はがね'], z:['フェアリー']},
        'あく':       {x2:['エスパー','ゴースト'], h:['かくとう','あく','フェアリー'], z:[]},
        'はがね':     {x2:['こおり','いわ','フェアリー'], h:['ほのお','みず','でんき','はがね'], z:[]},
        'フェアリー': {x2:['かくとう','ドラゴン','あく'], h:['ほのお','どく','はがね'], z:[]},
    };

    // セレクト初期化
    const $ = id => document.getElementById(id);
    $('moveType').innerHTML = TYPES.map(t => `<option>${t}</option>`).join('');
    $('defType1').innerHTML = TYPES.map(t => `<option>${t}</option>`).join('');
    $('defType2').innerHTML = '<option value="">（なし）</option>' + TYPES.map(t => `<option>${t}</option>`).join('');

    function typeMul(move, def) {
        if (!def) return 1;
        const c = CHART[move];
        if (c.z.includes(def)) return 0;
        if (c.x2.includes(def)) return 2;
        if (c.h.includes(def)) return 0.5;
        return 1;
    }

    function calc() {
        const L = +$('level').value || 50, P = +$('power').value || 0;
        const A = +$('atk').value || 1, D = +$('def').value || 1;
        const hp = +$('hp').value || 0;
        const t = typeMul($('moveType').value, $('defType1').value) * typeMul($('moveType').value, $('defType2').value);
        const mod = (($('stab').checked ? 1.5 : 1)) * t * ($('crit').checked ? 1.5 : 1) * ($('burn').checked ? 0.5 : 1) * (+$('other').value || 1);

        const base = Math.floor(Math.floor(Math.floor((2 * L / 5 + 2) * P * A / D) / 50) + 2);
        const min = Math.floor(base * 0.85 * mod);
        const max = Math.floor(base * 1.0 * mod);

        if (t === 0) {
            $('dmg').textContent = '0（こうかがない）';
            $('dmgPct').textContent = ''; $('ko').textContent = ''; $('eff').textContent = '';
            return;
        }
        $('dmg').textContent = `${min} 〜 ${max}`;
        const effLabel = t >= 4 ? '4倍 ばつぐん' : t >= 2 ? '2倍 ばつぐん' : t <= 0.25 ? '1/4 いまひとつ' : t <= 0.5 ? '1/2 いまひとつ' : '等倍';
        $('eff').textContent = `タイプ相性: ×${t}（${effLabel}）／ 補正合計 ×${mod.toFixed(2)}`;

        if (hp > 0) {
            const pMin = (min / hp * 100).toFixed(1), pMax = (max / hp * 100).toFixed(1);
            $('dmgPct').textContent = `相手HPの ${pMin}% 〜 ${pMax}%`;
            const worst = Math.ceil(hp / Math.max(1, min));
            const best = Math.ceil(hp / Math.max(1, max));
            $('ko').textContent = (best === worst) ? `確定 ${worst} 発` : `乱数 ${best}〜${worst} 発`;
        } else {
            $('dmgPct').textContent = ''; $('ko').textContent = '';
        }
    }

    document.querySelectorAll('input,select').forEach(el => el.addEventListener('input', calc));
    calc();
</script>
@endsection
