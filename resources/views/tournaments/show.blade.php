@extends('layouts.app')
@section('title', $tournament->name)

@section('content')
@php $isOwner = $tournament->user_id === auth()->id() || auth()->user()->is_admin; @endphp

<div class="mb-4 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold">🏆 {{ $tournament->name }}</h2>
        <p class="text-sm text-slate-500">
            {{ $tournament->user->name }} 作成 ・ {{ count($tournament->participants ?? []) }}人 ・
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

<div class="overflow-x-auto rounded-2xl bg-white p-6 shadow-sm">
    <div id="bracket" class="flex items-start gap-8"></div>
</div>

@if ($isOwner)
    <div class="mt-4 flex gap-3">
        <button id="saveBtn" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">保存</button>
        <span class="self-center text-xs text-slate-400">勝者をクリック → 次のラウンドに自動で進みます。最後に「保存」。</span>
    </div>
    <form id="saveForm" method="POST" action="{{ route('tournaments.update', $tournament) }}" class="hidden">
        @csrf @method('PUT')
        <input type="hidden" name="bracket" id="bracketInput">
        <input type="hidden" name="status" id="statusInput">
    </form>
@endif

<script>
    const bracket = @json($tournament->bracket ?? ['rounds' => [], 'size' => 0]);
    const isOwner = @json($isOwner);
    const container = document.getElementById('bracket');

    // 前ラウンドの勝者を次ラウンドの対戦枠に反映し、無効になった勝者は消す
    function syncPlayers() {
        for (let r = 1; r < bracket.rounds.length; r++) {
            bracket.rounds[r].forEach((m, j) => {
                const a = bracket.rounds[r - 1][2 * j].winner ?? null;
                const b = bracket.rounds[r - 1][2 * j + 1].winner ?? null;
                m.p1 = a;
                m.p2 = b;
                if (m.winner && m.winner !== a && m.winner !== b) {
                    m.winner = null; // 上流が変わって不整合になった勝者をクリア
                }
            });
        }
    }

    function render() {
        syncPlayers();
        container.innerHTML = '';
        bracket.rounds.forEach((round, ri) => {
            const col = document.createElement('div');
            col.className = 'flex min-w-[180px] flex-col justify-around gap-4';
            const heading = document.createElement('div');
            heading.className = 'text-center text-xs font-bold text-slate-400';
            heading.textContent = (ri === bracket.rounds.length - 1) ? '決勝' : ('ラウンド ' + (ri + 1));
            col.appendChild(heading);

            round.forEach((m) => {
                const card = document.createElement('div');
                card.className = 'rounded-lg border border-slate-200 text-sm';
                [m.p1, m.p2].forEach((name) => {
                    const row = document.createElement('div');
                    const isWinner = m.winner && m.winner === name;
                    const isBye = name === null && ri === 0;
                    row.className = 'flex items-center justify-between border-b px-3 py-2 last:border-0 '
                        + (isWinner ? 'bg-emerald-50 font-bold text-emerald-700' : '')
                        + (name === null ? ' text-slate-300' : '');
                    row.textContent = name === null ? (isBye ? '（BYE）' : '（未定）') : name;
                    if (isOwner && name) {
                        row.style.cursor = 'pointer';
                        row.title = 'クリックで勝者に設定';
                        row.onclick = () => { m.winner = (m.winner === name) ? null : name; render(); };
                    }
                    card.appendChild(row);
                });
                col.appendChild(card);
            });
            container.appendChild(col);
        });

        // 優勝者表示
        const last = bracket.rounds[bracket.rounds.length - 1];
        if (last && last.length === 1 && last[0].winner) {
            const champ = document.createElement('div');
            champ.className = 'flex flex-col justify-center';
            champ.innerHTML = '<div class="rounded-lg bg-amber-100 px-4 py-3 text-center font-bold text-amber-700">🏆 優勝<br>' + last[0].winner + '</div>';
            container.appendChild(champ);
        }
    }

    if (isOwner) {
        document.getElementById('saveBtn').onclick = () => {
            syncPlayers();
            const last = bracket.rounds[bracket.rounds.length - 1];
            const finished = last.length === 1 && !!last[0].winner;
            document.getElementById('bracketInput').value = JSON.stringify(bracket);
            document.getElementById('statusInput').value = finished ? 'finished' : 'ongoing';
            document.getElementById('saveForm').submit();
        };
    }

    render();
</script>
@endsection
