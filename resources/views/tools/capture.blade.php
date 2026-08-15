@extends('layouts.app')
@section('title', 'Webページ→PDF/画像')

@section('content')
<x-page-header title="Webページ → PDF / 画像" icon="📸" back="{{ route('tools.index') }}"
    subtitle="URLを指定してページを丸ごと保存します" />

@if (! $chromium)
    <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-5">
        <p class="font-bold text-amber-800">⚠️ この機能にはサーバー側の準備が必要です</p>
        <p class="mt-1 text-sm text-amber-700">
            ページの描画に Chromium（ヘッドレスブラウザ）を使います。サーバーに導入してください：
        </p>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-amber-900/90 p-3 text-xs text-amber-50">sudo apt-get install -y chromium
# Raspberry Pi OS の場合は: sudo apt-get install -y chromium-browser</pre>
        <p class="mt-2 text-xs text-amber-700">
            ※ 変換中は一時的にメモリを 300〜500MB 使います。ラズパイでは同時に何本も実行しないでください。
        </p>
    </div>
@endif

<div x-data="captureTool()" x-cloak>
    <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
        <form @submit.prevent="run()" class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">ページのURL</label>
                <input type="text" x-model="url" placeholder="https://example.com"
                       class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
            </div>
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs text-slate-500">形式</label>
                    <div class="mt-1 flex gap-1.5">
                        <button type="button" @click="type = 'png'"
                                :class="type === 'png' ? 'bg-slate-900 text-white' : 'bg-slate-100'"
                                class="rounded-lg px-3 py-1.5 text-sm font-semibold">🖼️ 画像(PNG)</button>
                        <button type="button" @click="type = 'pdf'"
                                :class="type === 'pdf' ? 'bg-slate-900 text-white' : 'bg-slate-100'"
                                class="rounded-lg px-3 py-1.5 text-sm font-semibold">📄 PDF</button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-500">横幅 <span x-text="width + 'px'"></span></label>
                    <input type="range" min="360" max="1920" step="20" x-model.number="width" class="mt-1 w-40">
                </div>
                <label class="flex items-center gap-1.5 text-sm text-slate-600" x-show="type === 'png'">
                    <input type="checkbox" x-model="fullpage" class="rounded border-slate-300"> ページ全体
                </label>
                <x-btn type="submit" x-bind:disabled="busy || !{{ $chromium ? 'true' : 'false' }}">
                    <span x-show="!busy">📸 変換する</span>
                    <span x-show="busy">変換中…（最大60秒）</span>
                </x-btn>
            </div>
        </form>
        <p class="mt-2 text-xs text-rose-600" x-text="error"></p>
        <p class="mt-2 text-xs text-slate-400">
            ※ 安全のため、社内・家庭内などのプライベートIP宛URLは指定できません。
        </p>
    </div>

    <template x-if="result">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-bold" x-text="result.name"></h3>
                <a :href="result.dataUrl" :download="result.name"
                   class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">⬇️ 保存</a>
            </div>
            <p class="mb-2 text-xs text-slate-400" x-text="`${(result.size/1024).toFixed(0)}KB`"></p>
            <template x-if="type === 'png'">
                <img :src="result.dataUrl" alt="" class="w-full rounded-lg border">
            </template>
            <template x-if="type === 'pdf'">
                <embed :src="result.dataUrl" type="application/pdf" class="h-96 w-full rounded-lg border">
            </template>
        </div>
    </template>
</div>

<script>
function captureTool() {
  return {
    url: '', type: 'png', width: 1200, fullpage: true, busy: false, error: '', result: null,
    async run() {
      this.busy = true; this.error = ''; this.result = null;
      try {
        const res = await fetch('{{ route('tools.capture.run') }}', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
          body: JSON.stringify({ url: this.url, type: this.type, width: this.width, fullpage: this.fullpage }),
        });
        const d = await res.json();
        if (!d.ok) { this.error = d.message; return; }
        this.result = d;
      } catch (e) { this.error = '通信に失敗しました（ページが重すぎる可能性）'; }
      finally { this.busy = false; }
    },
  };
}
</script>
@endsection
