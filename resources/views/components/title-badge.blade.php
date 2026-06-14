@props(['title'])
@if ($title)
    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold text-white"
          style="background-color: {{ $title->color }}">
        {{ $title->icon }} {{ $title->name }}
    </span>
@else
    <span class="inline-flex items-center gap-1 rounded-full bg-slate-400 px-2.5 py-0.5 text-xs font-bold text-white">🔰 称号なし</span>
@endif
