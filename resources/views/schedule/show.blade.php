@extends('layouts.app')
@section('title', $event->title)

@section('content')
@php
    $isOwner = $event->user_id === auth()->id() || auth()->user()->is_admin;
    $statuses = ['yes' => ['参加', 'emerald'], 'maybe' => ['未定', 'amber'], 'no' => ['不参加', 'rose']];
@endphp

<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-start justify-between">
        <div>
            <h2 class="text-2xl font-bold">{{ $event->title }}</h2>
            <p class="text-sm text-slate-500">
                🕒 {{ $event->starts_at->format('Y/n/j (D) H:i') }}
                @if ($event->ends_at) 〜 {{ $event->ends_at->format('H:i') }}@endif
            </p>
            @if ($event->location)<p class="text-sm text-slate-500">📍 {{ $event->location }}</p>@endif
        </div>
        @if ($isOwner)
            <form method="POST" action="{{ route('schedule.destroy', $event) }}" onsubmit="return confirm('削除しますか?')">
                @csrf @method('DELETE')
                <button class="rounded-lg bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">削除</button>
            </form>
        @endif
    </div>

    @if ($event->description)
        <p class="mb-4 whitespace-pre-wrap rounded-2xl bg-white p-4 text-sm text-slate-600 shadow-sm">{{ $event->description }}</p>
    @endif

    {{-- 出欠登録 --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">あなたの出欠</h3>
        <form method="POST" action="{{ route('schedule.attend', $event) }}" class="space-y-3">
            @csrf
            <div class="flex gap-2">
                @foreach ($statuses as $key => [$label, $color])
                    <label class="flex-1 cursor-pointer rounded-lg border-2 border-slate-200 py-2 text-center text-sm has-[:checked]:border-{{ $color }}-400 has-[:checked]:bg-{{ $color }}-50">
                        <input type="radio" name="status" value="{{ $key }}" class="hidden" {{ optional($myAttendance)->status === $key ? 'checked' : '' }}>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <input type="text" name="comment" value="{{ optional($myAttendance)->comment }}" placeholder="ひとこと(任意)" class="w-full rounded-lg border-slate-300 shadow-sm text-sm">
            <button class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-700">登録</button>
        </form>
    </div>

    {{-- 出欠一覧 --}}
    <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">出欠状況</h3>
        @forelse ($statuses as $key => [$label, $color])
            @php $members = $event->attendances->where('status', $key); @endphp
            <div class="mb-2">
                <span class="text-sm font-semibold text-{{ $color }}-600">{{ $label }} ({{ $members->count() }})</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($members as $a)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">
                            {{ $a->user->name }}@if ($a->comment)<span class="text-slate-400"> – {{ $a->comment }}</span>@endif
                        </span>
                    @endforeach
                </div>
            </div>
        @empty
        @endforelse
        @if ($event->attendances->isEmpty())
            <p class="text-sm text-slate-400">まだ出欠登録がありません。</p>
        @endif
    </div>
</div>
@endsection
