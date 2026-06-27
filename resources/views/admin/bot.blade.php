@extends('layouts.app')
@section('title', 'Bot設定')

@section('content')
<div class="mx-auto max-w-3xl">
    <x-page-header title="Discord Bot 設定" icon="🤖" back="{{ route('admin.index') }}"
        subtitle="ステータス・機能ON/OFF・メッセージ文面を再起動なしで編集（管理者のみ）" />

    @unless ($configured)
        <div class="rounded-2xl bg-amber-50 p-6 text-sm text-amber-800">
            <p class="font-bold">未設定です</p>
            <p class="mt-1">サーバーの <code>.env</code> に <code>BOT_ADMIN_KEY</code>（と必要なら <code>BOT_ADMIN_URL</code>）を設定し、
            <code>php artisan config:cache</code> を実行してください。キーはブラウザには表示されません（サーバー側でのみ使用）。</p>
        </div>
    @else
        @if ($error)
            <div class="mb-4 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                ⚠️ {{ $error }}
            </div>
        @endif

        @if ($settings !== null)
            <form method="POST" action="{{ route('admin.bot.update') }}" class="space-y-5">
                @csrf

                {{-- ステータス --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <h3 class="mb-3 font-bold">🎮 ステータス（即時反映）</h3>
                    <div class="flex flex-wrap gap-3">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-700">文言</label>
                            <input type="text" name="activity_name" value="{{ data_get($settings, 'activity.name') }}"
                                   class="mt-1 w-full rounded-lg border-slate-300 shadow-sm" placeholder="例: レジェンドオブアストルム">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">種別</label>
                            @php $curType = data_get($settings, 'activity.type', 'PLAYING'); @endphp
                            <select name="activity_type" class="mt-1 rounded-lg border-slate-300 shadow-sm">
                                @foreach ($types as $t)
                                    <option value="{{ $t }}" @selected($curType === $t)>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- 機能ON/OFF --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <h3 class="mb-3 font-bold">🔧 機能 ON/OFF <span class="text-xs font-normal text-slate-400">（次のイベントから反映）</span></h3>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($features as $key => $label)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50">
                                <input type="checkbox" name="features[{{ $key }}]" value="1" class="rounded border-slate-300"
                                    @checked(data_get($settings, "features.{$key}", false))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- メッセージ文面 --}}
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <h3 class="mb-3 font-bold">💬 メッセージ文面</h3>
                    <div class="space-y-3">
                        @foreach ($messages as $key => $label)
                            <div>
                                <label class="block text-sm font-medium text-slate-700">{{ $label }}</label>
                                <textarea name="messages[{{ $key }}]" rows="2"
                                          class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">{{ data_get($settings, "messages.{$key}") }}</textarea>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <x-btn type="submit">保存してBotに反映</x-btn>
                    <span class="text-xs text-slate-400">ID（チャンネル等）の編集はフェーズ2予定</span>
                </div>
            </form>
        @endif
    @endunless
</div>
@endsection
