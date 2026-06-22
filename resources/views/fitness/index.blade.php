@extends('layouts.app')
@section('title', 'フィットネス')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">💪 フィットネス</h2>
    <a href="{{ route('challenges.index') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">🏁 チャレンジ一覧</a>
</div>

{{-- ソロ目標 --}}
<div class="mb-6 rounded-2xl bg-white p-5 shadow-sm">
    <div class="grid gap-5 md:grid-cols-2">
        {{-- 進捗 --}}
        <div>
            <h3 class="mb-3 font-bold">🎯 マイ目標(1人でもOK)</h3>
            @php $cur = $latestWeight?->weight_kg ? (float) $latestWeight->weight_kg : null; @endphp
            {{-- 体重目標 --}}
            <div class="mb-3">
                <div class="mb-1 flex justify-between text-sm">
                    <span>体重</span>
                    <span class="text-slate-500">
                        @if ($cur !== null) 現在 {{ $cur }}kg @else 未記録 @endif
                        @if ($targetWeight) / 目標 {{ $targetWeight }}kg @endif
                    </span>
                </div>
                @php
                    $wp = 0;
                    if ($targetWeight && $cur !== null && $startWeight !== null && abs($startWeight - $targetWeight) > 0.01) {
                        $wp = (int) min(100, max(0, round(($startWeight - $cur) / ($startWeight - $targetWeight) * 100)));
                    } elseif ($targetWeight && $cur !== null && $cur <= $targetWeight) {
                        $wp = 100;
                    }
                @endphp
                <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-blue-500" style="width: {{ $targetWeight ? $wp : 0 }}%"></div>
                </div>
                @if ($targetWeight && $cur !== null)
                    <p class="mt-1 text-xs text-slate-500">
                        @if ($cur <= $targetWeight) 🎉 目標達成！ @else 目標まであと {{ round($cur - $targetWeight, 1) }}kg @endif
                    </p>
                @endif
            </div>
            {{-- 週間運動目標 --}}
            <div>
                <div class="mb-1 flex justify-between text-sm">
                    <span>今週の運動</span>
                    <span class="text-slate-500">{{ $weekMinutes }}分 @if ($weeklyGoal) / 目標 {{ $weeklyGoal }}分 @endif</span>
                </div>
                @php $ep = $weeklyGoal ? (int) min(100, round($weekMinutes / max(1, $weeklyGoal) * 100)) : 0; @endphp
                <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-orange-500" style="width: {{ $ep }}%"></div>
                </div>
                @if ($weeklyGoal)
                    <p class="mt-1 text-xs text-slate-500">{{ $weekMinutes >= $weeklyGoal ? '🎉 今週の目標クリア！' : '達成率 ' . $ep . '%' }}</p>
                @endif
            </div>
        </div>
        {{-- 目標設定 --}}
        <form method="POST" action="{{ route('fitness.goal') }}" class="space-y-3 rounded-xl bg-slate-50 p-4 text-sm">
            @csrf
            <p class="font-semibold text-slate-600">目標を設定</p>
            <div class="flex items-center gap-2">
                <label class="w-28">目標体重</label>
                <input type="number" step="0.1" name="target_weight_kg" value="{{ $targetWeight }}" placeholder="例 62.0" class="w-28 rounded-lg border-slate-300 shadow-sm"> kg
            </div>
            <div class="flex items-center gap-2">
                <label class="w-28">週の運動目標</label>
                <input type="number" name="weekly_exercise_goal" value="{{ $weeklyGoal }}" placeholder="例 150" class="w-28 rounded-lg border-slate-300 shadow-sm"> 分
            </div>
            <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700">目標を保存</button>
            <p class="text-xs text-slate-400">空欄にすると目標を解除できます。</p>
        </form>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    {{-- 記録フォーム --}}
    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">⚖️ 体重を記録</h3>
            <form method="POST" action="{{ route('fitness.weight.store') }}" class="space-y-3 text-sm">
                @csrf
                <input type="date" name="recorded_on" value="{{ now()->toDateString() }}" required class="w-full rounded-lg border-slate-300 shadow-sm">
                <div class="flex items-center gap-2">
                    <input type="number" step="0.1" name="weight_kg" placeholder="体重" required
                           value="{{ $latestWeight?->weight_kg }}" class="w-28 rounded-lg border-slate-300 shadow-sm"> kg
                </div>
                <input type="text" name="note" placeholder="メモ(任意)" class="w-full rounded-lg border-slate-300 shadow-sm">
                <button class="w-full rounded-lg bg-slate-900 py-2 font-semibold text-white hover:bg-slate-700">記録(+3pt)</button>
            </form>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">🏃 運動を記録</h3>
            <form method="POST" action="{{ route('fitness.exercise.store') }}" class="space-y-3 text-sm">
                @csrf
                <input type="date" name="recorded_on" value="{{ now()->toDateString() }}" required class="w-full rounded-lg border-slate-300 shadow-sm">
                <input type="text" name="activity" list="acts" placeholder="種目(ランニング等)" required class="w-full rounded-lg border-slate-300 shadow-sm">
                <datalist id="acts"><option value="ランニング"><option value="ウォーキング"><option value="筋トレ"><option value="サイクリング"><option value="水泳"></datalist>
                <div class="flex gap-2">
                    <input type="number" name="minutes" placeholder="分" min="1" required class="w-20 rounded-lg border-slate-300 shadow-sm">
                    <input type="number" name="calories" placeholder="kcal(任意)" min="0" class="flex-1 rounded-lg border-slate-300 shadow-sm">
                </div>
                <button class="w-full rounded-lg bg-slate-900 py-2 font-semibold text-white hover:bg-slate-700">記録(+3pt)</button>
            </form>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-2 font-bold">🏁 開催中/募集中のチャレンジ</h3>
            @forelse ($challenges as $c)
                <a href="{{ route('challenges.show', $c) }}" class="block border-b py-2 text-sm last:border-0 hover:text-slate-900">
                    <span class="font-medium">{{ $c->title }}</span>
                    <span class="block text-xs text-slate-500">{{ $c->metricLabel() }} ・ {{ $c->participants->count() }}人 ・ 〜{{ $c->ends_on->format('n/j') }}</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">開催中のチャレンジはありません。</p>
            @endforelse
        </div>
    </div>

    {{-- グラフ & 記録一覧 --}}
    <div class="space-y-6 lg:col-span-2">
        {{-- 体重推移 折れ線グラフ --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">📉 体重の推移</h3>
            @php
                $pts = $weightChart;
                $vals = $pts->pluck('kg');
                $min = $vals->min(); $max = $vals->max();
                $range = max(0.1, $max - $min);
                $W = 600; $H = 160; $pad = 10;
            @endphp
            @if ($pts->count() >= 2)
                <svg viewBox="0 0 {{ $W }} {{ $H }}" class="w-full" preserveAspectRatio="none" style="height:180px">
                    @php
                        $n = $pts->count();
                        $coords = $pts->values()->map(function ($p, $i) use ($n, $min, $range, $W, $H, $pad) {
                            $x = $pad + ($n <= 1 ? 0 : $i * (($W - 2 * $pad) / ($n - 1)));
                            $y = $H - $pad - (($p['kg'] - $min) / $range) * ($H - 2 * $pad);
                            return round($x, 1) . ',' . round($y, 1);
                        })->implode(' ');
                    @endphp
                    <polyline points="{{ $coords }}" fill="none" stroke="#3b82f6" stroke-width="2"/>
                    @foreach ($pts as $i => $p)
                        @php
                            $x = $pad + ($i * (($W - 2 * $pad) / max(1, $pts->count() - 1)));
                            $y = $H - $pad - (($p['kg'] - $min) / $range) * ($H - 2 * $pad);
                        @endphp
                        <circle cx="{{ round($x,1) }}" cy="{{ round($y,1) }}" r="2.5" fill="#3b82f6"/>
                    @endforeach
                </svg>
                <div class="flex justify-between text-xs text-slate-400">
                    <span>{{ $pts->first()['date'] }}（{{ $pts->first()['kg'] }}kg）</span>
                    <span>最新 {{ $pts->last()['kg'] }}kg</span>
                </div>
            @else
                <p class="text-sm text-slate-400">2日分以上記録するとグラフが出ます。</p>
            @endif
        </div>

        {{-- 運動サマリー --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-bold">🔥 運動量</h3>
                <span class="text-sm text-slate-500">直近7日 <b>{{ intdiv($weekMinutes,60) }}時間{{ $weekMinutes%60 }}分</b></span>
            </div>
            @forelse ($byActivity as $a)
                @php $max = $byActivity->max('total'); $pct = $max > 0 ? round($a->total / $max * 100) : 0; @endphp
                <div class="mb-2">
                    <div class="mb-0.5 flex justify-between text-sm"><span>{{ $a->activity }}</span><span class="text-slate-500">{{ $a->total }}分</span></div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-orange-500" style="width: {{ $pct }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-slate-400">まだ運動記録がありません。</p>
            @endforelse
        </div>

        {{-- 記録一覧（体重・運動） --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <h3 class="mb-2 font-bold">体重ログ</h3>
                <table class="w-full text-sm">
                    @forelse ($weights->take(12) as $r)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5">{{ $r->recorded_on->format('n/j') }}</td>
                            <td class="font-semibold">{{ $r->weight_kg }}kg</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('fitness.weight.destroy', $r) }}" onsubmit="return confirm('削除?')">
                                    @csrf @method('DELETE')<button class="text-xs text-rose-400 hover:underline">削除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-3 text-center text-slate-400">記録なし</td></tr>
                    @endforelse
                </table>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <h3 class="mb-2 font-bold">運動ログ</h3>
                <table class="w-full text-sm">
                    @forelse ($exercises->take(12) as $r)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5">{{ $r->recorded_on->format('n/j') }}</td>
                            <td>{{ $r->activity }}</td>
                            <td>{{ $r->minutes }}分</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('fitness.exercise.destroy', $r) }}" onsubmit="return confirm('削除?')">
                                    @csrf @method('DELETE')<button class="text-xs text-rose-400 hover:underline">削除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-3 text-center text-slate-400">記録なし</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
