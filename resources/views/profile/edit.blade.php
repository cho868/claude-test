@extends('layouts.app')
@section('title', 'プロフィール')

@section('content')
<div class="mx-auto max-w-2xl">
    <h2 class="mb-4 text-2xl font-bold">⚙️ プロフィール設定</h2>

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-slate-700">名前</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">メールアドレス</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            {{-- アバター(アップロード不要・著作権フリー) --}}
            <div class="rounded-xl bg-slate-50 p-4">
                <h3 class="mb-3 text-sm font-bold text-slate-700">🙂 アイコン</h3>
                <div class="flex items-start gap-4">
                    <div id="avatarPreview" class="shrink-0"></div>
                    <div class="flex-1 space-y-3">
                        <div class="flex gap-3 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="avatar_style" value="emoji" {{ ($user->avatar_style ?? 'emoji') === 'emoji' ? 'checked' : '' }} onchange="updateAvatar()"> 絵文字</label>
                            <label class="flex items-center gap-1"><input type="radio" name="avatar_style" value="dicebear" {{ ($user->avatar_style ?? 'emoji') === 'dicebear' ? 'checked' : '' }} onchange="updateAvatar()"> 自動生成イラスト</label>
                        </div>

                        {{-- 絵文字設定 --}}
                        <div id="emojiOpts" class="space-y-2">
                            <div class="flex items-center gap-2">
                                <input type="text" id="emojiInput" name="avatar_emoji" value="{{ old('avatar_emoji', $user->avatar_emoji) }}" maxlength="8" placeholder="絵文字"
                                       class="w-24 rounded-lg border-slate-300 text-center text-lg shadow-sm" oninput="updateAvatar()">
                                <div class="flex flex-wrap gap-1">
                                    @foreach (['😀','😎','🐱','🐶','🦊','🐸','🍣','🎮','🔥','⚡','🌸','👑','🤖','🦄'] as $e)
                                        <button type="button" class="rounded px-1 text-lg hover:bg-slate-200" onclick="document.getElementById('emojiInput').value='{{ $e }}';updateAvatar()">{{ $e }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-500">背景色</span>
                                <input type="color" id="colorInput" name="avatar_color" value="{{ old('avatar_color', $user->avatar_color ?: '#6366f1') }}" oninput="updateAvatar()" class="h-8 w-12 rounded">
                                <div class="flex gap-1">
                                    @foreach (['#6366f1','#ef4444','#f59e0b','#22c55e','#3b82f6','#ec4899','#0ea5e9','#111827'] as $c)
                                        <button type="button" class="h-6 w-6 rounded-full ring-1 ring-black/10" style="background:{{ $c }}" onclick="document.getElementById('colorInput').value='{{ $c }}';updateAvatar()"></button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- DiceBear設定 --}}
                        <div id="dicebearOpts" class="space-y-2">
                            <div class="flex items-center gap-2">
                                <select id="variantInput" name="avatar_variant" class="rounded-lg border-slate-300 text-sm shadow-sm" onchange="updateAvatar()">
                                    @foreach ($variants as $v)
                                        <option value="{{ $v }}" {{ ($user->avatar_variant ?: 'fun-emoji') === $v ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                                <input type="text" id="seedInput" name="avatar_seed" value="{{ old('avatar_seed', $user->avatar_seed) }}" placeholder="シード(空欄=名前)"
                                       class="flex-1 rounded-lg border-slate-300 text-sm shadow-sm" oninput="updateAvatar()">
                                <button type="button" class="rounded-lg bg-slate-200 px-2 py-1 text-xs hover:bg-slate-300" onclick="randomSeed()">🎲 ランダム</button>
                            </div>
                            <p class="text-xs text-slate-400">同じシードなら毎回同じ絵柄。シードを変えると別の絵柄になります(DiceBear・無料/著作権フリー)。</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-slate-50 p-4">
                <h3 class="mb-3 text-sm font-bold text-slate-700">🔗 連携</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Discord ID</label>
                        <input type="text" name="discord_id" value="{{ old('discord_id', $user->discord_id) }}" placeholder="123456789012345678"
                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Steam（IDまたはバニティ名・URL）</label>
                        <input type="text" name="steam_id" value="{{ old('steam_id', $user->steam_id) }}" placeholder="7656119… / バニティ名 / プロフィールURL"
                               class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <p class="mt-1 text-xs text-slate-500">17桁のID・バニティ名（`/id/◯◯`）・プロフィールURL のどれでもOK。保存時に64bit IDへ自動変換します。「ゲーム時間」から実績を取り込めます。</p>
                    </div>
                </div>
            </div>

            <button class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white hover:bg-slate-700">保存</button>
        </form>
    </div>

    <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
        <div class="mb-3 flex items-center gap-3">
            <x-avatar :user="$user" :size="48" />
            <h3 class="font-bold">📊 ステータス</h3>
        </div>
        <div class="flex flex-wrap gap-4 text-sm text-slate-600">
            <span>現在の称号: <x-title-badge :title="$user->currentTitle()" /></span>
            <span>ポイント: <b>{{ number_format($user->points) }}</b></span>
            <span>連続ログイン: <b>{{ $user->login_streak }}</b> 日</span>
            <span>累計ログイン: <b>{{ $user->total_logins }}</b> 回</span>
        </div>
    </div>
</div>

<script>
    function updateAvatar() {
        const style = document.querySelector('input[name=avatar_style]:checked').value;
        document.getElementById('emojiOpts').style.display = style === 'emoji' ? '' : 'none';
        document.getElementById('dicebearOpts').style.display = style === 'dicebear' ? '' : 'none';
        const prev = document.getElementById('avatarPreview');
        if (style === 'emoji') {
            const emoji = document.getElementById('emojiInput').value || @json($user->initial());
            const color = document.getElementById('colorInput').value || '#6366f1';
            prev.innerHTML = `<span style="display:inline-flex;width:72px;height:72px;border-radius:9999px;align-items:center;justify-content:center;font-size:36px;color:#fff;background:${color}">${emoji}</span>`;
        } else {
            const variant = document.getElementById('variantInput').value;
            const seed = encodeURIComponent(document.getElementById('seedInput').value || @json($user->name));
            prev.innerHTML = `<img src="https://api.dicebear.com/9.x/${variant}/svg?seed=${seed}" style="width:72px;height:72px;border-radius:9999px;background:#fff;border:1px solid #e2e8f0" />`;
        }
    }
    function randomSeed() {
        document.getElementById('seedInput').value = Math.random().toString(36).slice(2, 8);
        updateAvatar();
    }
    updateAvatar();
</script>
@endsection
