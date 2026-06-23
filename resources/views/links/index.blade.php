@extends('layouts.app')
@section('title', 'リンク集')

@section('content')
<h2 class="mb-1 text-2xl font-bold">🔗 リンク集</h2>
<p class="mb-4 text-sm text-slate-500">身内で使う外部サービス（共同編集・スプレッドシート・各種ツール）への入口をまとめます。</p>

{{-- 追加フォーム --}}
<details class="mb-6 rounded-2xl bg-white p-5 shadow-sm">
    <summary class="cursor-pointer font-semibold">＋ リンクを追加</summary>
    <form method="POST" action="{{ route('links.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
        @csrf
        <input type="text" name="title" placeholder="名前（例: みんなのメモ HackMD）" required class="rounded-lg border-slate-300 text-sm shadow-sm">
        <input type="url" name="url" placeholder="https://..." required class="rounded-lg border-slate-300 text-sm shadow-sm">
        <input type="text" name="category" list="cats" placeholder="カテゴリ（例: 共同編集）" required class="rounded-lg border-slate-300 text-sm shadow-sm">
        <datalist id="cats">
            <option value="共同編集"><option value="ツール"><option value="ゲーム"><option value="その他">
            @foreach ($categories as $c)<option value="{{ $c }}">@endforeach
        </datalist>
        <input type="text" name="icon" placeholder="アイコン絵文字（任意 例: 📝）" maxlength="8" class="rounded-lg border-slate-300 text-sm shadow-sm">
        <input type="text" name="description" placeholder="説明（任意）" class="rounded-lg border-slate-300 text-sm shadow-sm">
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_public" value="1" checked class="rounded border-slate-300"> 身内に公開</label>
        <div class="sm:col-span-2"><button class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-700">追加</button></div>
    </form>
</details>

@forelse ($links as $category => $items)
    <h3 class="mb-2 mt-4 font-bold text-slate-600">{{ $category }}</h3>
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $link)
            <div x-data="{edit:false}" class="rounded-2xl bg-white p-4 shadow-sm">
                <div x-show="!edit">
                    <div class="flex items-start justify-between">
                        <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 font-bold hover:underline">
                            <span class="text-xl">{{ $link->icon ?: '🔗' }}</span>{{ $link->title }}
                        </a>
                        @if ($link->user_id === auth()->id() || auth()->user()->is_admin)
                            <div class="flex shrink-0 gap-1 text-xs">
                                <button @click="edit=true" class="text-slate-400 hover:text-slate-700">編集</button>
                                <form method="POST" action="{{ route('links.destroy', $link) }}" onsubmit="return confirm('削除?')">@csrf @method('DELETE')<button class="text-slate-300 hover:text-rose-500">×</button></form>
                            </div>
                        @endif
                    </div>
                    @if ($link->description)<p class="mt-1 text-xs text-slate-500">{{ $link->description }}</p>@endif
                    <p class="mt-1 truncate text-xs text-slate-400">{{ $link->url }}</p>
                    @unless ($link->is_public)<span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">自分のみ</span>@endunless
                </div>
                {{-- 編集フォーム --}}
                <form x-show="edit" x-cloak method="POST" action="{{ route('links.update', $link) }}" class="space-y-2">
                    @csrf @method('PUT')
                    <input type="text" name="title" value="{{ $link->title }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                    <input type="url" name="url" value="{{ $link->url }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                    <div class="flex gap-2">
                        <input type="text" name="category" value="{{ $link->category }}" required class="flex-1 rounded-lg border-slate-300 text-sm shadow-sm">
                        <input type="text" name="icon" value="{{ $link->icon }}" maxlength="8" class="w-16 rounded-lg border-slate-300 text-center text-sm shadow-sm">
                    </div>
                    <input type="text" name="description" value="{{ $link->description }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_public" value="1" {{ $link->is_public ? 'checked' : '' }} class="rounded border-slate-300"> 公開</label>
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-white">保存</button>
                        <button type="button" @click="edit=false" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs">やめる</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@empty
    <p class="text-slate-400">まだリンクがありません。「＋ リンクを追加」から登録してください（HackMD等の入口に）。</p>
@endforelse
@endsection
