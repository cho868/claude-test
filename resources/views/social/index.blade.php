@extends('layouts.app')
@section('title', 'ソシャゲ日課')

@section('content')
@php
    $user = auth()->user();
    $config = [
        'urls' => [
            'store' => route('social.games.store'),
            'reorder' => route('social.reorder'),
            'notify' => route('social.notify'),
            'pushSubscribe' => route('push.subscribe'),
            'pushUnsubscribe' => route('push.unsubscribe'),
            'pushTest' => route('push.test'),
            'base' => url('/social-games'),
        ],
        'templates' => $templates,
        'push' => [
            'key' => $pushPublicKey,
            'configured' => $pushConfigured,
            'count' => $subscribedCount,
        ],
        'notify' => [
            'enabled' => (bool) $user->routine_notify,
            'before' => (int) $user->routine_notify_before,
        ],
    ];
@endphp

<div x-data="routineApp(@js($games), @js($config))" x-cloak>

    <x-page-header title="ソシャゲ日課" icon="📋"
        subtitle="ゲームごとのリセット時刻に合わせて自動リセット。リセット前に通知します。">
        <x-slot:actions>
            <button type="button" @click="tab = 'today'"
                :class="tab === 'today' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600'"
                class="rounded-lg px-3 py-2 text-sm font-semibold shadow-sm">今日やること</button>
            <button type="button" @click="tab = 'games'"
                :class="tab === 'games' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600'"
                class="rounded-lg px-3 py-2 text-sm font-semibold shadow-sm">ゲーム別</button>
            <button type="button" @click="tab = 'settings'"
                :class="tab === 'settings' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600'"
                class="rounded-lg px-3 py-2 text-sm font-semibold shadow-sm">⚙️ 設定</button>
        </x-slot:actions>
    </x-page-header>

    {{-- 保存状態のさりげない表示（画面遷移が無いので、ここで安心感を出す） --}}
    <div class="mb-3 h-5 text-sm" aria-live="polite">
        <span x-show="message" x-transition class="text-emerald-600" x-text="message"></span>
        <span x-show="failed" x-transition class="text-rose-600">
            保存できませんでした。通信を確認してください。
        </span>
    </div>

    {{-- ============ 今日やること ============ --}}
    <div x-show="tab === 'today'">
        <template x-if="games.length === 0">
            <p class="rounded-2xl bg-white p-6 text-center text-slate-400 shadow-sm">
                まだゲームがありません。「⚙️ 設定」から追加してください。
            </p>
        </template>

        <template x-if="games.length > 0">
            <div>
                {{-- 達成状況のサマリー --}}
                <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
                    <div class="mb-2 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-sm text-slate-500">未完了</p>
                            <p class="text-3xl font-bold" :class="pending.length === 0 ? 'text-emerald-500' : ''">
                                <span x-text="pending.length"></span><span class="text-base font-normal text-slate-400">件</span>
                            </p>
                        </div>
                        <p class="text-right text-sm text-slate-500">
                            <span x-show="nextResetLabel" x-text="nextResetLabel"></span>
                        </p>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-400 transition-all"
                            :style="`width: ${progress}%`"></div>
                    </div>
                    <p class="mt-2 text-xs text-slate-400" x-show="pending.length === 0">
                        🎉 今日の分はぜんぶ終わっています。
                    </p>
                </div>

                {{-- 未完了リスト --}}
                <div class="space-y-2">
                    <template x-for="item in pending" :key="item.task.id">
                        <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm">
                            <button type="button" @click="setDone(item.task, true)"
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border-2 border-slate-300 bg-white hover:border-emerald-400"
                                :aria-label="`${item.task.title} を完了にする`"></button>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium" x-text="item.task.title"></p>
                                <p class="text-xs text-slate-400">
                                    <span x-text="item.game.icon"></span>
                                    <span x-text="item.game.name"></span>
                                    ・<span x-text="cadenceLabel(item.task.cadence)"></span>
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-xs"
                                :class="item.hoursLeft <= 3 ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-500'"
                                x-text="remainText(item.reset)"></span>
                        </div>
                    </template>
                </div>

                {{-- 完了済み（間違えて付けた時に外せるよう、畳んで残す） --}}
                <div class="mt-4" x-show="finished.length > 0">
                    <button type="button" @click="showFinished = !showFinished"
                        class="text-sm text-slate-500 hover:underline">
                        <span x-text="showFinished ? '▾' : '▸'"></span>
                        完了済み <span x-text="finished.length"></span>件
                    </button>
                    <div class="mt-2 space-y-2" x-show="showFinished" x-transition>
                        <template x-for="item in finished" :key="item.task.id">
                            <div class="flex items-center gap-3 rounded-xl bg-white/60 p-3">
                                <button type="button" @click="setDone(item.task, false)"
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border-2 border-emerald-500 bg-emerald-500 text-sm text-white"
                                    :aria-label="`${item.task.title} を未完了に戻す`">✓</button>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-slate-400 line-through" x-text="item.task.title"></p>
                                    <p class="text-xs text-slate-300">
                                        <span x-text="item.game.name"></span>
                                        ・<span x-text="cadenceLabel(item.task.cadence)"></span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ============ ゲーム別 ============ --}}
    <div x-show="tab === 'games'" class="grid gap-4 lg:grid-cols-2">
        <template x-for="game in games" :key="game.id">
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="truncate text-lg font-bold">
                        <span x-text="game.icon"></span> <span x-text="game.name"></span>
                    </h3>
                    <span class="shrink-0 text-xs text-slate-400" x-text="remainText(game.next_reset.daily)"></span>
                </div>

                <template x-for="cadence in ['daily','weekly','monthly']" :key="cadence">
                    <div class="mb-3" x-show="tasksOf(game, cadence).length > 0">
                        <p class="mb-1 text-xs font-bold text-slate-500">
                            <span x-text="cadenceIcon(cadence)"></span>
                            <span x-text="cadenceLabel(cadence)"></span>
                            <span class="font-normal text-slate-300" x-text="remainText(game.next_reset[cadence])"></span>
                        </p>
                        <template x-for="task in tasksOf(game, cadence)" :key="task.id">
                            <div class="flex items-center gap-2 py-1">
                                <button type="button" @click="setDone(task, !task.done)"
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded border-2 text-xs"
                                    :class="task.done ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 bg-white'"
                                    :aria-label="task.title">
                                    <span x-show="task.done">✓</span>
                                </button>
                                <span class="flex-1 text-sm" :class="task.done ? 'text-slate-400 line-through' : ''"
                                    x-text="task.title"></span>
                            </div>
                        </template>
                    </div>
                </template>

                <p class="text-xs text-slate-300" x-show="game.tasks.length === 0">
                    課題がありません。「⚙️ 設定」から追加してください。
                </p>
            </div>
        </template>

        <p class="text-slate-400" x-show="games.length === 0">まだゲームがありません。</p>
    </div>

    {{-- ============ 設定 ============ --}}
    <div x-show="tab === 'settings'" class="space-y-4">

        {{-- 通知 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-1 text-lg font-bold">🔔 リセット前の通知</h3>
            <p class="mb-3 text-sm text-slate-500">
                未完了が残っている時だけ鳴ります。全部終わっていれば黙ります。
            </p>

            <template x-if="!push.configured">
                <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">
                    サーバー側の VAPID 鍵が未設定です。
                    <code class="rounded bg-amber-100 px-1">php artisan push:vapid</code> で生成して .env に設定してください。
                </p>
            </template>

            <template x-if="push.configured">
                <div class="space-y-3">
                    <template x-if="needsHomeScreen">
                        <p class="rounded-lg bg-sky-50 p-3 text-sm text-sky-700">
                            iPhone / iPad では<strong>ホーム画面に追加した状態</strong>でないと通知を登録できません。
                            共有ボタン →「ホーム画面に追加」してから、そのアイコンで開き直してください。
                        </p>
                    </template>

                    <template x-if="!pushSupported">
                        <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">
                            このブラウザは Web Push に対応していません。
                        </p>
                    </template>

                    <div class="flex flex-wrap items-center gap-2" x-show="pushSupported">
                        <button type="button" @click="enablePush()" x-show="!pushEnabled"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                            この端末で通知を受け取る
                        </button>
                        <button type="button" @click="disablePush()" x-show="pushEnabled"
                            class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50">
                            この端末の通知を解除
                        </button>
                        <button type="button" @click="testPush()" x-show="pushEnabled"
                            class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400">
                            テスト送信
                        </button>
                        <span class="text-xs text-slate-400">
                            登録済み端末: <span x-text="push.count"></span>台
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t pt-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" x-model="notify.enabled" @change="saveNotify()"
                                class="rounded border-slate-300">
                            通知する
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            リセットの
                            <select x-model.number="notify.before" @change="saveNotify()"
                                class="rounded-lg border-slate-300 text-sm">
                                <template x-for="h in [1,2,3,4,6,8,12]" :key="h">
                                    <option :value="h" x-text="`${h}時間前`"></option>
                                </template>
                            </select>
                        </label>
                    </div>
                </div>
            </template>
        </div>

        {{-- ゲーム追加 --}}
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-lg font-bold">＋ ゲームを追加</h3>
            <div class="mb-3 flex flex-wrap gap-2">
                <template x-for="t in templates" :key="t.key">
                    <button type="button" @click="addFromTemplate(t)"
                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                        <span x-text="t.icon"></span> <span x-text="t.name"></span>
                        <span class="text-xs text-slate-400" x-text="`(${t.count}件)`"></span>
                    </button>
                </template>
            </div>
            <form @submit.prevent="addGame()" class="flex flex-wrap gap-2">
                <input type="text" x-model="newGame.icon" placeholder="🎮" maxlength="4"
                    class="w-16 rounded-lg border-slate-300 text-center text-sm shadow-sm">
                <input type="text" x-model="newGame.name" placeholder="ゲーム名"
                    class="w-52 rounded-lg border-slate-300 text-sm shadow-sm">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                    空で追加
                </button>
            </form>
        </div>

        {{-- 各ゲームの編集 --}}
        <template x-for="(game, gi) in games" :key="game.id">
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <input type="text" x-model="game.icon" @change="saveGame(game)" maxlength="4"
                        class="w-14 rounded-lg border-slate-300 text-center text-sm shadow-sm">
                    <input type="text" x-model="game.name" @change="saveGame(game)"
                        class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm font-bold shadow-sm">
                    <button type="button" @click="moveGame(gi, -1)" :disabled="gi === 0"
                        class="rounded border px-2 py-1 text-xs disabled:opacity-30">↑</button>
                    <button type="button" @click="moveGame(gi, 1)" :disabled="gi === games.length - 1"
                        class="rounded border px-2 py-1 text-xs disabled:opacity-30">↓</button>
                    <button type="button" @click="removeGame(game)"
                        class="text-xs text-rose-400 hover:underline">削除</button>
                </div>

                {{-- リセット設定（ここが今回の肝。0時固定をやめた） --}}
                <div class="mb-3 grid gap-2 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-3">
                    <label class="flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-500">日課リセット</span>
                        <select x-model.number="game.reset_hour" @change="saveGame(game)"
                            class="rounded-lg border-slate-300 text-sm">
                            <template x-for="h in 24" :key="h">
                                <option :value="h - 1" x-text="`${h - 1}:00`"></option>
                            </template>
                        </select>
                    </label>
                    <label class="flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-500">週課リセット</span>
                        <select x-model.number="game.reset_dow" @change="saveGame(game)"
                            class="rounded-lg border-slate-300 text-sm">
                            <template x-for="(d, i) in ['日','月','火','水','木','金','土']" :key="i">
                                <option :value="i" x-text="`${d}曜`"></option>
                            </template>
                        </select>
                    </label>
                    <label class="flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-500">月課リセット</span>
                        <select x-model.number="game.reset_day" @change="saveGame(game)"
                            class="rounded-lg border-slate-300 text-sm">
                            <template x-for="d in 28" :key="d">
                                <option :value="d" x-text="`${d}日`"></option>
                            </template>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 sm:col-span-3">
                        <input type="checkbox" x-model="game.notify" @change="saveGame(game)"
                            class="rounded border-slate-300">
                        <span class="text-xs text-slate-500">このゲームを通知の対象にする</span>
                    </label>
                </div>

                {{-- 課題の編集 --}}
                <template x-for="cadence in ['daily','weekly','monthly']" :key="cadence">
                    <div class="mb-2" x-show="tasksOf(game, cadence).length > 0">
                        <p class="mb-1 text-xs font-bold text-slate-500">
                            <span x-text="cadenceIcon(cadence)"></span><span x-text="cadenceLabel(cadence)"></span>
                        </p>
                        <template x-for="(task, ti) in tasksOf(game, cadence)" :key="task.id">
                            <div class="flex items-center gap-2 py-0.5">
                                <span class="flex-1 truncate text-sm" x-text="task.title"></span>
                                <button type="button" @click="moveTask(game, cadence, ti, -1)" :disabled="ti === 0"
                                    class="rounded border px-1.5 text-xs disabled:opacity-30">↑</button>
                                <button type="button" @click="moveTask(game, cadence, ti, 1)"
                                    :disabled="ti === tasksOf(game, cadence).length - 1"
                                    class="rounded border px-1.5 text-xs disabled:opacity-30">↓</button>
                                <button type="button" @click="removeTask(game, task)"
                                    class="text-xs text-slate-300 hover:text-rose-400">×</button>
                            </div>
                        </template>
                    </div>
                </template>

                <form @submit.prevent="addTask(game)" class="mt-2 flex gap-2 border-t pt-3">
                    <input type="text" x-model="game.newTaskTitle" placeholder="課題を追加"
                        class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm shadow-sm">
                    <select x-model="game.newTaskCadence" class="rounded-lg border-slate-300 text-sm shadow-sm">
                        <option value="daily">日課</option>
                        <option value="weekly">週課</option>
                        <option value="monthly">月課</option>
                    </select>
                    <button class="rounded-lg bg-slate-700 px-3 text-sm text-white hover:bg-slate-600">＋</button>
                </form>
            </div>
        </template>
    </div>
</div>

@verbatim
<script>
function routineApp(games, config) {
    return {
        games: games.map(g => ({ ...g, newTaskTitle: '', newTaskCadence: 'daily' })),
        templates: config.templates,
        urls: config.urls,
        push: config.push,
        notify: config.notify,
        tab: 'today',
        showFinished: false,
        message: '',
        failed: false,
        now: Date.now(),
        pushSupported: 'serviceWorker' in navigator && 'PushManager' in window,
        pushEnabled: false,

        init() {
            // カウントダウンの更新。30秒間隔なら負荷も電池も気にならない
            setInterval(() => {
                this.now = Date.now();
                // 開きっぱなしでリセットを跨いだら、古いチェック状態を見せずに取り直す
                if (this.resetPassed()) location.reload();
            }, 30000);
            this.refreshPushState();
        },

        resetPassed() {
            return this.games.some(g =>
                ['daily', 'weekly', 'monthly'].some(c => new Date(g.next_reset[c]).getTime() <= this.now)
            );
        },

        // ---------- 表示用 ----------
        cadenceLabel(c) { return { daily: '日課', weekly: '週課', monthly: '月課' }[c] || '日課' },
        cadenceIcon(c) { return { daily: '🔆', weekly: '📅', monthly: '🗓️' }[c] || '🔆' },
        tasksOf(game, cadence) { return game.tasks.filter(t => t.cadence === cadence) },

        hoursLeft(iso) { return (new Date(iso).getTime() - this.now) / 3600000 },

        remainText(iso) {
            const h = this.hoursLeft(iso);
            if (h <= 0) return 'まもなくリセット';
            if (h < 1) return `あと${Math.max(1, Math.round(h * 60))}分`;
            if (h < 48) return `あと${Math.floor(h)}時間`;
            return `あと${Math.floor(h / 24)}日`;
        },

        /** 今日やること: 日課すべて + 48時間以内にリセットされる週課/月課 */
        get todayItems() {
            const items = [];
            for (const game of this.games) {
                for (const task of game.tasks) {
                    const reset = game.next_reset[task.cadence];
                    const h = this.hoursLeft(reset);
                    if (task.cadence !== 'daily' && h > 48) continue;
                    items.push({ game, task, reset, hoursLeft: h });
                }
            }
            return items.sort((a, b) => a.hoursLeft - b.hoursLeft);
        },

        get pending() { return this.todayItems.filter(i => !i.task.done) },
        get finished() { return this.todayItems.filter(i => i.task.done) },

        get progress() {
            const all = this.todayItems.length;
            return all === 0 ? 100 : Math.round((this.finished.length / all) * 100);
        },

        get nextResetLabel() {
            const next = this.pending[0];
            return next ? `次のリセット ${this.remainText(next.reset)}` : '';
        },

        // ---------- 通信 ----------
        async send(url, body, method = 'POST') {
            this.failed = false;
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: body ? JSON.stringify(body) : undefined,
                });
                if (!res.ok) throw new Error(res.status);
                return await res.json();
            } catch (e) {
                this.failed = true;
                return null;
            }
        },

        flash(text) {
            this.message = text;
            setTimeout(() => { this.message = '' }, 2000);
        },

        /** サーバーから返った最新スナップショットで置き換える（入力中の値は保つ） */
        apply(data) {
            if (!data || !data.games) return;
            const drafts = Object.fromEntries(this.games.map(g => [g.id, g]));
            this.games = data.games.map(g => ({
                ...g,
                newTaskTitle: drafts[g.id]?.newTaskTitle ?? '',
                newTaskCadence: drafts[g.id]?.newTaskCadence ?? 'daily',
            }));
            if (data.message) this.flash(data.message);
        },

        // ---------- チェック ----------
        async setDone(task, done) {
            const before = task.done;
            task.done = done;   // 先に画面を変える（体感を最優先）
            const res = await this.send(`${this.urls.base}/tasks/${task.id}/toggle`, { done });
            if (!res) { task.done = before; return; }
            task.done = res.done;
        },

        // ---------- ゲーム/課題の編集 ----------
        async addGame() {
            if (!this.newGame.name.trim()) return;
            this.apply(await this.send(this.urls.store, {
                name: this.newGame.name.trim(),
                icon: this.newGame.icon || '🎮',
            }));
            this.newGame = { name: '', icon: '' };
        },

        newGame: { name: '', icon: '' },

        async addFromTemplate(t) {
            this.apply(await this.send(this.urls.store, { name: t.name, icon: t.icon, template: t.key }));
        },

        async saveGame(game) {
            const res = await this.send(`${this.urls.base}/${game.id}`, {
                name: game.name, icon: game.icon,
                reset_hour: game.reset_hour, reset_dow: game.reset_dow,
                reset_day: game.reset_day, notify: game.notify,
            }, 'PATCH');
            this.apply(res);
        },

        async removeGame(game) {
            if (!confirm(`「${game.name}」を削除しますか?`)) return;
            this.apply(await this.send(`${this.urls.base}/${game.id}`, null, 'DELETE'));
        },

        async addTask(game) {
            if (!game.newTaskTitle.trim()) return;
            const res = await this.send(`${this.urls.base}/${game.id}/tasks`, {
                title: game.newTaskTitle.trim(),
                cadence: game.newTaskCadence,
            });
            game.newTaskTitle = '';
            this.apply(res);
        },

        async removeTask(game, task) {
            this.apply(await this.send(`${this.urls.base}/tasks/${task.id}`, null, 'DELETE'));
        },

        // ---------- 並べ替え ----------
        async moveGame(index, delta) {
            const to = index + delta;
            if (to < 0 || to >= this.games.length) return;
            const list = [...this.games];
            [list[index], list[to]] = [list[to], list[index]];
            this.games = list;
            await this.send(this.urls.reorder, { games: list.map(g => g.id) });
        },

        async moveTask(game, cadence, index, delta) {
            const group = this.tasksOf(game, cadence);
            const to = index + delta;
            if (to < 0 || to >= group.length) return;
            [group[index], group[to]] = [group[to], group[index]];
            // 表示順は cadence ごとに見せているが、保存は行全体の並びで行う
            const merged = ['daily', 'weekly', 'monthly'].flatMap(c =>
                c === cadence ? group : this.tasksOf(game, c)
            );
            game.tasks = merged;
            await this.send(this.urls.reorder, { tasks: merged.map(t => t.id) });
        },

        async saveNotify() {
            const res = await this.send(this.urls.notify, {
                routine_notify: this.notify.enabled,
                routine_notify_before: this.notify.before,
            });
            if (res) this.flash('通知設定を保存しました。');
        },

        // ---------- Web Push ----------
        get needsHomeScreen() {
            const iOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            const standalone = window.navigator.standalone === true
                || window.matchMedia('(display-mode: standalone)').matches;
            return iOS && !standalone;
        },

        async refreshPushState() {
            if (!this.pushSupported) return;
            try {
                const reg = await navigator.serviceWorker.ready;
                this.pushEnabled = !!(await reg.pushManager.getSubscription());
            } catch (e) {
                this.pushEnabled = false;
            }
        },

        async enablePush() {
            try {
                const reg = await navigator.serviceWorker.ready;
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('ブラウザ側で通知が許可されませんでした。設定から許可してください。');
                    return;
                }
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.push.key),
                });
                const json = sub.toJSON();
                const res = await this.send(this.urls.pushSubscribe, {
                    endpoint: json.endpoint,
                    keys: json.keys,
                    label: navigator.userAgent.slice(0, 80),
                });
                if (res) {
                    this.push.count = res.count;
                    this.pushEnabled = true;
                    this.flash('この端末で通知を受け取ります。');
                }
            } catch (e) {
                this.failed = true;
            }
        },

        async disablePush() {
            try {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    await this.send(this.urls.pushUnsubscribe, { endpoint: sub.endpoint });
                    await sub.unsubscribe();
                }
                this.pushEnabled = false;
                this.push.count = Math.max(0, this.push.count - 1);
                this.flash('この端末の通知を解除しました。');
            } catch (e) {
                this.failed = true;
            }
        },

        async testPush() {
            const res = await this.send(this.urls.pushTest, {});
            if (res && !res.ok) alert('送信できませんでした。端末の通知許可を確認してください。');
        },

        /** VAPID公開鍵(base64url) を pushManager が要求する Uint8Array に変換する */
        urlBase64ToUint8Array(base64) {
            const padding = '='.repeat((4 - base64.length % 4) % 4);
            const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
            return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
        },
    }
}
</script>
@endverbatim
@endsection
