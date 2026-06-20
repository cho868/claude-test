@extends('layouts.app')
@php $editing = isset($document); @endphp
@section('title', $editing ? '資料の編集' : '資料を書く')

@section('content')
<div class="mx-auto max-w-4xl">
    <h2 class="mb-4 text-2xl font-bold">📚 {{ $editing ? '資料の編集' : '資料を書く' }}</h2>

    <form method="POST" action="{{ $editing ? route('documents.update', $document) : route('documents.store') }}"
          class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">タイトル</label>
                <input type="text" name="title" value="{{ old('title', $editing ? $document->title : '') }}" required
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">カテゴリ</label>
                <input type="text" name="category" list="cats" value="{{ old('category', $editing ? $document->category : '') }}"
                       placeholder="サーバー / 開発 など" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
                <datalist id="cats">
                    <option value="サーバー"><option value="開発"><option value="運用"><option value="ゲーム"><option value="一般">
                </datalist>
            </div>
        </div>

        {{-- エディタ / プレビュー切替 --}}
        <div>
            <div class="mb-2 flex gap-1 text-sm">
                <button type="button" id="tabWrite" onclick="showTab('write')" class="rounded-t-lg border-b-2 border-slate-900 px-3 py-1.5 font-semibold">✍️ 書く</button>
                <button type="button" id="tabPrev" onclick="showTab('prev')" class="rounded-t-lg border-b-2 border-transparent px-3 py-1.5 text-slate-500">👁 プレビュー</button>
            </div>
            <textarea name="body" id="body" rows="20" required
                      class="w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm"
                      placeholder="# 見出し&#10;&#10;Markdown で書けます。コードは ``` で囲みます。">{{ old('body', $editing ? $document->body : '') }}</textarea>
            <div id="preview" class="prose prose-slate hidden min-h-[20rem] max-w-none rounded-lg border border-slate-200 bg-white p-5"></div>
            <p class="mt-1 text-xs text-slate-400">Markdown 対応(見出し #、リスト -、コード ```、リンク [text](url) など)。安全のため生HTMLは除去されます。</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">公開範囲</label>
            @php $vis = old('visibility', $editing ? $document->visibility : 'members'); @endphp
            <select name="visibility" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm sm:w-64">
                <option value="members" @selected($vis === 'members')>身内に公開（ログインユーザー全員）</option>
                <option value="private" @selected($vis === 'private')>自分のみ</option>
                @if (auth()->user()->is_admin)
                    <option value="admin" @selected($vis === 'admin')>管理者のみ（サーバー/機密情報向け）</option>
                @endif
            </select>
            <p class="mt-1 text-xs text-slate-400">サーバー手順やセキュリティなど機密性の高い資料は「管理者のみ」に。</p>
        </div>

        <div class="flex gap-2">
            <button class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700">{{ $editing ? '更新' : '公開する' }}</button>
            <a href="{{ route('documents.index') }}" class="rounded-lg bg-slate-100 px-5 py-2.5 text-slate-600 hover:bg-slate-200">キャンセル</a>
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
