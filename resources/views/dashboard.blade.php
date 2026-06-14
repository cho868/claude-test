@extends('layouts.app')
@section('title', 'ダッシュボード')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    {{-- 左: マイステータス --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">こんにちは</p>
                    <h2 class="text-2xl font-bold">{{ $user->name }} さん</h2>
                    <div class="mt-2">
                        <x-title-badge :title="$currentTitle" />
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-extrabold text-amber-500">{{ number_format($user->points) }}<span class="text-base">pt</span></p>
                    <p class="text-xs text-slate-500">連続ログイン {{ $user->login_streak }} 日 / 累計 {{ $user->total_logins }} 回</p>
                </div>
            </div>

            {{-- 称号の進捗バー --}}
            <div class="mt-5">
                @if ($nextTitle)
                    <div class="mb-1 flex justify-between text-xs text-slate-500">
                        <span>次の称号: {{ $nextTitle->icon }} {{ $nextTitle->name }}</span>
                        <span>あと {{ number_format($nextTitle->required_points - $user->points) }}pt</span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-rose-500" style="width: {{ $progress }}%"></div>
                    </div>
                @else
                    <p class="text-sm font-semibold text-rose-500">🎉 最高位の称号に到達しています！</p>
                @endif
            </div>
        </div>

        {{-- ツール一覧 --}}
        <div>
            <h3 class="mb-3 text-lg font-bold">🧰 便利ツール</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $tools = [
                        ['tournaments.index', '🏆', 'トーナメント作成', '対戦表を自動生成'],
                        ['tierlists.index', '📊', 'ソート/ランキング', 'ティアリストを作成'],
                        ['memos.index', '📝', 'GMQ2メモ', '攻略メモを共有'],
                        ['sleep.index', '😴', '睡眠時間チェック', '睡眠を記録・可視化'],
                        ['games.index', '🎮', 'ゲーム時間', 'Steam連携 / 手動記録'],
                        ['surveys.index', '🗳️', 'アンケート', 'みんなで投票'],
                        ['schedule.index', '📅', 'スケジュール共有', '予定と出欠管理'],
                        ['profile.edit', '⚙️', 'プロフィール', 'Discord/Steam連携'],
                    ];
                @endphp
                @foreach ($tools as [$route, $icon, $name, $desc])
                    <a href="{{ route($route) }}"
                       class="group rounded-2xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="text-2xl">{{ $icon }}</div>
                        <p class="mt-1 font-semibold group-hover:text-slate-900">{{ $name }}</p>
                        <p class="text-xs text-slate-500">{{ $desc }}</p>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- 直近の予定 & アンケート --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-bold">📅 近日の予定</h3>
                @forelse ($upcomingEvents as $event)
                    <a href="{{ route('schedule.show', $event) }}" class="block border-b py-2 text-sm last:border-0 hover:text-slate-900">
                        <span class="font-medium">{{ $event->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $event->starts_at->format('n/j (D) H:i') }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">予定はありません</p>
                @endforelse
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-bold">🗳️ 募集中のアンケート</h3>
                @forelse ($openSurveys as $survey)
                    <a href="{{ route('surveys.show', $survey) }}" class="block border-b py-2 text-sm last:border-0 hover:text-slate-900">
                        <span class="font-medium">{{ $survey->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $survey->votes_count }} 票</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400">アンケートはありません</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- 右: ランキング & 称号一覧 & 履歴 --}}
    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">🏅 ポイントランキング</h3>
            <ol class="space-y-2">
                @foreach ($ranking as $i => $member)
                    <li class="flex items-center justify-between text-sm {{ $member->id === $user->id ? 'font-bold text-amber-600' : '' }}">
                        <span class="flex items-center gap-2">
                            <span class="w-5 text-right text-slate-400">{{ $i + 1 }}</span>
                            {{ $member->name }}
                            <x-title-badge :title="$member->currentTitle()" />
                        </span>
                        <span>{{ number_format($member->points) }}pt</span>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">👑 称号一覧</h3>
            <ul class="space-y-1.5 text-sm">
                @foreach ($titles as $title)
                    <li class="flex items-center justify-between {{ $user->points >= $title->required_points ? '' : 'opacity-40' }}">
                        <x-title-badge :title="$title" />
                        <span class="text-xs text-slate-500">{{ number_format($title->required_points) }}pt</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">🧾 最近の獲得ポイント</h3>
            @forelse ($recentPointLogs as $log)
                <div class="flex items-center justify-between border-b py-1.5 text-xs last:border-0">
                    <span class="text-slate-600">{{ $log->description ?? $log->reason }}</span>
                    <span class="font-semibold text-emerald-600">+{{ $log->amount }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">まだ履歴がありません</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
