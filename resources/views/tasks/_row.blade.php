{{-- タスク1行。チェックは fetch + 楽観的UI（ページ遷移なし） --}}
@php
    $mine = $task->user_id === auth()->id();
    $bucket = $task->bucket($today);
    $dueColor = match ($bucket) {
        'overdue' => 'text-rose-600 font-semibold',
        'today' => 'text-amber-600 font-semibold',
        default => 'text-slate-400',
    };
@endphp
<li class="py-2.5" x-data="taskRow({{ $task->isDone() ? 'true' : 'false' }}, '{{ route('tasks.toggle', $task) }}')">
    <div class="flex items-start gap-3">
        @if ($mine)
            <input type="checkbox" x-model="done" @change="save()"
                   class="mt-0.5 h-5 w-5 shrink-0 cursor-pointer rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
        @else
            <span class="mt-0.5 shrink-0">{{ $task->isDone() ? '✅' : '⬜' }}</span>
        @endif

        <div class="min-w-0 flex-1">
            <p class="break-words" :class="done ? 'text-slate-400 line-through' : ''">{{ $task->title }}</p>
            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                @if ($label = $task->dueLabel($today))
                    <span class="{{ $dueColor }}">⏰ {{ $label }}</span>
                @endif
                @if ($task->list)
                    <a href="{{ route('tasks.index', ['tab' => $tab, 'list' => $task->list]) }}"
                       class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-500 hover:bg-slate-200">{{ $task->list }}</a>
                @endif
                @if ($mine && $task->isShared())
                    <span class="text-sky-600">👥 共有中</span>
                @endif
                @unless ($mine)
                    <span class="inline-flex items-center gap-1 text-slate-500"><x-avatar :user="$task->user" :size="16" />{{ $task->user->name }}</span>
                @endunless
                <span x-show="failed" x-cloak class="text-rose-600">保存できませんでした</span>
            </div>
            @if ($task->note)
                <div class="prose prose-sm prose-slate mt-1 max-w-none text-slate-600">{!! $task->renderedNote() !!}</div>
            @endif

            @if ($mine)
                <details class="mt-1">
                    <summary class="cursor-pointer text-xs text-slate-400 hover:text-slate-600">編集</summary>
                    <form method="POST" action="{{ route('tasks.update', $task) }}" class="mt-2 grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <input name="title" value="{{ $task->title }}" required maxlength="120"
                               class="rounded-lg border-slate-300 text-sm sm:col-span-2">
                        <input type="date" name="due_date" value="{{ $task->due_date?->toDateString() }}" class="rounded-lg border-slate-300 text-sm">
                        <input name="list" value="{{ $task->list }}" list="task-lists" maxlength="40" placeholder="リスト名（任意）"
                               class="rounded-lg border-slate-300 text-sm">
                        <textarea name="note" rows="2" maxlength="5000" placeholder="メモ（Markdown可）"
                                  class="rounded-lg border-slate-300 text-sm sm:col-span-2">{{ $task->note }}</textarea>
                        <select name="visibility" class="rounded-lg border-slate-300 text-sm">
                            <option value="private" @selected(! $task->isShared())>🔒 自分のみ</option>
                            <option value="members" @selected($task->isShared())>👥 身内に共有</option>
                        </select>
                        <div class="flex justify-end gap-2">
                            <x-btn type="submit" variant="secondary">保存</x-btn>
                            <x-btn type="submit" variant="danger" form="task-del-{{ $task->id }}">削除</x-btn>
                        </div>
                    </form>
                    <form id="task-del-{{ $task->id }}" method="POST" action="{{ route('tasks.destroy', $task) }}"
                          onsubmit="return confirm('削除しますか？')">
                        @csrf @method('DELETE')
                    </form>
                </details>
            @endif
        </div>
    </div>
</li>
