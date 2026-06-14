@extends('layouts.app')
@section('title', $survey->title)

@section('content')
@php
    $isOwner = $survey->user_id === auth()->id() || auth()->user()->is_admin;
    $total = $survey->totalVotes();
@endphp

<div class="mx-auto max-w-2xl">
    <div class="mb-4 flex items-start justify-between">
        <div>
            <h2 class="text-2xl font-bold">{{ $survey->title }}</h2>
            <p class="text-sm text-slate-500">{{ $survey->user->name }} ・ {{ $total }}票
                @if ($survey->is_closed)<span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs">締切</span>@endif
            </p>
            @if ($survey->description)<p class="mt-2 text-sm text-slate-600">{{ $survey->description }}</p>@endif
        </div>
        @if ($isOwner)
            <div class="flex gap-2">
                <form method="POST" action="{{ route('surveys.close', $survey) }}">
                    @csrf
                    <button class="rounded-lg bg-slate-100 px-3 py-2 text-sm hover:bg-slate-200">{{ $survey->is_closed ? '再開' : '締切' }}</button>
                </form>
                <form method="POST" action="{{ route('surveys.destroy', $survey) }}" onsubmit="return confirm('削除しますか?')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">削除</button>
                </form>
            </div>
        @endif
    </div>

    {{-- 投票フォーム --}}
    @unless ($survey->is_closed)
        <form method="POST" action="{{ route('surveys.vote', $survey) }}" class="space-y-2 rounded-2xl bg-white p-5 shadow-sm">
            @csrf
            <p class="mb-2 text-sm font-semibold">{{ $survey->multiple_choice ? '複数選択できます' : '1つ選んでください' }}</p>
            @foreach ($survey->options as $option)
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                    <input type="{{ $survey->multiple_choice ? 'checkbox' : 'radio' }}" name="options[]" value="{{ $option->id }}"
                           class="border-slate-300" {{ in_array($option->id, $myVotes) ? 'checked' : '' }}>
                    <span>{{ $option->label }}</span>
                </label>
            @endforeach
            <button class="mt-2 rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-700">投票する</button>
        </form>
    @endunless

    {{-- 結果 --}}
    <div class="mt-4 rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">📊 結果</h3>
        <div class="space-y-3">
            @foreach ($survey->options as $option)
                @php
                    $count = $option->votes->count();
                    $pct = $total > 0 ? round($count / $total * 100) : 0;
                @endphp
                <div>
                    <div class="mb-1 flex justify-between text-sm">
                        <span class="{{ in_array($option->id, $myVotes) ? 'font-bold text-slate-900' : '' }}">
                            {{ $option->label }} {{ in_array($option->id, $myVotes) ? '✓' : '' }}
                        </span>
                        <span class="text-slate-500">{{ $count }}票 ({{ $pct }}%)</span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
