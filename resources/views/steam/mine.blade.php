@extends('layouts.app')
@section('title', 'マイ実績')

@section('content')
<x-page-header title="マイ実績" icon="🏅" back="{{ route('steam.index') }}"
    subtitle="自分のプレイしたゲームの実績を確認">
    <x-slot:actions>
        <x-btn href="{{ route('steam.achievements') }}" variant="secondary">⚖️ みんなと比較</x-btn>
    </x-slot:actions>
</x-page-header>

@unless ($configured)
    <div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-800">サーバーの <code>STEAM_API_KEY</code> が未設定です。</div>
@elseif (! $hasSteam)
    <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm">
        プロフィールに Steam ID（バニティ名/URLでも可）を登録すると、自分の実績を確認できます。
        <a href="{{ route('profile.edit') }}" class="font-medium text-emerald-600 hover:underline">プロフィールを編集</a>
    </div>
@else
    {{-- ゲーム選択 --}}
    <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
        <label class="block text-sm font-medium text-slate-700">ゲームを選ぶ（プレイ時間順）</label>
        @if (empty($myGames))
            <p class="mt-1 text-sm text-slate-400">プレイ時間を取得できませんでした（Steamのプライバシー設定で「ゲームの詳細」を公開にしてください）。</p>
        @else
            <select onchange="if (this.value) location.href='{{ route('steam.mine') }}?appid='+this.value"
                    class="mt-1 w-full max-w-md rounded-lg border-slate-300 text-sm shadow-sm sm:w-96">
                <option value="">― ゲームを選択 ―</option>
                @foreach ($myGames as $g)
                    <option value="{{ $g['appid'] }}" @selected((string) $appid === $g['appid'])>
                        {{ $g['name'] }}（{{ number_format($g['playtime'] / 60, 1) }}h）
                    </option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- 選んだゲームの実績詳細 --}}
    @if ($appid === '')
        @unless (empty($myGames))
            <p class="text-sm text-slate-400">ゲームを選ぶと、その実績の解除状況が一覧表示されます。</p>
        @endunless
    @elseif (! $detail)
        <div class="rounded-2xl bg-white p-6 text-sm text-slate-500 shadow-sm">
            このゲームの実績データが取得できませんでした。<br>
            （このゲームに実績が無い／未プレイ／プロフィールやゲーム詳細が非公開、のいずれか）
        </div>
    @else
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-bold">🏆 {{ $detail['game'] ?? ('App ' . $appid) }}</h3>
                <span class="text-sm font-medium text-slate-600">{{ $detail['pct'] }}% コンプ（{{ $detail['achieved'] }}/{{ $detail['total'] }}）</span>
            </div>
            <div class="mb-4 h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $detail['pct'] }}%"></div>
            </div>

            <ul class="divide-y">
                @foreach ($detail['items'] as $a)
                    <li class="flex items-start gap-3 py-2">
                        <span class="mt-0.5 shrink-0 text-lg">{{ $a['done'] ? '✅' : '⬜' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium {{ $a['done'] ? '' : 'text-slate-400' }}">{{ $a['name'] }}</p>
                            @if ($a['desc'])
                                <p class="text-xs text-slate-400">{{ $a['desc'] }}</p>
                            @endif
                        </div>
                        @if ($a['done'] && $a['time'])
                            <span class="shrink-0 text-xs text-slate-400">{{ \Illuminate\Support\Carbon::createFromTimestamp($a['time'])->format('Y/n/j') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-slate-400">※ 解除済みを上に表示。隠し実績は説明が空の場合があります。10分キャッシュ。</p>
        </div>
    @endif
@endunless
@endsection
