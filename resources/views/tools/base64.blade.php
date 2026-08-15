@extends('layouts.app')
@section('title', 'Base64→画像デコード')

@section('content')
<x-page-header title="Base64→画像デコード" icon="🧩" back="{{ route('tools.index') }}"
    subtitle="Base64文字列を画像に復元。逆方向（画像→Base64）もできます" />

<div x-data="b64tool()" x-cloak class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <label class="block text-sm font-medium text-slate-700">Base64文字列</label>
        <textarea x-model="input" @input="decode()" rows="10"
                  placeholder="data:image/png;base64,iVBORw0KGgo... または iVBORw0KGgo... だけでもOK"
                  class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs shadow-sm"></textarea>
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" @click="input=''; result=null; error=''"
                    class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold hover:bg-slate-200">クリア</button>
            <label class="cursor-pointer rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold hover:bg-slate-200">
                🖼️ 画像からBase64を作る
                <input type="file" accept="image/*" class="hidden" @change="encode($event)">
            </label>
        </div>
        <p class="mt-2 text-xs text-rose-600" x-text="error"></p>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">プレビュー</h3>
        <template x-if="result">
            <div>
                <img :src="result.url" alt="" class="mx-auto max-h-72 rounded-lg border"
                     style="background-image: linear-gradient(45deg,#eee 25%,transparent 25%,transparent 75%,#eee 75%),linear-gradient(45deg,#eee 25%,transparent 25%,transparent 75%,#eee 75%); background-size:16px 16px; background-position:0 0,8px 8px;">
                <p class="mt-2 text-center text-xs text-slate-500" x-text="result.info"></p>
                <div class="mt-3 flex justify-center gap-2">
                    <a :href="result.url" :download="result.name"
                       class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">⬇️ 画像を保存</a>
                </div>
            </div>
        </template>
        <p x-show="!result" class="text-sm text-slate-400">Base64を貼り付けると画像が表示されます</p>
    </div>
</div>

<script>
function b64tool() {
  return {
    input: '', result: null, error: '',

    decode() {
      this.error = ''; this.result = null;
      const raw = this.input.trim();
      if (!raw) return;
      let url = raw;
      if (!/^data:/i.test(raw)) {
        const body = raw.replace(/\s+/g, '');
        if (!/^[A-Za-z0-9+/=]+$/.test(body)) { this.error = 'Base64として読めない文字が含まれています'; return; }
        url = 'data:image/png;base64,' + body;   // 形式不明はPNGとして試す
      }
      const img = new Image();
      img.onload = () => {
        const mime = (url.match(/^data:([^;]+)/) || [,'image/png'])[1];
        const bytes = Math.round((url.length - url.indexOf(',') - 1) * 3 / 4);
        this.result = {
          url,
          name: 'decoded.' + (mime.split('/')[1] || 'png').replace('svg+xml', 'svg'),
          info: `${img.naturalWidth}×${img.naturalHeight} / ${mime} / 約${(bytes/1024).toFixed(1)}KB`,
        };
      };
      img.onerror = () => { this.error = '画像としてデコードできませんでした'; };
      img.src = url;
    },

    encode(ev) {
      const f = ev.target.files?.[0];
      if (!f) return;
      const r = new FileReader();
      r.onload = () => { this.input = r.result; this.decode(); };
      r.readAsDataURL(f);
      ev.target.value = '';
    },
  };
}
</script>
@endsection
