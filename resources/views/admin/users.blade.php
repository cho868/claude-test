@extends('layouts.app')
@section('title', 'ユーザー管理')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">👥 ユーザー管理</h2>
    <a href="{{ route('admin.index') }}" class="text-sm text-slate-500 hover:underline">← 管理ダッシュボード</a>
</div>

{{-- 発行されたパスワードリセットリンク（本人にDiscord等で渡す） --}}
@if (session('reset_link'))
    @php $rl = session('reset_link'); @endphp
    <div class="mb-4 rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-sm"
         x-data="{ copied: false, copy() { navigator.clipboard.writeText($refs.url.value).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
        <p class="font-bold text-emerald-800">🔑 {{ $rl['user'] }} さんのパスワード再設定リンクを発行しました</p>
        <div class="mt-2 flex gap-2">
            <input x-ref="url" type="text" readonly value="{{ $rl['url'] }}"
                   onclick="this.select()"
                   class="w-full rounded-lg border-emerald-200 bg-white px-2 py-1.5 text-xs text-slate-600">
            <button type="button" @click="copy()"
                    class="shrink-0 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                    x-text="copied ? 'コピーしました✓' : 'コピー'"></button>
        </div>
        <p class="mt-2 text-xs text-emerald-700">
            このリンクを本人にDiscord等で送ってください。開いた本人が新しいパスワードを設定します（有効期限 {{ $rl['expires'] }}分・1回使い切り）。パスワードは誰にも表示されません。
        </p>
    </div>
@endif

<div class="overflow-x-auto rounded-2xl bg-white p-4 shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs text-slate-400">
                <th class="py-2">名前</th><th>ログインID</th><th>称号</th><th class="text-right">pt</th>
                <th class="text-center">連続</th><th class="text-center">権限</th><th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $u)
                <tr class="border-b last:border-0">
                    <td class="py-2 font-medium"><span class="flex items-center gap-2"><x-avatar :user="$u" :size="24" /> {{ $u->name }}</span></td>
                    <td class="text-slate-500">{{ $u->username }}</td>
                    <td><x-title-badge :title="$u->currentTitle()" /></td>
                    <td class="text-right">{{ number_format($u->points) }}</td>
                    <td class="text-center">{{ $u->login_streak }}日</td>
                    <td class="text-center">
                        @if ($u->is_admin)
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-700">管理者</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">一般</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <form method="POST" action="{{ route('admin.users.reset-link', $u) }}"
                                  onsubmit="return confirm('{{ $u->name }} のパスワード再設定リンクを発行しますか?')">
                                @csrf
                                <button class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs hover:bg-slate-200" title="パスワード再設定リンクを発行">
                                    🔑 リセット
                                </button>
                            </form>
                            @if ($u->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.toggle-admin', $u) }}"
                                      onsubmit="return confirm('{{ $u->name }} の権限を変更しますか?')">
                                    @csrf
                                    <button class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs hover:bg-slate-200">
                                        {{ $u->is_admin ? '一般にする' : '管理者にする' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-300">自分</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $users->links() }}</div>
@endsection
