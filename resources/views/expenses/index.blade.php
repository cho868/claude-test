@extends('layouts.app')
@section('title', '家計簿')

@php
    $prev = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth()->format('Y-m');
    $all = $categories['expense'] + $categories['income'];
    $yen = fn ($n) => '¥' . number_format($n);
@endphp

@section('content')
<x-page-header title="家計簿" icon="💰" subtitle="記録は自分だけに見えます（共有した支出のみ身内に表示）">
    <x-slot:actions>
        <a href="{{ route('expenses.index', ['m' => $prev]) }}" class="rounded-lg bg-white px-3 py-2 text-sm shadow-sm hover:bg-slate-50">←</a>
        <span class="px-2 font-bold">{{ $month->format('Y年n月') }}</span>
        <a href="{{ route('expenses.index', ['m' => $next]) }}" class="rounded-lg bg-white px-3 py-2 text-sm shadow-sm hover:bg-slate-50">→</a>
    </x-slot:actions>
</x-page-header>

{{-- サマリー --}}
<div class="mb-4 grid gap-3 sm:grid-cols-3">
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-400">収入</p>
        <p class="text-2xl font-extrabold text-emerald-600">{{ $yen($income) }}</p>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-400">支出</p>
        <p class="text-2xl font-extrabold text-rose-600">{{ $yen($expense) }}</p>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm">
        <p class="text-xs text-slate-400">収支</p>
        <p class="text-2xl font-extrabold {{ $balance >= 0 ? 'text-slate-800' : 'text-rose-600' }}">
            {{ $balance >= 0 ? '+' : '' }}{{ $yen($balance) }}
        </p>
    </div>
</div>

{{-- 予算 --}}
<div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-bold">🎯 今月の予算</h3>
        <form method="POST" action="{{ route('expenses.budget') }}" class="flex items-center gap-2">
            @csrf
            <input type="number" name="monthly_budget" value="{{ $budget }}" min="0" placeholder="未設定"
                   class="w-32 rounded-lg border-slate-300 text-sm shadow-sm">
            <button class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold hover:bg-slate-200">保存</button>
        </form>
    </div>
    @if ($budget)
        @php $pct = min(100, (int) round($expense / max(1, $budget) * 100)); $over = $expense > $budget; @endphp
        <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full {{ $over ? 'bg-rose-500' : ($pct >= 80 ? 'bg-amber-400' : 'bg-emerald-500') }}"
                 style="width: {{ $pct }}%"></div>
        </div>
        <p class="mt-1 text-sm {{ $over ? 'font-bold text-rose-600' : 'text-slate-500' }}">
            {{ $yen($expense) }} / {{ $yen($budget) }}
            @if ($over) （{{ $yen($expense - $budget) }} 超過）@else （残り {{ $yen($budget - $expense) }}）@endif
        </p>
    @else
        <p class="mt-2 text-sm text-slate-400">予算を設定すると使いすぎが一目で分かります。</p>
    @endif
</div>

<div class="grid gap-4 lg:grid-cols-3">
    {{-- 入力フォーム --}}
    <div class="lg:col-span-1">
        <div class="rounded-2xl bg-white p-5 shadow-sm" x-data="{ kind: 'expense' }">
            <h3 class="mb-3 font-bold">✏️ 記録する</h3>
            <form method="POST" action="{{ route('expenses.store') }}" class="space-y-3">
                @csrf
                <div class="flex gap-1.5">
                    <button type="button" @click="kind = 'expense'"
                            :class="kind === 'expense' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600'"
                            class="flex-1 rounded-lg px-3 py-2 text-sm font-bold">− 支出</button>
                    <button type="button" @click="kind = 'income'"
                            :class="kind === 'income' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'"
                            class="flex-1 rounded-lg px-3 py-2 text-sm font-bold">＋ 収入</button>
                </div>
                <input type="hidden" name="kind" :value="kind">

                <div>
                    <label class="block text-xs text-slate-500">金額</label>
                    <div class="mt-1 flex items-center gap-1">
                        <span class="text-lg">¥</span>
                        <input type="number" name="amount" required min="1" inputmode="numeric" placeholder="1000"
                               class="w-full rounded-lg border-slate-300 text-lg font-bold shadow-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-500">日付</label>
                    <input type="date" name="spent_on" required value="{{ now()->format('Y-m-d') }}"
                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
                </div>

                <div>
                    <label class="block text-xs text-slate-500">カテゴリ</label>
                    <div class="mt-1 grid grid-cols-3 gap-1" x-show="kind === 'expense'">
                        @foreach ($categories['expense'] as $key => [$icon, $label])
                            <label class="cursor-pointer">
                                <input type="radio" name="category" value="{{ $key }}" class="peer sr-only" @checked($loop->first)>
                                <div class="rounded-lg border border-slate-200 px-1 py-1.5 text-center text-xs peer-checked:border-rose-400 peer-checked:bg-rose-50">
                                    <div>{{ $icon }}</div>{{ $label }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-1 grid grid-cols-3 gap-1" x-show="kind === 'income'" x-cloak>
                        @foreach ($categories['income'] as $key => [$icon, $label])
                            <label class="cursor-pointer">
                                <input type="radio" name="category" value="{{ $key }}" class="peer sr-only">
                                <div class="rounded-lg border border-slate-200 px-1 py-1.5 text-center text-xs peer-checked:border-emerald-400 peer-checked:bg-emerald-50">
                                    <div>{{ $icon }}</div>{{ $label }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-500">メモ（任意）</label>
                    <input type="text" name="memo" maxlength="100" placeholder="スーパーで買い物"
                           class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
                </div>

                <label class="flex items-center gap-2 text-xs text-slate-600" x-show="kind === 'expense'">
                    <input type="checkbox" name="is_shared" value="1" class="rounded border-slate-300">
                    身内に共有する（共同購入・割り勘の把握用）
                </label>

                <x-btn type="submit" class="w-full">記録する</x-btn>
            </form>
        </div>
    </div>

    <div class="space-y-4 lg:col-span-2">
        {{-- カテゴリ別 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">📊 何に使った？</h3>
            @if ($byCategory->isEmpty())
                <p class="text-sm text-slate-400">まだ支出の記録がありません。</p>
            @else
                @php $max = $byCategory->max(); @endphp
                <div class="space-y-2">
                    @foreach ($byCategory as $cat => $sum)
                        @php [$icon, $label] = $all[$cat] ?? ['📦', $cat]; @endphp
                        <div>
                            <div class="mb-0.5 flex justify-between text-sm">
                                <span>{{ $icon }} {{ $label }}</span>
                                <span class="font-semibold">
                                    {{ $yen($sum) }}
                                    <span class="ml-1 text-xs text-slate-400">{{ round($sum / max(1, $expense) * 100) }}%</span>
                                </span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-rose-400" style="width: {{ round($sum / $max * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- 推移 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">📈 直近6か月の推移</h3>
            @php $tmax = max(1, $trend->max(fn ($t) => max($t['expense'], $t['income']))); @endphp
            <div class="flex items-end justify-between gap-2" style="height:140px">
                @foreach ($trend as $t)
                    <div class="flex flex-1 flex-col items-center justify-end gap-1" style="height:100%">
                        <div class="flex w-full items-end justify-center gap-0.5" style="height:100%">
                            <div class="w-1/2 rounded-t bg-emerald-400" style="height: {{ round($t['income'] / $tmax * 100) }}%"
                                 title="収入 {{ $yen($t['income']) }}"></div>
                            <div class="w-1/2 rounded-t bg-rose-400" style="height: {{ round($t['expense'] / $tmax * 100) }}%"
                                 title="支出 {{ $yen($t['expense']) }}"></div>
                        </div>
                        <span class="text-xs text-slate-400">{{ $t['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-center text-xs text-slate-400">
                <span class="text-emerald-500">■</span> 収入 　<span class="text-rose-400">■</span> 支出
            </p>
        </div>

        {{-- 明細 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">🧾 {{ $month->format('n月') }}の明細（{{ $items->count() }}件）</h3>
            @forelse ($items as $e)
                @php [$icon, $label] = $all[$e->category] ?? ['📦', $e->category]; @endphp
                <div class="flex items-center gap-3 border-b py-2 text-sm last:border-0">
                    <span class="w-12 shrink-0 text-xs text-slate-400">{{ $e->spent_on->format('n/j') }}</span>
                    <span class="shrink-0">{{ $icon }}</span>
                    <div class="min-w-0 flex-1">
                        <span class="text-xs text-slate-500">{{ $label }}</span>
                        @if ($e->memo)<span class="ml-1 truncate">{{ $e->memo }}</span>@endif
                        @if ($e->is_shared)<span class="ml-1 rounded-full bg-sky-100 px-1.5 py-0.5 text-xs text-sky-700">共有</span>@endif
                    </div>
                    <span class="shrink-0 font-bold {{ $e->kind === 'income' ? 'text-emerald-600' : 'text-slate-700' }}">
                        {{ $e->kind === 'income' ? '+' : '−' }}{{ $yen($e->amount) }}
                    </span>
                    <form method="POST" action="{{ route('expenses.destroy', $e) }}" onsubmit="return confirm('削除しますか?')">
                        @csrf @method('DELETE')
                        <button class="shrink-0 text-xs text-slate-300 hover:text-rose-500">✕</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-slate-400">この月の記録はまだありません。</p>
            @endforelse
        </div>

        {{-- 共有支出 --}}
        @if ($shared->isNotEmpty())
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-bold">👥 みんなの共有支出</h3>
                    <span class="text-sm font-bold">{{ $yen($sharedTotal) }}</span>
                </div>
                @foreach ($shared as $e)
                    @php [$icon, $label] = $all[$e->category] ?? ['📦', $e->category]; @endphp
                    <div class="flex items-center gap-2 border-b py-1.5 text-sm last:border-0">
                        <span class="w-12 shrink-0 text-xs text-slate-400">{{ $e->spent_on->format('n/j') }}</span>
                        <x-avatar :user="$e->user" :size="20" />
                        <span class="shrink-0 text-xs text-slate-500">{{ $e->user->name }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $icon }} {{ $e->memo ?: $label }}</span>
                        <span class="shrink-0 font-semibold">{{ $yen($e->amount) }}</span>
                    </div>
                @endforeach
                <p class="mt-2 text-xs text-slate-400">
                    合計 {{ $yen($sharedTotal) }} ／ {{ $shared->pluck('user_id')->unique()->count() }}人で割ると
                    <b>1人あたり {{ $yen((int) round($sharedTotal / max(1, $shared->pluck('user_id')->unique()->count()))) }}</b>
                </p>
            </div>
        @endif
    </div>
</div>
@endsection
