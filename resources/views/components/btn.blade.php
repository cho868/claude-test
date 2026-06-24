@props(['href' => null, 'variant' => 'primary', 'type' => 'submit'])

@php
    $styles = [
        'primary'   => 'bg-slate-900 text-white hover:bg-slate-700',
        'secondary' => 'bg-slate-100 text-slate-700 hover:bg-slate-200',
        'success'   => 'bg-emerald-600 text-white hover:bg-emerald-500',
        'danger'    => 'bg-rose-100 text-rose-700 hover:bg-rose-200',
    ];
    $base = 'inline-flex items-center justify-center gap-1 rounded-lg px-4 py-2 text-sm font-semibold transition';
    $cls = $base.' '.($styles[$variant] ?? $styles['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</button>
@endif
