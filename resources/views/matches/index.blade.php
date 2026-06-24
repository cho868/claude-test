@extends('layouts.app')
@section('title', '戦績')

@section('content')
<x-page-header title="対戦ゲーム戦績" icon="⚔️" />

<div class="grid gap-6 lg:grid-cols-3">
    {{-- 記録フォーム --}}
    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">📝 戦績を記録</h3>
            <form method="POST" action="{{ route('matches.store') }}" class="space-y-3 text-sm">
                @csrf
                <input type="text" name="game" list="games" placeholder="ゲーム名" required value="{{ $game }}" class="w-full rounded-lg border-slate-300 shadow-sm">
                <datalist id="games">@foreach ($games as $g)<option value="{{ $g }}">@endforeach</datalist>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['win' => '勝ち', 'loss' => '負け', 'draw' => '引分'] as $val => $lbl)
                        <label class="cursor-pointer rounded-lg border-2 border-slate-200 py-2 text-center has-[:checked]:border-slate-900 has-[:checked]:bg-slate-900 has-[:checked]:text-white">
                            <input type="radio" name="result" value="{{ $val }}" class="hidden" {{ $loop->first ? 'checked' : '' }}> {{ $lbl }}
                        </label>
                    @endforeach
                </div>
                <input type="text" name="opponent" placeholder="相手(任意)" class="w-full rounded-lg border-slate-300 shadow-sm">
                <div class="flex gap-2">
                    <input type="text" name="score" placeholder="スコア 例:2-1(任意)" class="flex-1 rounded-lg border-slate-300 shadow-sm">
                    <input type="date" name="played_on" value="{{ now()->toDateString() }}" required class="rounded-lg border-slate-300 shadow-sm">
                </div>
                <input type="text" name="note" placeholder="メモ(任意)" class="w-full rounded-lg border-slate-300 shadow-sm">
                <button class="w-full rounded-lg bg-slate-900 py-2 font-semibold text-white hover:bg-slate-700">記録(+2pt)</button>
            </form>
        </div>

        {{-- 全体成績 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm text-center">
            <h3 class="mb-2 font-bold">総合成績</h3>
            <p class="text-4xl font-extrabold {{ $overall['winrate'] >= 50 ? 'text-emerald-600' : 'text-slate-700' }}">{{ $overall['winrate'] }}<span class="text-lg">%</span></p>
            <p class="text-sm text-slate-500">{{ $overall['win'] }}勝 {{ $overall['loss'] }}敗 {{ $overall['draw'] }}分</p>
            @if ($streak >= 2)<p class="mt-2 inline-block rounded-full bg-orange-100 px-3 py-1 text-sm font-bold text-orange-700">🔥 {{ $streak }}連勝中</p>@endif
        </div>
    </div>

    {{-- ゲーム別 & 履歴 --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">🎮 ゲーム別 勝率</h3>
            @forelse ($byGame as $g)
                <a href="{{ route('matches.index', ['game' => $g['game']]) }}" class="mb-3 block">
                    <div class="mb-1 flex justify-between text-sm">
                        <span class="font-medium">{{ $g['game'] }}</span>
                        <span class="text-slate-500">{{ $g['win'] }}勝{{ $g['loss'] }}敗{{ $g['draw'] ? $g['draw'].'分' : '' }} ・ 勝率{{ $g['winrate'] }}%</span>
                    </div>
                    <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full bg-emerald-500" style="width: {{ $g['total'] ? $g['win'] / $g['total'] * 100 : 0 }}%"></div>
                        <div class="h-full bg-slate-300" style="width: {{ $g['total'] ? $g['draw'] / $g['total'] * 100 : 0 }}%"></div>
                        <div class="h-full bg-rose-400" style="width: {{ $g['total'] ? $g['loss'] / $g['total'] * 100 : 0 }}%"></div>
                    </div>
                </a>
            @empty
                <p class="text-sm text-slate-400">まだ戦績がありません。</p>
            @endforelse
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-2 flex items-center justify-between">
                <h3 class="font-bold">履歴 {{ $game ? '（'.$game.'）' : '' }}</h3>
                @if ($game)<a href="{{ route('matches.index') }}" class="text-xs text-slate-500 hover:underline">絞り込み解除</a>@endif
            </div>
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left text-xs text-slate-400"><th class="py-1">日付</th><th>ゲーム</th><th>結果</th><th>相手</th><th>スコア</th><th></th></tr></thead>
                <tbody>
                    @forelse ($records as $r)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5">{{ $r->played_on->format('n/j') }}</td>
                            <td>{{ $r->game }}</td>
                            <td>
                                <span class="rounded px-1.5 py-0.5 text-xs font-bold text-white {{ $r->result === 'win' ? 'bg-emerald-500' : ($r->result === 'loss' ? 'bg-rose-400' : 'bg-slate-400') }}">{{ $r->resultLabel() }}</span>
                            </td>
                            <td class="text-slate-500">{{ $r->opponent }}</td>
                            <td class="text-slate-500">{{ $r->score }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('matches.destroy', $r) }}" onsubmit="return confirm('削除?')">@csrf @method('DELETE')<button class="text-xs text-rose-400 hover:underline">削除</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-center text-slate-400">記録がありません</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
