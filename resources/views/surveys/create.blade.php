@extends('layouts.app')
@section('title', 'アンケート作成')

@section('content')
<div class="mx-auto max-w-2xl">
    <h2 class="mb-4 text-2xl font-bold">🗳️ アンケート作成</h2>
    <form method="POST" action="{{ route('surveys.store') }}" class="space-y-4 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">質問・タイトル</label>
            <input type="text" name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">説明(任意)</label>
            <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border-slate-300 shadow-sm">{{ old('description') }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">選択肢</label>
            <div id="options" class="mt-1 space-y-2">
                <input type="text" name="options[]" placeholder="選択肢1" class="w-full rounded-lg border-slate-300 shadow-sm">
                <input type="text" name="options[]" placeholder="選択肢2" class="w-full rounded-lg border-slate-300 shadow-sm">
            </div>
            <button type="button" onclick="addOption()" class="mt-2 text-sm text-slate-600 hover:underline">＋ 選択肢を追加</button>
        </div>
        <div class="flex flex-wrap gap-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="multiple_choice" value="1" class="rounded border-slate-300"> 複数選択可
            </label>
            <div class="flex items-center gap-2 text-sm">
                <span>締切(任意)</span>
                <input type="datetime-local" name="closes_at" class="rounded-lg border-slate-300 shadow-sm">
            </div>
        </div>
        <button class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700">作成</button>
    </form>
</div>

<script>
    function addOption() {
        const div = document.getElementById('options');
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'options[]';
        input.placeholder = '選択肢' + (div.children.length + 1);
        input.className = 'w-full rounded-lg border-slate-300 shadow-sm';
        div.appendChild(input);
    }
</script>
@endsection
