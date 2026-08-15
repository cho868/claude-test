@extends('layouts.app')
@section('title', 'SSLチェッカー')

@section('content')
<x-page-header title="SSLチェッカー" icon="🔒" back="{{ route('tools.index') }}"
    subtitle="サーバーに証明書が正しく入っているか・期限はいつまでかを確認" />

<div x-data="sslTool()" x-cloak>
    <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
        <form @submit.prevent="check()" class="flex flex-wrap items-end gap-2">
            <div class="min-w-64 flex-1">
                <label class="block text-sm font-medium text-slate-700">サイトのURL / ドメイン</label>
                <input type="text" x-model="url" placeholder="example.com"
                       class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
            </div>
            <x-btn type="submit" x-bind:disabled="busy">
                <span x-show="!busy">🔍 チェック</span><span x-show="busy">確認中…</span>
            </x-btn>
        </form>
        <p class="mt-2 text-xs text-rose-600" x-text="error"></p>
    </div>

    <template x-if="r">
        <div class="space-y-4">
            <div class="rounded-2xl p-5 shadow-sm"
                 :class="r.expired ? 'bg-rose-50' : (r.valid ? 'bg-emerald-50' : 'bg-amber-50')">
                <p class="text-2xl font-bold"
                   x-text="r.expired ? '❌ 証明書の期限が切れています' : (r.valid ? '✅ 正しくインストールされています' : '⚠️ 確認が必要です')"></p>
                <p class="mt-1 text-sm" x-text="`残り ${r.daysLeft} 日（${r.to} まで）`"></p>
                <p class="mt-1 text-sm" x-show="!r.nameMatches">
                    ⚠️ 証明書の対象ドメインに <b x-text="r.host"></b> が含まれていません
                </p>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-bold">📄 証明書の内容</h3>
                <dl class="space-y-1.5 text-sm">
                    <template x-for="row in [
                        ['対象 (CN)', r.subject], ['発行者', r.issuer],
                        ['有効期間', r.from + ' 〜 ' + r.to],
                        ['残り日数', r.daysLeft + ' 日'],
                        ['署名方式', r.sigAlg],
                    ]" :key="row[0]">
                        <div class="flex gap-2 border-b border-slate-50 py-1 last:border-0">
                            <dt class="w-28 shrink-0 text-slate-400" x-text="row[0]"></dt>
                            <dd class="break-all" x-text="row[1]"></dd>
                        </div>
                    </template>
                </dl>
                <div class="mt-3">
                    <p class="text-xs text-slate-400">対象ドメイン</p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <template x-for="n in r.names" :key="n">
                            <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs" x-text="n"></span>
                        </template>
                    </div>
                </div>
                <div class="mt-3" x-show="r.chain.length">
                    <p class="text-xs text-slate-400">証明書チェーン</p>
                    <p class="mt-1 text-xs" x-text="r.chain.join(' → ')"></p>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function sslTool() {
  return {
    url: '', r: null, busy: false, error: '',
    async check() {
      this.busy = true; this.error = ''; this.r = null;
      try {
        const res = await fetch('{{ route('tools.ssl.check') }}', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
          body: JSON.stringify({ url: this.url }),
        });
        const d = await res.json();
        if (!d.ok) { this.error = d.message; return; }
        this.r = d;
      } catch (e) { this.error = '通信に失敗しました'; }
      finally { this.busy = false; }
    },
  };
}
</script>
@endsection
