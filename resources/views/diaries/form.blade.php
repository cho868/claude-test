@extends('layouts.app')
@php $editing = $diary->exists; @endphp
@section('title', $editing ? '日記の編集' : '日記を書く')

@section('content')
<div class="mx-auto max-w-3xl">
    <x-page-header :title="$editing ? '日記を編集' : '日記を書く'" icon="📔"
        back="{{ $editing ? route('diaries.show', $diary) : route('diaries.index') }}"
        subtitle="Markdown で書けます。公開範囲は既定で「自分のみ」です。" />

    <form method="POST" action="{{ $editing ? route('diaries.update', $diary) : route('diaries.store') }}"
          class="space-y-4 rounded-2xl bg-white p-5 shadow-sm sm:p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">日付</label>
                <input type="date" name="entry_date" required max="{{ now()->toDateString() }}"
                       value="{{ old('entry_date', $diary->entry_date?->toDateString() ?? now()->toDateString()) }}"
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">タイトル <span class="text-xs text-slate-400">(任意)</span></label>
                <input type="text" name="title" maxlength="120" value="{{ old('title', $diary->title) }}"
                       placeholder="未入力なら「n月j日の日記」になります"
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
        </div>

        {{-- 気分・天気 --}}
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['mood', '今日の気分', \App\Models\Diary::MOODS, old('mood', $diary->mood)],
                ['weather', '天気', \App\Models\Diary::WEATHERS, old('weather', $diary->weather)],
            ] as [$field, $label, $options, $selected])
                <div>
                    <p class="mb-1 text-sm font-medium text-slate-700">{{ $label }} <span class="text-xs text-slate-400">(任意)</span></p>
                    <div class="flex flex-wrap gap-1.5">
                        <label class="cursor-pointer">
                            <input type="radio" name="{{ $field }}" value="" class="peer sr-only" @checked(! $selected)>
                            <span class="inline-block rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs text-slate-500 peer-checked:bg-slate-800 peer-checked:text-white">なし</span>
                        </label>
                        @foreach ($options as $key => [$icon, $name])
                            <label class="cursor-pointer">
                                <input type="radio" name="{{ $field }}" value="{{ $key }}" class="peer sr-only" @checked($selected === $key)>
                                <span class="inline-block rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs peer-checked:bg-slate-800 peer-checked:text-white">{{ $icon }} {{ $name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- 本文（書く / プレビュー） --}}
        <div>
            <div class="mb-2 flex gap-1 text-sm">
                <button type="button" id="tabWrite" onclick="showTab('write')" class="rounded-t-lg border-b-2 border-slate-900 px-3 py-1.5 font-semibold">✍️ 書く</button>
                <button type="button" id="tabPrev" onclick="showTab('prev')" class="rounded-t-lg border-b-2 border-transparent px-3 py-1.5 text-slate-500">👁 プレビュー</button>
            </div>
            <textarea name="body" id="body" rows="16" required maxlength="20000"
                      class="w-full rounded-lg border-slate-300 text-sm shadow-sm"
                      placeholder="今日あったこと、思ったことなど。&#10;&#10;## 見出し&#10;- リスト&#10;**太字** も使えます。">{{ old('body', $diary->body) }}</textarea>
            <div id="preview" class="prose prose-slate hidden min-h-[16rem] max-w-none rounded-lg border border-slate-200 bg-white p-5"></div>
            <p class="mt-1 text-xs text-slate-400">Markdown 対応（見出し #、リスト -、リンク [text](url) など）。安全のため生HTMLは除去されます。</p>
        </div>

        {{-- 公開範囲 --}}
        <div>
            <label class="block text-sm font-medium text-slate-700">公開範囲</label>
            @php $vis = old('visibility', $diary->visibility ?: 'private'); @endphp
            <select name="visibility" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm sm:w-72">
                <option value="private" @selected($vis === 'private')>自分のみ（既定）</option>
                <option value="members" @selected($vis === 'members')>身内に共有（みんなの日記に載る）</option>
            </select>
            <p class="mt-1 text-xs text-slate-400">「自分のみ」の日記は管理者からも見えません。</p>
        </div>

        <div class="flex items-center gap-2">
            <x-btn type="submit">💾 {{ $editing ? '更新する' : '保存する' }}</x-btn>
            <a href="{{ $editing ? route('diaries.show', $diary) : route('diaries.index') }}"
               class="text-sm text-slate-500 hover:underline">キャンセル</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
    const body = document.getElementById('body');
    const preview = document.getElementById('preview');
    const tabWrite = document.getElementById('tabWrite');
    const tabPrev = document.getElementById('tabPrev');

    function showTab(which) {
        const writing = which === 'write';
        body.classList.toggle('hidden', !writing);
        preview.classList.toggle('hidden', writing);
        tabWrite.classList.toggle('border-slate-900', writing);
        tabWrite.classList.toggle('font-semibold', writing);
        tabWrite.classList.toggle('border-transparent', !writing);
        tabWrite.classList.toggle('text-slate-500', !writing);
        tabPrev.classList.toggle('border-slate-900', !writing);
        tabPrev.classList.toggle('font-semibold', !writing);
        tabPrev.classList.toggle('border-transparent', writing);
        tabPrev.classList.toggle('text-slate-500', writing);
        if (!writing) preview.innerHTML = marked.parse(body.value || '*(プレビューする内容がありません)*');
    }
</script>
@endsection
