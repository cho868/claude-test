@props(['user', 'size' => 40])
@php
    $px = (int) $size;
    $style = $user->avatar_style ?? 'emoji';
@endphp
@if ($style === 'dicebear')
    <img src="{{ $user->avatarDicebearUrl() }}" alt="{{ $user->name }}"
         class="shrink-0 rounded-full bg-white object-cover ring-1 ring-slate-200"
         style="width: {{ $px }}px; height: {{ $px }}px;" loading="lazy">
@else
    <span class="inline-flex shrink-0 items-center justify-center rounded-full font-bold text-white ring-1 ring-black/5"
          style="width: {{ $px }}px; height: {{ $px }}px; background-color: {{ $user->avatar_color ?: '#6366f1' }}; font-size: {{ round($px * 0.5) }}px;">
        {{ $user->avatar_emoji ?: $user->initial() }}
    </span>
@endif
