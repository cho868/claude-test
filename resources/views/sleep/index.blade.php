@extends('layouts.app')
@section('title', '睡眠時間チェック')

@section('content')
<h2 class="mb-4 text-2xl font-bold">😴 睡眠時間チェック</h2>

<div class="grid gap-6 lg:grid-cols-3">
    {{-- 記録フォーム --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">✅ 睡眠を記録</h3>
        <form method="POST" action="{{ route('sleep.store') }}" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block font-medium text-slate-700">日付(起きた日)</label>
                <input type="date" name="sleep_date" value="{{ now()->toDateString() }}" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block font-medium text-slate-700">就寝</label>
                <input type="datetime-local" name="bed_at" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block font-medium text-slate-700">起床</label>
                <input type="datetime-local" name="wake_at" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <input type="text" name="note" placeholder="メモ(任意)" class="w-full rounded-lg border-slate-300 shadow-sm">
            <button class="w-full rounded-lg bg-slate-900 py-2 font-semibold text-white hover:bg-slate-700">記録する (+5pt)</button>
        </form>
    </div>

    {{-- グラフ & 統計 --}}
    <div class="lg:col-span-2">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-bold">直近7日の睡眠</h3>
                <span class="text-sm text-slate-500">平均 <b>{{ intdiv($avgMinutes, 60) }}時間{{ $avgMinutes % 60 }}分</b></span>
            </div>
            <div class="flex h-40 items-end justify-around gap-2 border-b border-slate-200 pb-1">
                @forelse ($chart as $d)
                    <div class="flex flex-1 flex-col items-center justify-end">
                        <span class="text-xs text-slate-500">{{ $d['hours'] }}h</span>
                        <div class="w-full rounded-t {{ $d['hours'] >= 7 ? 'bg-emerald-400' : ($d['hours'] >= 5 ? 'bg-amber-400' : 'bg-rose-400') }}"
                             style="height: {{ min(100, $d['hours'] / 12 * 100) }}%"></div>
                        <span class="mt-1 text-xs text-slate-400">{{ $d['date'] }}</span>
                    </div>
                @empty
                    <p class="w-full text-center text-sm text-slate-400">記録がありません</p>
                @endforelse
            </div>
        </div>

        {{-- 履歴 --}}
        <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-2 font-bold">記録一覧</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs text-slate-400">
                        <th class="py-1">日付</th><th>就寝</th><th>起床</th><th>睡眠</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $r)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5">{{ $r->sleep_date->format('n/j') }}</td>
                            <td>{{ $r->bed_at->format('H:i') }}</td>
                            <td>{{ $r->wake_at->format('H:i') }}</td>
                            <td class="font-semibold">{{ $r->hoursLabel() }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('sleep.destroy', $r) }}" onsubmit="return confirm('削除しますか?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-400 hover:underline">削除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-center text-slate-400">記録がありません</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
