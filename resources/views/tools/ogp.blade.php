@extends('layouts.app')
@section('title', 'OGP確認')

@section('content')
<x-page-header title="OGP確認" icon="🔍" back="{{ route('tools.index') }}"
    subtitle="SNSでシェアしたときの画像・タイトル・説明文の見え方を確認" />

<div x-data="ogpTool()" x-cloak>
    <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
        <form @submit.prevent="fetchOgp()" class="flex flex-wrap items-end gap-2">
            <div class="min-w-64 flex-1">
                <label class="block text-sm font-medium text-slate-700">ページのURL</label>
                <input type="text" x-model="url" placeholder="https://example.com/article"
                       class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
            </div>
            <x-btn type="submit" x-bind:disabled="busy">
                <span x-show="!busy">🔍 確認</span><span x-show="busy">取得中…</span>
            </x-btn>
        </form>
        <p class="mt-2 text-xs text-rose-600" x-text="error"></p>
    </div>

    <template x-if="r">
        <div class="grid gap-4 lg:grid-cols-2">
            {{-- プレビュー --}}
            <div class="space-y-4">
                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <h3 class="mb-3 font-bold">𝕏 / Discord での見え方</h3>
                    <div class="overflow-hidden rounded-xl border">
                        <template x-if="r.image">
                            <img :src="r.image" alt="" class="aspect-[1.91/1] w-full bg-slate-100 object-cover"
                                 x-on:load="imgOk = true" x-on:error="imgOk = false">
                        </template>
                        <div class="p-3">
                            <p class="text-xs text-slate-400" x-text="r.siteName"></p>
                            <p class="font-bold leading-tight" x-text="r.title || '(タイトルなし)'"></p>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-500" x-text="r.desc || '(説明文なし)'"></p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-5 shadow-sm">
                    <h3 class="mb-3 font-bold">✅ チェック結果</h3>
                    <div class="space-y-1.5 text-sm">
                        <template x-for="c in checks" :key="c.label">
                            <div class="flex items-start gap-2">
                                <span x-text="c.ok ? '✅' : '⚠️'"></span>
                                <div>
                                    <span x-text="c.label"></span>
                                    <span class="text-xs text-slate-400" x-text="c.note"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- メタ情報一覧 --}}
            <div class="rounded-2xl bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-bold">🏷️ 取得したメタ情報</h3>
                <div class="max-h-96 space-y-1 overflow-y-auto text-xs">
                    <template x-for="(v, k) in r.meta" :key="k">
                        <div class="flex gap-2 border-b border-slate-50 py-1">
                            <span class="w-40 shrink-0 font-mono text-slate-400" x-text="k"></span>
                            <span class="break-all" x-text="v"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function ogpTool() {
  return {
    url: '', r: null, busy: false, error: '', imgOk: false, imgSize: null,
    get checks() {
      if (!this.r) return [];
      const t = (this.r.title || '').length, d = (this.r.desc || '').length;
      return [
        { ok: !!this.r.title, label: 'og:title', note: t ? `（${t}文字${t > 60 ? '・長め' : ''}）` : '（未設定）' },
        { ok: !!this.r.desc, label: 'og:description', note: d ? `（${d}文字${d > 120 ? '・切れる可能性' : ''}）` : '（未設定）' },
        { ok: !!this.r.image, label: 'og:image', note: this.r.image ? (this.imgSize || '') : '（未設定・シェア時に画像が出ません）' },
        { ok: !!this.r.card, label: 'twitter:card', note: this.r.card ? `（${this.r.card}）` : '（未設定・summaryとして扱われます）' },
        { ok: !!this.r.meta['og:url'], label: 'og:url', note: this.r.meta['og:url'] ? '' : '（未設定）' },
      ];
    },
    async fetchOgp() {
      this.busy = true; this.error = ''; this.r = null; this.imgSize = null;
      try {
        const res = await fetch('{{ route('tools.ogp.fetch') }}', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
          body: JSON.stringify({ url: this.url }),
        });
        const d = await res.json();
        if (!d.ok) { this.error = d.message; return; }
        this.r = d;
        if (d.image) {
          const img = new Image();
          img.onload = () => {
            const ratio = (img.naturalWidth / img.naturalHeight).toFixed(2);
            const ideal = img.naturalWidth >= 1200 && Math.abs(ratio - 1.91) < 0.2;
            this.imgSize = `（${img.naturalWidth}×${img.naturalHeight}・比率${ratio}${ideal ? '・推奨サイズOK' : '・推奨は1200×630'}）`;
          };
          img.src = d.image;
        }
      } catch (e) { this.error = '通信に失敗しました'; }
      finally { this.busy = false; }
    },
  };
}
</script>
@endsection
