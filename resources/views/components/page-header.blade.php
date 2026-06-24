@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-0.5 inline-block text-sm text-slate-500 hover:underline">← 戻る</a>
        @endif
        <h2 class="text-2xl font-bold tracking-tight">{{ $icon ? $icon.' ' : '' }}{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
