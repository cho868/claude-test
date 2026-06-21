@extends('layouts.app')
@section('title', $tournament->name)

@section('content')
@php $isOwner = $tournament->user_id === auth()->id() || auth()->user()->is_admin; @endphp

<div class="mb-4 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold">🏆 {{ $tournament->name }}</h2>
        <p class="text-sm text-slate-500">
            {{ $tournament->user->name }} 作成 ・ {{ count($tournament->participants ?? []) }}人 ・
            {{ ($tournament->bracket['format'] ?? $tournament->format) === 'double' ? 'ダブルイリミ' : 'シングル' }} ・
            <span id="statusLabel">{{ $tournament->status === 'finished' ? '終了' : '進行中' }}</span>
        </p>
    </div>
    @if ($isOwner)
        <form method="POST" action="{{ route('tournaments.destroy', $tournament) }}" onsubmit="return confirm('削除しますか?')">
            @csrf @method('DELETE')
            <button class="rounded-lg bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">削除</button>
        </form>
    @endif
</div>

@if ($tournament->description)
    <p class="mb-4 rounded-xl bg-white p-4 text-sm text-slate-600 shadow-sm">{{ $tournament->description }}</p>
@endif

@if (empty($tournament->bracket['matches']))
    <div class="rounded-2xl bg-amber-50 p-6 text-sm text-amber-800">
        この対戦表は旧形式です。お手数ですが新しく作成し直してください。
    </div>
@else
<div id="champBanner" class="mb-4 hidden rounded-2xl bg-amber-100 p-4 text-center text-lg font-bold text-amber-700"></div>

<style>
    #bracketWrap { position: relative; overflow: auto; }
    #lines { position: absolute; top: 0; left: 0; pointer-events: none; z-index: 0; }
    .bk-row { display: flex; align-items: stretch; gap: 48px; position: relative; z-index: 1; width: max-content; }
    .bk-col { display: flex; flex-direction: column; justify-content: space-around; min-width: 150px; gap: 14px; }
    .bk-col h4 { text-align: center; font-size: 11px; font-weight: 700; color: #94a3b8; }
    .bk-match { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    .bk-row-item { display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
    .bk-row-item:last-child { border-bottom: 0; }
    .bk-win { background: #ecfdf5; font-weight: 700; color: #047857; }
    .bk-empty { color: #cbd5e1; }
    .bk-pick { cursor: pointer; }
    .bk-pick:hover { background: #f8fafc; }
    .bk-section-label { font-weight: 700; color: #475569; margin: 4px 0; }
</style>

<div id="bracketWrap" class="rounded-2xl bg-white p-6 shadow-sm">
    <svg id="lines"></svg>
    <div class="relative" style="z-index:1;">
        <div class="bk-section-label">🏅 勝者側（WB）</div>
        <div id="wbRow" class="bk-row"></div>
        <div id="lbWrap" class="mt-8 hidden">
            <div class="bk-section-label">💀 敗者側（LB）</div>
            <div id="lbRow" class="bk-row"></div>
        </div>
    </div>
</div>

@if ($isOwner)
    <div class="mt-4 flex items-center gap-3">
        <button id="saveBtn" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">保存</button>
        <span class="text-xs text-slate-400">勝者をクリック → 自動で次へ進みます。線は勝ち上がり（灰）／敗者の移動（赤点線）。最後に「保存」。</span>
    </div>
    <form id="saveForm" method="POST" action="{{ route('tournaments.update', $tournament) }}" class="hidden">
        @csrf @method('PUT')
        <input type="hidden" name="bracket" id="bracketInput">
        <input type="hidden" name="status" id="statusInput">
    </form>
@endif

<script>
    const data = @json($tournament->bracket);
    const isOwner = @json($isOwner);
    const byId = {};
    data.matches.forEach(m => byId[m.id] = m);

    const wrap = document.getElementById('bracketWrap');
    const svg = document.getElementById('lines');

    // seed(W round0) と各試合の pick から全スロット・勝者を導出
    function derive() {
        data.matches.forEach(m => {
            if (!(m.bracket === 'W' && m.round === 0)) { m.p1 = null; m.p2 = null; }
            m.winner = null;
        });
        data.matches.forEach(m => {
            let winner = null, loser = null;
            if (m.p1 !== null && m.p2 !== null) {
                if (m.pick === m.p1 || m.pick === m.p2) { winner = m.pick; loser = (winner === m.p1) ? m.p2 : m.p1; }
            } else if (m.p1 !== null && m.p2 === null) { winner = m.p1; }
            else if (m.p1 === null && m.p2 !== null) { winner = m.p2; }
            m.winner = winner;
            if (winner !== null && m.winnerTo) byId[m.winnerTo[0]][m.winnerTo[1]] = winner;
            if (loser !== null && m.loserTo) byId[m.loserTo[0]][m.loserTo[1]] = loser;
        });
    }

    function champion() {
        const fin = data.matches.find(m => !m.winnerTo);
        return fin ? fin.winner : null;
    }

    function makeCard(m) {
        const card = document.createElement('div');
        card.className = 'bk-match';
        card.dataset.mid = m.id;
        [m.p1, m.p2].forEach(name => {
            const row = document.createElement('div');
            const isWin = m.winner && m.winner === name;
            const bye = name === null && m.bracket === 'W' && m.round === 0;
            row.className = 'bk-row-item' + (isWin ? ' bk-win' : '') + (name === null ? ' bk-empty' : '');
            const span = document.createElement('span');
            span.textContent = name === null ? (bye ? '（BYE）' : '（未定）') : name;
            row.appendChild(span);
            if (isOwner && name && m.p1 !== null && m.p2 !== null) {
                row.classList.add('bk-pick');
                row.title = 'クリックで勝者に設定';
                row.onclick = () => { m.pick = (m.pick === name) ? null : name; render(); };
            }
            card.appendChild(row);
        });
        return card;
    }

    function renderBracketRow(rowEl, bracketKey, extraGF) {
        rowEl.innerHTML = '';
        const rounds = {};
        data.matches.filter(m => m.bracket === bracketKey).forEach(m => {
            (rounds[m.round] = rounds[m.round] || []).push(m);
        });
        Object.keys(rounds).map(Number).sort((a, b) => a - b).forEach(r => {
            const col = document.createElement('div');
            col.className = 'bk-col';
            const h = document.createElement('h4');
            h.textContent = rounds[r][0].label;
            col.appendChild(h);
            rounds[r].sort((a, b) => a.col - b.col).forEach(m => col.appendChild(makeCard(m)));
            rowEl.appendChild(col);
        });
        // 勝者側の右端にグランドファイナル列を追加
        if (extraGF && byId['GF']) {
            const col = document.createElement('div');
            col.className = 'bk-col';
            const h = document.createElement('h4');
            h.textContent = byId['GF'].label;
            col.appendChild(h);
            col.appendChild(makeCard(byId['GF']));
            rowEl.appendChild(col);
        }
    }

    function lineBetween(srcId, dstId, color, dashed) {
        const a = wrap.querySelector('[data-mid="' + srcId + '"]');
        const b = wrap.querySelector('[data-mid="' + dstId + '"]');
        if (!a || !b) return;
        const wr = wrap.getBoundingClientRect();
        const ar = a.getBoundingClientRect(), br = b.getBoundingClientRect();
        const x1 = ar.right - wr.left + wrap.scrollLeft, y1 = ar.top - wr.top + wrap.scrollTop + ar.height / 2;
        const x2 = br.left - wr.left + wrap.scrollLeft, y2 = br.top - wr.top + wrap.scrollTop + br.height / 2;
        const mx = (x1 + x2) / 2;
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', `M ${x1} ${y1} H ${mx} V ${y2} H ${x2}`);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke', color);
        path.setAttribute('stroke-width', '2');
        if (dashed) path.setAttribute('stroke-dasharray', '4 4');
        svg.appendChild(path);
    }

    function drawLines() {
        svg.innerHTML = '';
        svg.setAttribute('width', wrap.scrollWidth);
        svg.setAttribute('height', wrap.scrollHeight);
        data.matches.forEach(m => {
            if (m.winnerTo) lineBetween(m.id, m.winnerTo[0], '#cbd5e1', false);
            if (m.loserTo) lineBetween(m.id, m.loserTo[0], '#fda4af', true);
        });
    }

    function render() {
        derive();
        renderBracketRow(document.getElementById('wbRow'), 'W', data.format === 'double');
        const lbWrap = document.getElementById('lbWrap');
        if (data.matches.some(m => m.bracket === 'L')) {
            lbWrap.classList.remove('hidden');
            renderBracketRow(document.getElementById('lbRow'), 'L', false);
        }
        // 優勝表示
        const champ = champion();
        const banner = document.getElementById('champBanner');
        if (champ) { banner.textContent = '🏆 優勝: ' + champ; banner.classList.remove('hidden'); }
        else { banner.classList.add('hidden'); }
        const sl = document.getElementById('statusLabel');
        if (sl) sl.textContent = champ ? '終了' : '進行中';
        requestAnimationFrame(drawLines);
    }

    if (isOwner) {
        document.getElementById('saveBtn').onclick = () => {
            derive();
            document.getElementById('bracketInput').value = JSON.stringify(data);
            document.getElementById('statusInput').value = champion() ? 'finished' : 'ongoing';
            document.getElementById('saveForm').submit();
        };
    }

    window.addEventListener('resize', drawLines);
    render();
</script>
@endif
@endsection
