@extends('layouts.app')
@section('title', 'タスク')

@section('content')
<x-page-header title="タスク" icon="✅" subtitle="期限つきのToDo。既定は自分だけに見えます。期限の日の朝に通知できます。">
    <x-slot:actions>
        <x-btn href="{{ route('tasks.index') }}" :variant="$tab === 'mine' ? 'primary' : 'secondary'">🙋 自分</x-btn>
        <x-btn href="{{ route('tasks.index', ['tab' => 'shared']) }}" :variant="$tab === 'shared' ? 'primary' : 'secondary'">👥 みんなの共有</x-btn>
    </x-slot:actions>
</x-page-header>

<datalist id="task-lists">
    @foreach ($myLists as $name)
        <option value="{{ $name }}">
    @endforeach
</datalist>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        {{-- リストで絞り込み --}}
        @if ($lists->isNotEmpty())
            <div class="flex flex-wrap gap-1.5 text-sm">
                <a href="{{ route('tasks.index', ['tab' => $tab]) }}"
                   class="rounded-full px-3 py-1 {{ $list ? 'bg-white text-slate-600 shadow-sm hover:bg-slate-50' : 'bg-slate-900 text-white' }}">すべて</a>
                @foreach ($lists as $name)
                    <a href="{{ route('tasks.index', ['tab' => $tab, 'list' => $name]) }}"
                       class="rounded-full px-3 py-1 {{ $list === $name ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 shadow-sm hover:bg-slate-50' }}">{{ $name }}</a>
                @endforeach
            </div>
        @endif

        @if ($openCount === 0)
            <div class="rounded-2xl bg-white p-8 text-center text-slate-400 shadow-sm">
                {{ $tab === 'shared' ? '共有されているタスクはありません。' : '🎉 やることはありません。右のフォームから追加できます。' }}
            </div>
        @endif

        @foreach (\App\Http\Controllers\TaskController::BUCKETS as $key => [$icon, $label])
            @if ($buckets->has($key))
                <section class="rounded-2xl bg-white p-4 shadow-sm {{ $key === 'overdue' ? 'ring-1 ring-rose-200' : '' }}">
                    <h3 class="mb-1 font-bold">{{ $icon }} {{ $label }}
                        <span class="ml-1 text-sm font-normal text-slate-400">{{ $buckets[$key]->count() }}件</span></h3>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($buckets[$key] as $task)
                            @include('tasks._row')
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach

        @if ($done->isNotEmpty())
            <details class="rounded-2xl bg-white p-4 shadow-sm">
                <summary class="cursor-pointer font-bold text-slate-500">✔️ 完了済み
                    <span class="ml-1 text-sm font-normal text-slate-400">{{ $doneTotal }}件{{ $doneTotal > $done->count() ? '（新しい'.$done->count().'件を表示）' : '' }}</span></summary>
                <ul class="mt-1 divide-y divide-slate-100">
                    @foreach ($done as $task)
                        @include('tasks._row')
                    @endforeach
                </ul>
                @if ($tab === 'mine')
                    <form method="POST" action="{{ route('tasks.clear-done') }}" class="mt-2 text-right"
                          onsubmit="return confirm('完了済みのタスクを削除します。よろしいですか？')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="list" value="{{ $list }}">
                        <x-btn type="submit" variant="danger">🧹 {{ $list ? '「'.$list.'」の' : '' }}完了済みを片付ける</x-btn>
                    </form>
                @endif
            </details>
        @endif
    </div>

    {{-- 右: 追加フォーム --}}
    <div class="space-y-4">
        <form method="POST" action="{{ route('tasks.store') }}" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <h3 class="font-bold">➕ タスクを追加</h3>
            <input name="title" required maxlength="120" placeholder="やること" value="{{ old('title') }}"
                   class="w-full rounded-lg border-slate-300 text-sm">
            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="due_date" value="{{ old('due_date') }}" class="rounded-lg border-slate-300 text-sm">
                <input name="list" list="task-lists" maxlength="40" placeholder="リスト名（任意）" value="{{ old('list', $list) }}"
                       class="rounded-lg border-slate-300 text-sm">
            </div>
            <textarea name="note" rows="2" maxlength="5000" placeholder="メモ（Markdown可・任意）"
                      class="w-full rounded-lg border-slate-300 text-sm">{{ old('note') }}</textarea>
            <div class="flex items-center justify-between gap-2">
                <select name="visibility" class="rounded-lg border-slate-300 text-sm">
                    <option value="private">🔒 自分のみ</option>
                    <option value="members">👥 身内に共有</option>
                </select>
                <x-btn type="submit">追加</x-btn>
            </div>
        </form>

        <details class="rounded-2xl bg-white p-4 shadow-sm" @if ($errors->has('text')) open @endif>
            <summary class="cursor-pointer font-bold">📋 まとめて貼り付け</summary>
            <form method="POST" action="{{ route('tasks.bulk') }}" class="mt-2 space-y-2">
                @csrf
                <p class="text-xs leading-relaxed text-slate-500">
                    1行＝1タスク。行の先頭の日付（<code>10/6</code> や <code>2027/1/10</code>）が期限になります。
                    <code># 見出し</code> の行で以降のリスト名が切り替わります。Markdown の表や箇条書きもそのまま貼れます。
                </p>
                <textarea name="text" rows="8" required maxlength="10000"
                          placeholder="# Olive&#10;10/6 Olive口座に10万円を一度に入金&#10;# 年末調整&#10;保険料の控除証明書を探す"
                          class="w-full rounded-lg border-slate-300 font-mono text-xs">{{ old('text') }}</textarea>
                <div class="grid grid-cols-2 gap-2">
                    <input name="list" list="task-lists" maxlength="40" placeholder="既定のリスト名" value="{{ old('list') }}"
                           class="rounded-lg border-slate-300 text-sm">
                    <select name="visibility" class="rounded-lg border-slate-300 text-sm">
                        <option value="private">🔒 自分のみ</option>
                        <option value="members">👥 身内に共有</option>
                    </select>
                </div>
                <div class="text-right"><x-btn type="submit">まとめて追加</x-btn></div>
            </form>
        </details>

        <form method="POST" action="{{ route('tasks.settings') }}" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            @csrf
            <h3 class="font-bold">🔔 期限の通知</h3>
            <p class="text-xs text-slate-500">期限の日（と期限切れが残っている日）に、指定した時刻に1回だけ Push 通知します。</p>
            <div class="flex items-center gap-2">
                <select name="task_notify_hour" class="rounded-lg border-slate-300 text-sm">
                    <option value="" @selected(auth()->user()->task_notify_hour === null)>通知しない</option>
                    @foreach (range(5, 22) as $h)
                        <option value="{{ $h }}" @selected(auth()->user()->task_notify_hour === $h)>毎朝 {{ $h }}:00</option>
                    @endforeach
                </select>
                <x-btn type="submit" variant="secondary">保存</x-btn>
            </div>
            <p class="text-xs text-slate-400">
                通知を受け取る端末の登録は <a href="{{ route('social.index') }}" class="underline">ソシャゲ日課</a> の「設定」タブで行います（共通）。
                iPhone は「ホーム画面に追加」してから開いてください。
            </p>
        </form>
    </div>
</div>

<script>
    function taskRow(initial, url) {
        return {
            done: initial,
            failed: false,
            // 楽観的UI: 先に見た目を変え、失敗したら戻す。done を送るので再送しても裏返らない
            async save() {
                const want = this.done;
                this.failed = false;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ done: want }),
                    });
                    if (!res.ok) throw new Error(res.status);
                } catch (e) {
                    this.done = !want;
                    this.failed = true;
                }
            },
        };
    }
</script>
@endsection
