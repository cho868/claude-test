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
                JPEG・PNG・WebP・GIF・BMP・AVIF・TIFF・HEIC・PDF / 複数まとめてOK
            </p>
            <input type="file" accept="image/*,.heic,.heif,.tif,.tiff,.pdf" multiple class="hidden"
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
                <label class="block text-sm font-medium text-slate-700" x-show="!isPdf || true">最大サイズ（任意）</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="number" x-model.number="maxSize" min="0" step="100" placeholder="変更しない"
                           class="w-32 rounded-lg border-slate-300 text-sm shadow-sm">
                    <span class="text-sm text-slate-500">px（長辺）</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">空欄/0なら元の解像度のまま</p>
            </div>
        </div>

        {{-- 色を指定して透明化（クロマキー） --}}
        <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-3">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" x-model="chroma" @change="if (chroma && (format === 'image/jpeg' || isPdf)) format = 'image/png'"
                       class="rounded border-slate-300">
                🪄 指定した色を透明にする
            </label>
            <div x-show="chroma" class="mt-3 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-slate-500">透明にする色</span>
                    <input type="color" x-model="chromaColor" class="h-9 w-14 cursor-pointer rounded border-slate-300">
                    <input type="text" x-model="chromaColor" class="w-24 rounded-lg border-slate-300 font-mono text-xs shadow-sm">
                    <button type="button" @click="items[0] && detectBg(items[0])" x-show="items.length"
                            class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm hover:bg-slate-100">
                        🔍 背景色を自動検出
                    </button>
                </div>
                <div>
                    <label class="block text-xs text-slate-500">
                        許容範囲 <span class="font-mono" x-text="tolerance + '%'"></span>
                        <span class="text-slate-400">（大きいほど似た色もまとめて透明に）</span>
                    </label>
                    <input type="range" min="0" max="60" step="1" x-model.number="tolerance" class="mt-1 w-full">
                </div>
                <p class="text-xs text-amber-600" x-show="!supportsAlpha">
                    ⚠️ 透明化するには出力形式を PNG か WebP にしてください（JPEG/PDFは透過を保存できません）
                </p>
                <p class="text-xs text-slate-400">
                    白背景のロゴやスクショの背景抜きに便利。境界は自動でなめらかにします。
                </p>
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

    {{-- PDF出力の結果 --}}
    <div x-show="pdfUrl" class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-emerald-300 bg-emerald-50 p-4">
        <span class="text-3xl">📄</span>
        <div class="min-w-0 flex-1">
            <p class="font-bold text-emerald-800">PDFを作成しました</p>
            <p class="text-xs text-emerald-700" x-text="`${pdfName} / ${pdfSize} / 全${items.length}ページ`"></p>
        </div>
        <a :href="pdfUrl" :download="pdfName"
           class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">⬇️ PDFを保存</a>
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
            <li><b>PDF出力</b>は複数画像を1つのPDF（1画像=1ページ）にまとめます。PDFを読み込むと1ページ目を画像として取り込みます</li>
            <li><b>色の透明化</b>はPNG/WebP出力時のみ有効（JPEG/PDFは透過を保存できない形式のため）</li>
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
    // 色指定の透明化（クロマキー）
    chroma: false, chromaColor: '#ffffff', tolerance: 12,
    pdfUrl: '', pdfName: '', pdfSize: '',
    formats: [
      { mime: 'image/png',  label: 'PNG',  ext: 'png'  },
      { mime: 'image/jpeg', label: 'JPEG', ext: 'jpg'  },
      { mime: 'image/webp', label: 'WebP', ext: 'webp' },
      { mime: 'application/pdf', label: 'PDF', ext: 'pdf' },
    ],
    get lossless() { return this.format === 'image/png'; },
    get isPdf() { return this.format === 'application/pdf'; },
    get supportsAlpha() { return this.format !== 'image/jpeg' && !this.isPdf; },

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

      // ④ PDF（1ページ目を画像として取り込む）
      if (/\.pdf$/.test(name) || /pdf/.test(file.type)) {
        await this.loadScript('https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/build/pdf.min.mjs');
        const pdfjs = window.pdfjsLib || globalThis.pdfjsLib;
        if (!pdfjs) throw new Error('PDFの読み込みに失敗しました');
        pdfjs.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.379/build/pdf.worker.min.mjs';
        const doc = await pdfjs.getDocument({ data: await file.arrayBuffer() }).promise;
        const page = await doc.getPage(1);
        const vp = page.getViewport({ scale: 2 });   // 2倍で描画して精細に
        const cv = document.createElement('canvas');
        cv.width = vp.width; cv.height = vp.height;
        await page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise;
        return await createImageBitmap(cv);
      }

      throw new Error('この形式は読み込めませんでした');
    },

    /** 指定色を透明にする（色距離がしきい値以内のピクセルのalphaを0に） */
    applyChroma(ctx, w, h) {
      const m = /^#?([0-9a-f]{6})$/i.exec(this.chromaColor);
      if (!m) return;
      const n = parseInt(m[1], 16);
      const tr = n >> 16, tg = (n >> 8) & 255, tb = n & 255;
      // 許容値(0-100%)を色距離の二乗に変換（最大距離は約441）
      const limit = Math.pow(this.tolerance / 100 * 441, 2);

      const img = ctx.getImageData(0, 0, w, h);
      const d = img.data;
      for (let i = 0; i < d.length; i += 4) {
        if (d[i + 3] === 0) continue;
        const dr = d[i] - tr, dg = d[i + 1] - tg, db = d[i + 2] - tb;
        const dist = dr * dr + dg * dg + db * db;
        if (dist <= limit) {
          d[i + 3] = 0;
        } else if (dist <= limit * 2.2) {
          // 境界をなめらかに（ジャギー防止）
          d[i + 3] = Math.round(d[i + 3] * Math.min(1, (dist - limit) / (limit * 1.2 + 1)));
        }
      }
      ctx.putImageData(img, 0, 0);
    },

    /** 画像の四隅から背景色を推定する */
    async detectBg(item) {
      try {
        const bmp = await this.decode(item.file);
        const cv = document.createElement('canvas');
        cv.width = bmp.width; cv.height = bmp.height;
        const ctx = cv.getContext('2d');
        ctx.drawImage(bmp, 0, 0);
        const pts = [[0, 0], [cv.width - 1, 0], [0, cv.height - 1], [cv.width - 1, cv.height - 1]];
        const counts = {};
        for (const [x, y] of pts) {
          const p = ctx.getImageData(x, y, 1, 1).data;
          const hex = '#' + [p[0], p[1], p[2]].map(v => v.toString(16).padStart(2, '0')).join('');
          counts[hex] = (counts[hex] || 0) + 1;
        }
        this.chromaColor = Object.entries(counts).sort((a, b) => b[1] - a[1])[0][0];
        this.chroma = true;
        if (this.format === 'image/jpeg' || this.isPdf) this.format = 'image/png';
      } catch (e) { /* 読めない形式は無視 */ }
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

      // 指定色を透明化（PNG/WebP のときのみ意味がある）
      if (this.chroma && this.supportsAlpha && this.alphaMode !== 'flatten') {
        this.applyChroma(ctx, w, h);
      }

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
      this.busy = true; this.doneCount = 0; this.pdfUrl = '';
      try {
        if (this.isPdf) { await this.buildPdf(); }
        else {
          for (const item of this.items) {
            try { await this.convertOne(item); }
            catch (e) { item.error = e.message || '変換に失敗しました'; }
            this.doneCount++;
          }
        }
      } finally { this.busy = false; }
    },

    /** 全画像を1つのPDFにまとめる（1画像=1ページ・向きは自動） */
    async buildPdf() {
      await this.loadScript('https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js');
      const { jsPDF } = window.jspdf;
      let pdf = null;

      for (const item of this.items) {
        item.error = ''; item.result = null;
        try {
          const bmp = await this.decode(item.file);
          let w = bmp.width, h = bmp.height;
          if (this.maxSize && this.maxSize > 0 && Math.max(w, h) > this.maxSize) {
            const r = this.maxSize / Math.max(w, h);
            w = Math.round(w * r); h = Math.round(h * r);
          }
          const cv = document.createElement('canvas');
          cv.width = w; cv.height = h;
          const ctx = cv.getContext('2d');
          // PDFは透過を扱えないので必ず背景を塗る
          ctx.fillStyle = this.bgColor;
          ctx.fillRect(0, 0, w, h);
          ctx.drawImage(bmp, 0, 0, w, h);
          bmp.close?.();

          const data = cv.toDataURL('image/jpeg', this.quality);
          const orient = w >= h ? 'l' : 'p';
          if (!pdf) pdf = new jsPDF({ orientation: orient, unit: 'px', format: [w, h] });
          else pdf.addPage([w, h], orient);
          pdf.addImage(data, 'JPEG', 0, 0, w, h);
        } catch (e) {
          item.error = e.message || '変換に失敗しました';
        }
        this.doneCount++;
      }

      if (!pdf) throw new Error('PDFにできる画像がありませんでした');
      const blob = pdf.output('blob');
      this.pdfUrl = URL.createObjectURL(blob);
      this.pdfName = (this.items[0]?.name.replace(/\.[^.]+$/, '') || 'images') + '.pdf';
      this.pdfSize = this.fmtSize(blob.size);
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
