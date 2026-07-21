@extends('layouts.app')
@section('title', $board->title)

@section('content')
<x-page-header :title="$board->title" icon="🖊️" back="{{ route('whiteboards.index') }}"
    :subtitle="$board->user->name . ' ・ ' . $board->updated_at->format('Y/n/j H:i') . ' 更新'">
    <x-slot:actions>
        @if ($board->user_id === auth()->id() || auth()->user()->is_admin)
            <x-btn href="{{ route('whiteboards.edit', $board) }}" variant="secondary">✏️ 描き足す</x-btn>
            <form method="POST" action="{{ route('whiteboards.destroy', $board) }}"
                  onsubmit="return confirm('このホワイトボードを削除しますか?')">
                @csrf @method('DELETE')
                <button class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100">削除</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <img src="{{ $board->image_data }}" alt="{{ $board->title }}" class="block w-full">
</div>
<p class="mt-2 text-center text-xs text-slate-400">画像を長押し / 右クリックで保存もできます</p>
@endsection
