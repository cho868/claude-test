@extends('layouts.app')
@section('title', 'くじ引き・抽選')

@section('content')
<x-page-header title="くじ引き・抽選" icon="🎲" back="{{ route('tools.index') }}"
    subtitle="リストからランダムに選びます。順番決めやプレゼント抽選に" />

<div x-data="lottery()" x-cloak class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <label class="block text-sm font-medium text-slate-700">候補（1行に1つ）</label>
        <textarea x-model="raw" rows="10" placeholder="たろう&#10;はなこ&#10;じろう"
                  class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"></textarea>
        <p class="mt-1 text-xs text-slate-400"><span x-text="list.length"></span>件</p>

        <div class="mt-3 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-slate-500">選ぶ人数</label>
                <input type="number" x-model.number="count" min="1" class="mt-1 w-20 rounded-lg border-slate-300 text-sm shadow-sm">
            </div>
            <label class="flex items-center gap-1.5 text-sm text-slate-600">
                <input type="checkbox" x-model="unique" class="rounded border-slate-300"> 重複なし
            </label>
            <label class="flex items-center gap-1.5 text-sm text-slate-600">
                <input type="checkbox" x-model="removeAfter" class="rounded border-slate-300"> 当選者をリストから消す
            </label>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <x-btn type="button" @click="draw()" x-bind:disabled="!list.length || rolling">🎲 抽選する</x-btn>
            <button type="button" @click="shuffleAll()" x-bind:disabled="!list.length"
                    class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold hover:bg-slate-200">🔀 全体をシャッフル</button>
            <button type="button" @click="history = []" x-show="history.length"
                    class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold hover:bg-slate-200">履歴クリア</button>
        </div>
    </div>

    <div class="space-y-4">
        <div class="flex min-h-40 items-center justify-center rounded-2xl bg-white p-6 text-center shadow-sm">
            <div>
                <p class="text-xs text-slate-400" x-show="!result.length && !rolling">ここに結果が出ます</p>
                <p class="text-3xl font-black tracking-tight" :class="rolling ? 'text-slate-300' : 'text-slate-900'"
                   x-text="rolling ? spin : result.join('　')"></p>
                <p class="mt-2 text-xs text-emerald-600" x-show="result.length && !rolling">🎉 当選！</p>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm" x-show="history.length">
            <h3 class="mb-2 font-bold">📜 履歴</h3>
            <div class="space-y-1 text-sm">
                <template x-for="(h, i) in history" :key="i">
                    <div class="flex gap-2 border-b py-1 last:border-0">
                        <span class="text-xs text-slate-400" x-text="h.at"></span>
                        <span class="font-medium" x-text="h.names"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function lottery() {
  return {
    raw: '', count: 1, unique: true, removeAfter: false,
    result: [], history: [], rolling: false, spin: '',
    get list() { return this.raw.split(/\r?\n/).map(s => s.trim()).filter(Boolean); },

    // 暗号学的乱数で公平に選ぶ
    rand(n) { return crypto.getRandomValues(new Uint32Array(1))[0] % n; },

    async draw() {
      const pool = [...this.list];
      if (!pool.length) return;
      this.rolling = true; this.result = [];

      // ドラムロール演出
      const until = Date.now() + 900;
      while (Date.now() < until) {
        this.spin = pool[this.rand(pool.length)];
        await new Promise(r => setTimeout(r, 60));
      }

      const picked = [];
      const n = Math.max(1, Math.min(this.count, this.unique ? pool.length : 999));
      for (let i = 0; i < n; i++) {
        const idx = this.rand(pool.length);
        picked.push(pool[idx]);
        if (this.unique) pool.splice(idx, 1);
        if (!pool.length) break;
      }

      this.result = picked;
      this.rolling = false;
      this.history.unshift({ at: new Date().toLocaleTimeString('ja-JP'), names: picked.join('、') });
      if (this.removeAfter) {
        const rest = this.list.filter(x => !picked.includes(x));
        this.raw = rest.join('\n');
      }
    },

    shuffleAll() {
      const a = [...this.list];
      for (let i = a.length - 1; i > 0; i--) {
        const j = this.rand(i + 1);
        [a[i], a[j]] = [a[j], a[i]];
      }
      this.raw = a.join('\n');
      this.result = [];
    },
  };
}
</script>
@endsection
