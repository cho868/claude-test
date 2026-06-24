@extends('layouts.app')
@section('title', '睡眠時間チェック')

@section('content')
<x-page-header title="睡眠時間チェック" icon="😴" />

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

        {{-- 履歴（日ごと・分割睡眠は合算） --}}
        <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-2 font-bold">記録一覧</h3>
            <div class="space-y-3">
                @forelse ($days as $d)
                    @php $h = intdiv($d['total'],60); $m = $d['total']%60; @endphp
                    <div class="rounded-xl border border-slate-100 p-3">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="font-semibold">{{ $d['date']->format('n/j (D)') }}</span>
                            <span class="text-sm">
                                合計 <b>{{ $h }}時間{{ str_pad($m,2,'0',STR_PAD_LEFT) }}分</b>
                                @if ($d['segments']->count() > 1)<span class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">{{ $d['segments']->count() }}回に分割</span>@endif
                            </span>
                        </div>
                        @foreach ($d['segments'] as $r)
                            <div class="flex items-center justify-between border-t py-1 text-sm text-slate-600 first:border-0">
                                <span>🛏 {{ $r->bed_at->format('n/j H:i') }} → ☀️ {{ $r->wake_at->format('n/j H:i') }}（{{ $r->hoursLabel() }}）@if($r->note)<span class="text-xs text-slate-400">／{{ $r->note }}</span>@endif</span>
                                <form method="POST" action="{{ route('sleep.destroy', $r) }}" onsubmit="return confirm('この区間を削除しますか?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-400 hover:underline">削除</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="py-3 text-center text-slate-400">記録がありません</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
