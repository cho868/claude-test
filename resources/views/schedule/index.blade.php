@extends('layouts.app')
@section('title', 'スケジュール')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">📅 スケジュール共有</h2>
    <a href="{{ route('schedule.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">＋ 予定追加</a>
</div>

<h3 class="mb-2 font-bold">今後の予定</h3>
<div class="space-y-3">
    @forelse ($upcoming as $event)
        <a href="{{ route('schedule.show', $event) }}" class="block rounded-2xl bg-white p-4 shadow-sm hover:shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-bold">{{ $event->title }}</h4>
                    <p class="text-sm text-slate-500">
                        🕒 {{ $event->starts_at->format('n/j (D) H:i') }}
                        @if ($event->location) ・ 📍 {{ $event->location }}@endif
                    </p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    @php $yes = $event->attendances->where('status', 'yes')->count(); @endphp
                    参加 {{ $yes }}人
                </div>
            </div>
        </a>
    @empty
        <p class="text-slate-400">今後の予定はありません。</p>
    @endforelse
</div>

@if ($past->isNotEmpty())
    <h3 class="mb-2 mt-6 font-bold text-slate-400">過去の予定</h3>
    <div class="space-y-2">
        @foreach ($past as $event)
            <a href="{{ route('schedule.show', $event) }}" class="block rounded-xl bg-white/60 p-3 text-sm text-slate-500 hover:bg-white">
                {{ $event->starts_at->format('n/j') }} ・ {{ $event->title }}
            </a>
        @endforeach
    </div>
@endif
@endsection
