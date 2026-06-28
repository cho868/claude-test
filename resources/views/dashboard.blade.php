@extends('layouts.app')
@section('title', 'ダッシュボード')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    {{-- 左: マイステータス --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 to-slate-700 p-6 text-white shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <x-avatar :user="$user" :size="56" />
                    <div class="min-w-0">
                        <p class="text-xs text-slate-300">おかえりなさい</p>
                        <h2 class="truncate text-2xl font-bold">{{ $user->name }}</h2>
                        <div class="mt-1"><x-title-badge :title="$currentTitle" /></div>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-3xl font-extrabold text-amber-400">{{ number_format($user->points) }}<span class="text-base">pt</span></p>
                    <p class="text-xs text-slate-300">🔥 連続 {{ $user->login_streak }}日 / 累計 {{ $user->total_logins }}回</p>
                </div>
            </div>

            {{-- 称号の進捗バー --}}
            <div class="mt-5">
                @if ($nextTitle)
                    <div class="mb-1 flex justify-between text-xs text-slate-300">
                        <span>次の称号: {{ $nextTitle->icon }} {{ $nextTitle->name }}</span>
                        <span>あと {{ number_format($nextTitle->required_points - $user->points) }}pt</span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-600/50">
                        <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-rose-500" style="width: {{ $progress }}%"></div>
                    </div>
                @else
                    <p class="text-sm font-semibold text-amber-300">🎉 最高位の称号に到達しています！</p>
                @endif
            </div>
        </div>

        {{-- ツール一覧（カテゴリ別） --}}
        <div>
            <h3 class="mb-3 text-lg font-bold">🧰 便利ツール</h3>
            @php
                $groups = [
                    '🎮 ゲーム' => [
                        ['tournaments.index', '🏆', 'トーナメント', '対戦表を自動生成'],
                        ['tierlists.index', '📊', 'Tierリスト', 'Tierリストを作成・共有'],
                        ['games.index', '🎮', 'ゲーム時間', 'Steam連携 / 手動記録'],
                        ['steam.index', '🕹️', 'Steam', 'プレイ中/共通/実績/セール'],
                        ['matches.index', '⚔️', '戦績', '勝敗を記録・勝率'],
                        ['social.index', '📋', 'ソシャゲ管理', '日課/週課/月課'],
                        ['pokemon.index', '🔴', 'ポケモン計算', 'ダメージ計算機'],
                    ],
                    '💪 からだ' => [
                        ['sleep.index', '😴', '睡眠', '記録・グラフ化'],
                        ['fitness.index', '💪', 'フィットネス', '体重/運動・チャレンジ'],
                    ],
                    '👥 みんな' => [
                        ['surveys.index', '🗳️', 'アンケート', 'みんなで投票'],
                        ['schedule.index', '📅', '予定', '予定と出欠管理'],
                        ['documents.index', '📚', '資料', '手順書を共有'],
                        ['memos.index', '📝', 'メモ', '攻略メモを共有'],
                        ['links.index', '🔗', 'リンク集', 'ツールへの入口'],
                    ],
                    '⚙️ その他' => array_values(array_filter([
                        ['profile.edit', '⚙️', 'プロフィール', 'アイコン/連携設定'],
                        auth()->user()->is_admin ? ['admin.index', '🛠️', '管理', 'チェックリスト/ユーザー'] : null,
                    ])),
                ];
            @endphp
            <div class="space-y-4">
                @foreach ($groups as $label => $items)
                    <div>
                        <p class="mb-2 text-xs font-bold text-slate-400">{{ $label }}</p>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($items as [$route, $icon, $name, $desc])
                                <a href="{{ route($route) }}"
                                   class="group flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                    <div class="text-2xl">{{ $icon }}</div>
                                    <div class="min-w-0">
                                        <p class="font-semibold group-hover:text-slate-900">{{ $name }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $desc }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
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
                            <x-avatar :user="$member" :size="24" />
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
