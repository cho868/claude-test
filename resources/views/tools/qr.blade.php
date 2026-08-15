@extends('layouts.app')
@section('title', 'QRコード作成')

@section('content')
<x-page-header title="QRコード作成" icon="🔳" back="{{ route('tools.index') }}"
    subtitle="ブラウザ内で生成。内容はサーバーに送信されません" />

<div x-data="qrTool()" x-cloak class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-3 flex flex-wrap gap-1.5">
            <template x-for="m in modes" :key="m.id">
                <button type="button" @click="mode = m.id; build()"
                        :class="mode === m.id ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'"
                        class="rounded-lg px-3 py-1.5 text-sm font-semibold" x-text="m.label"></button>
            </template>
        </div>

        <div x-show="mode === 'text'">
            <label class="block text-sm font-medium text-slate-700">テキスト / URL</label>
            <textarea x-model="text" @input="build()" rows="3" placeholder="https://example.com"
                      class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"></textarea>
        </div>

        <div x-show="mode === 'wifi'" class="space-y-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Wi-Fi名 (SSID)</label>
                <input type="text" x-model="wifi.ssid" @input="build()" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">パスワード</label>
                <input type="text" x-model="wifi.pass" @input="build()" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
            </div>
            <div class="flex items-center gap-3">
                <select x-model="wifi.enc" @change="build()" class="rounded-lg border-slate-300 text-sm shadow-sm">
                    <option value="WPA">WPA/WPA2</option>
                    <option value="WEP">WEP</option>
                    <option value="nopass">パスワードなし</option>
                </select>
                <label class="flex items-center gap-1.5 text-sm text-slate-600">
                    <input type="checkbox" x-model="wifi.hidden" @change="build()" class="rounded border-slate-300"> ステルスSSID
                </label>
            </div>
            <p class="text-xs text-slate-400">スマホでこのQRを読むとWi-Fiに接続できます（来客用に便利）</p>
        </div>

        <div x-show="mode === 'mail'" class="space-y-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">宛先</label>
                <input type="email" x-model="mail.to" @input="build()" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">件名</label>
                <input type="text" x-model="mail.subject" @input="build()" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">本文</label>
                <textarea x-model="mail.body" @input="build()" rows="2" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm"></textarea>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-slate-500">サイズ <span x-text="size + 'px'"></span></label>
                <input type="range" min="128" max="1024" step="32" x-model.number="size" @input="build()" class="mt-1 w-full">
            </div>
            <div>
                <label class="block text-xs text-slate-500">誤り訂正</label>
                <select x-model="ecc" @change="build()" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
                    <option value="L">L（7%・小さい）</option>
                    <option value="M">M（15%・標準）</option>
                    <option value="Q">Q（25%）</option>
                    <option value="H">H（30%・ロゴ入れ向き）</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500">前景色</label>
                <input type="color" x-model="fg" @input="build()" class="mt-1 h-9 w-full cursor-pointer rounded border-slate-300">
            </div>
            <div>
                <label class="block text-xs text-slate-500">背景色</label>
                <input type="color" x-model="bg" @input="build()" class="mt-1 h-9 w-full cursor-pointer rounded border-slate-300">
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 text-center shadow-sm">
        <div class="flex min-h-64 items-center justify-center" x-ref="box"></div>
        <p class="mt-2 text-xs text-rose-600" x-text="error"></p>
        <div class="mt-3 flex justify-center gap-2" x-show="ready">
            <button type="button" @click="download('png')"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">⬇️ PNG保存</button>
            <button type="button" @click="download('svg')"
                    class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold hover:bg-slate-200">SVG保存</button>
        </div>
    </div>
</div>

<script>
function qrTool() {
  return {
    mode: 'text', text: '', size: 256, ecc: 'M', fg: '#000000', bg: '#ffffff',
    wifi: { ssid: '', pass: '', enc: 'WPA', hidden: false },
    mail: { to: '', subject: '', body: '' },
    ready: false, error: '', loaded: false,
    modes: [
      { id: 'text', label: 'テキスト / URL' },
      { id: 'wifi', label: 'Wi-Fi' },
      { id: 'mail', label: 'メール' },
    ],

    async init() { await this.load(); this.build(); },

    load() {
      if (this.loaded) return Promise.resolve();
      return new Promise((res, rej) => {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js';
        s.onload = () => { this.loaded = true; res(); };
        s.onerror = () => { this.error = 'QRライブラリの読み込みに失敗しました'; rej(); };
        document.head.appendChild(s);
      });
    },

    payload() {
      const esc = s => String(s).replace(/([\;,:"])/g, '\\$1');
      if (this.mode === 'wifi') {
        if (!this.wifi.ssid) return '';
        const p = this.wifi.enc === 'nopass' ? '' : `P:${esc(this.wifi.pass)};`;
        return `WIFI:T:${this.wifi.enc};S:${esc(this.wifi.ssid)};${p}${this.wifi.hidden ? 'H:true;' : ''};`;
      }
      if (this.mode === 'mail') {
        if (!this.mail.to) return '';
        const q = [];
        if (this.mail.subject) q.push('subject=' + encodeURIComponent(this.mail.subject));
        if (this.mail.body) q.push('body=' + encodeURIComponent(this.mail.body));
        return `mailto:${this.mail.to}${q.length ? '?' + q.join('&') : ''}`;
      }
      return this.text;
    },

    async build() {
      this.error = ''; this.ready = false;
      if (!this.loaded) { await this.load().catch(() => {}); if (!this.loaded) return; }
      const data = this.payload();
      this.$refs.box.innerHTML = '';
      if (!data) { this.$refs.box.innerHTML = '<p class="text-sm text-slate-400">内容を入力するとQRが表示されます</p>'; return; }
      try {
        const cv = document.createElement('canvas');
        await QRCode.toCanvas(cv, data, {
          width: this.size, margin: 2, errorCorrectionLevel: this.ecc,
          color: { dark: this.fg, light: this.bg },
        });
        cv.style.maxWidth = '100%'; cv.style.height = 'auto';
        this.$refs.box.appendChild(cv);
        this.canvas = cv;
        this.svgStr = await QRCode.toString(data, {
          type: 'svg', width: this.size, margin: 2, errorCorrectionLevel: this.ecc,
          color: { dark: this.fg, light: this.bg },
        });
        this.ready = true;
      } catch (e) {
        this.error = 'この内容はQRにできませんでした（長すぎる可能性）';
      }
    },

    download(type) {
      const a = document.createElement('a');
      if (type === 'png') { a.href = this.canvas.toDataURL('image/png'); a.download = 'qrcode.png'; }
      else { a.href = URL.createObjectURL(new Blob([this.svgStr], { type: 'image/svg+xml' })); a.download = 'qrcode.svg'; }
      document.body.appendChild(a); a.click(); a.remove();
    },
  };
}
</script>
@endsection
