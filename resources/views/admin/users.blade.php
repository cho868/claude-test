@extends('layouts.app')
@section('title', 'ユーザー管理')

@section('content')
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-2xl font-bold">👥 ユーザー管理</h2>
    <a href="{{ route('admin.index') }}" class="text-sm text-slate-500 hover:underline">← 管理ダッシュボード</a>
</div>

<div class="overflow-x-auto rounded-2xl bg-white p-4 shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs text-slate-400">
                <th class="py-2">名前</th><th>メール</th><th>称号</th><th class="text-right">pt</th>
                <th class="text-center">連続</th><th class="text-center">権限</th><th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $u)
                <tr class="border-b last:border-0">
                    <td class="py-2 font-medium">{{ $u->name }}</td>
                    <td class="text-slate-500">{{ $u->email }}</td>
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
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $users->links() }}</div>
@endsection
