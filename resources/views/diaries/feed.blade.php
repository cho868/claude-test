@extends('layouts.app')
@section('title', 'みんなの日記')

@section('content')
<x-page-header title="みんなの日記" icon="👥" back="{{ route('diaries.index') }}"
    subtitle="「身内に共有」にした日記だけが並びます。">
    <x-slot:actions>
        <x-btn href="{{ route('diaries.create') }}">✍️ 日記を書く</x-btn>
    </x-slot:actions>
</x-page-header>

@if ($entries->isEmpty())
    <div class="rounded-2xl bg-white p-10 text-center shadow-sm">
        <p class="text-4xl">👥</p>
        <p class="mt-2 text-slate-500">共有された日記はまだありません。</p>
        <a href="{{ route('diaries.create') }}" class="mt-3 inline-block font-semibold text-slate-900 hover:underline">最初の一本を書く →</a>
    </div>
@else
    <div class="space-y-3">
        @foreach ($entries as $entry)
            <a href="{{ route('diaries.show', $entry) }}"
               class="group block rounded-2xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start gap-3">
                    <x-avatar :user="$entry->user" :size="36" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-slate-400">
                            {{ $entry->user->name }} ・ {{ $entry->entry_date->format('Y/n/j (D)') }}
                        </p>
                        <p class="truncate font-semibold group-hover:text-slate-900">
                            {{ $entry->moodIcon() }}{{ $entry->weatherIcon() }} {{ $entry->displayTitle() }}
                        </p>
                        <p class="mt-0.5 line-clamp-2 text-sm text-slate-500">{{ $entry->excerpt() }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="mt-5">{{ $entries->links() }}</div>
@endif
@endsection
