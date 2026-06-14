@extends('layouts.app')
@section('title', 'メモ')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">📝 メモ</h2>
    <div class="flex gap-1 text-sm">
        @foreach (['gmq2' => 'GMQ2', 'general' => '汎用'] as $key => $label)
            <a href="{{ route('memos.index', ['category' => $key]) }}"
               class="rounded-lg px-3 py-1.5 {{ $category === $key ? 'bg-slate-900 text-white' : 'bg-white' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        {{-- 新規メモ --}}
        <details class="mb-4 rounded-2xl bg-white p-4 shadow-sm" open>
            <summary class="cursor-pointer font-semibold">＋ 新しいメモ</summary>
            <form method="POST" action="{{ route('memos.store') }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="category" value="{{ $category }}">
                <input type="text" name="title" placeholder="タイトル" required class="w-full rounded-lg border-slate-300 shadow-sm">
                <textarea name="body" rows="4" placeholder="内容..." class="w-full rounded-lg border-slate-300 shadow-sm"></textarea>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_public" value="1" class="rounded border-slate-300"> 身内に公開する
                </label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">保存</button>
            </form>
        </details>

        {{-- 自分のメモ --}}
        <h3 class="mb-2 font-bold">自分のメモ</h3>
        <div class="space-y-3">
            @forelse ($memos as $memo)
                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <details>
                        <summary class="flex cursor-pointer items-center justify-between font-semibold">
                            <span>{{ $memo->title }} @if ($memo->is_public)<span class="ml-1 text-xs text-emerald-600">公開</span>@endif</span>
                            <span class="text-xs text-slate-400">{{ $memo->updated_at->format('n/j') }}</span>
                        </summary>
                        <form method="POST" action="{{ route('memos.update', $memo) }}" class="mt-3 space-y-2">
                            @csrf @method('PUT')
                            <input type="text" name="title" value="{{ $memo->title }}" required class="w-full rounded-lg border-slate-300 shadow-sm text-sm">
                            <textarea name="body" rows="4" class="w-full rounded-lg border-slate-300 shadow-sm text-sm">{{ $memo->body }}</textarea>
                            <label class="flex items-center gap-2 text-xs">
                                <input type="checkbox" name="is_public" value="1" class="rounded border-slate-300" {{ $memo->is_public ? 'checked' : '' }}> 公開
                            </label>
                            <div class="flex gap-2">
                                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700">更新</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('memos.destroy', $memo) }}" class="mt-2" onsubmit="return confirm('削除しますか?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-rose-500 hover:underline">削除</button>
                        </form>
                    </details>
                </div>
            @empty
                <p class="text-sm text-slate-400">まだメモがありません。</p>
            @endforelse
        </div>
    </div>

    {{-- 共有メモ --}}
    <div>
        <h3 class="mb-2 font-bold">🌐 みんなのメモ</h3>
        <div class="space-y-3">
            @forelse ($shared as $memo)
                <div class="rounded-2xl bg-white p-4 shadow-sm">
                    <details>
                        <summary class="cursor-pointer text-sm font-semibold">{{ $memo->title }}
                            <span class="text-xs font-normal text-slate-400">by {{ $memo->user->name }}</span>
                        </summary>
                        <p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ $memo->body }}</p>
                    </details>
                </div>
            @empty
                <p class="text-sm text-slate-400">公開メモはありません。</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
