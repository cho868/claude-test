@extends('layouts.app')
@section('title', '画像変換')

@section('content')
<x-page-header title="画像変換" icon="🖼️" subtitle="ブラウザ内で変換。ファイルはサーバーに送信されません">
    <x-slot:actions>
        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">🔒 完全ローカル処理</span>
    </x-slot:actions>
</x-page-header>

<div x-data="imageConverter()" x-cloak>

    {{-- ドロップゾーン --}}
    <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
        <label @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
               @drop.prevent="dragging = false; addFiles($event.dataTransfer.files)"
               :class="dragging ? 'border-emerald-400 bg-emerald-50' : 'border-slate-300 bg-slate-50'"
               class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition">
            <span class="text-4xl">🖼️</span>
            <p class="mt-2 font-semibold">ここに画像をドロップ / タップして選択</p>
            <p class="mt-1 text-xs text-slate-500">
                JPEG・PNG・WebP・GIF・BMP・AVIF・TIFF・HEIC / 複数まとめてOK
            </p>
            <input type="file" accept="image/*,.heic,.heif,.tif,.tiff" multiple class="hidden"
                   @change="addFiles($event.target.files); $event.target.value = ''">
        </label>
    </div>

    {{-- 変換設定 --}}
    <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">⚙️ 変換設定</h3>

        <div class="grid gap-4 sm:grid-cols-2">
            {{-- 出力形式 --}}
            <div>
                <label class="block text-sm font-medium text-slate-700">出力形式</label>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <template x-for="f in formats" :key="f.mime">
                        <button type="button" @click="format = f.mime"
                                :class="format === f.mime ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                class="rounded-lg px-3 py-1.5 text-sm font-semibold" x-text="f.label"></button>
                    </template>
                </div>
                <p class="mt-1.5 text-xs" :class="lossless ? 'text-emerald-600' : 'text-amber-600'"
                   x-text="lossless ? '✅ 完全可逆（劣化なし）' : '⚠️ 非可逆（品質設定で調整）'"></p>
            </div>

            {{-- 品質（非可逆のときだけ） --}}
            <div x-show="!lossless">
                <label class="block text-sm font-medium text-slate-700">
                    品質 <span class="font-mono text-slate-500" x-text="Math.round(quality * 100) + '%'"></span>
                </label>
                <input type="range" min="0.3" max="1" step="0.01" x-model.number="quality" class="mt-2 w-full">
                <p class="mt-1 text-xs text-slate-400">100%でも JPEG/WebP は原理上わずかに劣化します</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            {{-- 透過 --}}
            <div>
                <label class="block text-sm font-medium text-slate-700">透過情報</label>
                <div class="mt-1 flex items-center gap-2">
                    <select x-model="alphaMode" class="rounded-lg border-slate-300 text-sm shadow-sm">
                        <option value="keep">そのまま保持</option>
                        <option value="flatten">背景色で塗りつぶす</option>
                    </select>
                    <input type="color" x-model="bgColor" x-show="alphaMode === 'flatten' || !supportsAlpha"
                           class="h-9 w-12 cursor-pointer rounded border-slate-300">
                </div>
                <p class="mt-1 text-xs text-slate-400" x-show="!supportsAlpha">
                    JPEGは透過を保持できないため背景色で塗りつぶします
                </p>
            </div>

            {{-- リサイズ --}}
            <div>
                <label class="block text-sm font-medium text-slate-700">最大サイズ（任意）</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="number" x-model.number="maxSize" min="0" step="100" placeholder="変更しない"
                           class="w-32 rounded-lg border-slate-300 text-sm shadow-sm">
                    <span class="text-sm text-slate-500">px（長辺）</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">空欄/0なら元の解像度のまま</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-btn type="button" @click="convertAll()" x-bind:disabled="!items.length || busy">
                <span x-show="!busy">🔄 変換する</span>
                <span x-show="busy">処理中… <span x-text="doneCount + '/' + items.length"></span></span>
            </x-btn>
            <button type="button" @click="downloadAll()" x-show="items.some(i => i.result)"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                ⬇️ すべて保存
            </button>
            <button type="button" @click="items = []" x-show="items.length"
                    class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200">
                クリア
            </button>
        </div>
    </div>

    {{-- ファイル一覧 --}}
    <div class="space-y-3" x-show="items.length">
        <template x-for="(item, i) in items" :key="item.id">
            <div class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow-sm">
                <img :src="item.preview" alt="" class="h-16 w-16 shrink-0 rounded-lg border object-contain"
                     style="background-image: linear-gradient(45deg,#eee 25%,transparent 25%,transparent 75%,#eee 75%),linear-gradient(45deg,#eee 25%,transparent 25%,transparent 75%,#eee 75%); background-size: 12px 12px; background-position: 0 0, 6px 6px;">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium" x-text="item.name"></p>
                    <p class="text-xs text-slate-500">
                        <span x-text="item.info"></span>
                        <template x-if="item.result">
                            <span>
                                → <b class="text-slate-700" x-text="item.result.name"></b>
                                <span :class="item.result.diff <= 0 ? 'text-emerald-600' : 'text-amber-600'"
                                      x-text="'（' + item.result.size + ' / ' + (item.result.diff > 0 ? '+' : '') + item.result.diff + '%）'"></span>
                            </span>
                        </template>
                    </p>
                    <p class="mt-0.5 text-xs text-rose-600" x-show="item.error" x-text="item.error"></p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a x-show="item.result" :href="item.result.url" :download="item.result.name"
                       class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">⬇️ 保存</a>
                    <button type="button" @click="items.splice(i, 1)"
                            class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs text-slate-500 hover:bg-slate-200">✕</button>
                </div>
            </div>
        </template>
    </div>

    <div class="mt-4 rounded-2xl bg-slate-50 p-4 text-xs text-slate-500">
        <p class="font-semibold text-slate-600">ℹ️ 仕様メモ</p>
        <ul class="mt-1 list-inside list-disc space-y-0.5">
            <li><b>PNG出力＝完全可逆</b>。JPEG/WebPは品質100%でも原理上わずかに劣化します（元がJPEGの場合、既にある劣化は元に戻せません）</li>
            <li>TIFF / HEIC はブラウザが直接読めないため、必要時に変換ライブラリを自動で読み込みます（要ネット接続）</li>
            <li>EXIF等のメタデータは変換時に削除されます（撮影場所などが残らないので共有時はむしろ安全）。EXIFの回転情報は反映済み</li>
            <li>処理は全てブラウザ内で完結し、画像がサーバーに送られることはありません</li>
        </ul>
    </div>
</div>

<script>
function imageConverter() {
  return {
    items: [], format: 'image/png', quality: 0.92, alphaMode: 'keep',
    bgColor: '#ffffff', maxSize: null, dragging: false, busy: false, doneCount: 0,
    seq: 0,
    formats: [
      { mime: 'image/png',  label: 'PNG',  ext: 'png'  },
      { mime: 'image/jpeg', label: 'JPEG', ext: 'jpg'  },
      { mime: 'image/webp', label: 'WebP', ext: 'webp' },
    ],
    get lossless() { return this.format === 'image/png'; },
    get supportsAlpha() { return this.format !== 'image/jpeg'; },

    addFiles(fileList) {
      for (const file of fileList) {
        const id = ++this.seq;
        const item = {
          id, file, name: file.name, preview: '', info: this.fmtSize(file.size),
          result: null, error: '',
        };
        this.items.push(item);
        // サムネイル（読めない形式は変換時に対応するのでここでは失敗を許容）
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
          item.preview = url;
          item.info = `${img.naturalWidth}×${img.naturalHeight} / ${this.fmtSize(file.size)}`;
        };
        img.onerror = () => { item.preview = ''; };
        img.src = url;
      }
    },

    fmtSize(bytes) {
      if (bytes < 1024) return bytes + 'B';
      if (bytes < 1048576) return (bytes / 1024).toFixed(1) + 'KB';
      return (bytes / 1048576).toFixed(2) + 'MB';
    },

    // 外部デコーダを必要な時だけ読み込む（TIFF/HEIC用）
    loadScript(src) {
      return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) return resolve();
        const s = document.createElement('script');
        s.src = src;
        s.onload = resolve;
        s.onerror = () => reject(new Error('ライブラリの読み込みに失敗しました（ネット接続を確認）'));
        document.head.appendChild(s);
      });
    },

    async decode(file) {
      // ① ブラウザがそのまま読める形式（EXIF回転も反映）
      try {
        return await createImageBitmap(file, { imageOrientation: 'from-image' });
      } catch (e) { /* 次へ */ }

      const name = file.name.toLowerCase();

      // ② HEIC / HEIF
      if (/\.(heic|heif)$/.test(name) || /heic|heif/.test(file.type)) {
        await this.loadScript('https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js');
        const png = await heic2any({ blob: file, toType: 'image/png' });
        return await createImageBitmap(Array.isArray(png) ? png[0] : png);
      }

      // ③ TIFF
      if (/\.(tif|tiff)$/.test(name) || /tiff/.test(file.type)) {
        await this.loadScript('https://cdn.jsdelivr.net/npm/utif@3.1.0/UTIF.min.js');
        const buf = await file.arrayBuffer();
        const ifds = UTIF.decode(buf);
        UTIF.decodeImage(buf, ifds[0], ifds);
        const rgba = UTIF.toRGBA8(ifds[0]);
        const cv = document.createElement('canvas');
        cv.width = ifds[0].width; cv.height = ifds[0].height;
        cv.getContext('2d').putImageData(new ImageData(new Uint8ClampedArray(rgba), cv.width, cv.height), 0, 0);
        return await createImageBitmap(cv);
      }

      throw new Error('この形式は読み込めませんでした');
    },

    async convertOne(item) {
      item.error = ''; item.result = null;
      const bmp = await this.decode(item.file);

      // リサイズ（長辺基準・拡大はしない）
      let w = bmp.width, h = bmp.height;
      if (this.maxSize && this.maxSize > 0 && Math.max(w, h) > this.maxSize) {
        const r = this.maxSize / Math.max(w, h);
        w = Math.round(w * r); h = Math.round(h * r);
      }

      const cv = document.createElement('canvas');
      cv.width = w; cv.height = h;
      const ctx = cv.getContext('2d');
      ctx.imageSmoothingQuality = 'high';

      // 透過の扱い：JPEGは必ず塗りつぶし、それ以外は設定に従う
      if (!this.supportsAlpha || this.alphaMode === 'flatten') {
        ctx.fillStyle = this.bgColor;
        ctx.fillRect(0, 0, w, h);
      }
      ctx.drawImage(bmp, 0, 0, w, h);
      bmp.close?.();

      const fmt = this.formats.find(f => f.mime === this.format);
      const blob = await new Promise((res, rej) =>
        cv.toBlob(b => b ? res(b) : rej(new Error('変換に失敗しました')), this.format,
                  this.lossless ? undefined : this.quality));

      const base = item.name.replace(/\.[^.]+$/, '');
      item.result = {
        url: URL.createObjectURL(blob),
        name: `${base}.${fmt.ext}`,
        size: this.fmtSize(blob.size),
        diff: Math.round((blob.size - item.file.size) / item.file.size * 100),
        dims: `${w}×${h}`,
      };
    },

    async convertAll() {
      this.busy = true; this.doneCount = 0;
      for (const item of this.items) {
        try { await this.convertOne(item); }
        catch (e) { item.error = e.message || '変換に失敗しました'; }
        this.doneCount++;
      }
      this.busy = false;
    },

    async downloadAll() {
      // ブラウザが連続ダウンロードをブロックしないよう少し間隔を空ける
      for (const item of this.items) {
        if (!item.result) continue;
        const a = document.createElement('a');
        a.href = item.result.url; a.download = item.result.name;
        document.body.appendChild(a); a.click(); a.remove();
        await new Promise(r => setTimeout(r, 300));
      }
    },
  };
}
</script>
@endsection
