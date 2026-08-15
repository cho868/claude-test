@extends('layouts.app')
@section('title', '文字変換')

@section('content')
<x-page-header title="文字変換ツール" icon="🔤" subtitle="装飾フォント・ケース変換・エンコード・整形など。すべてブラウザ内で処理" />

<div x-data="textTools()" x-cloak>

    {{-- 入力 --}}
    <div class="mb-4 rounded-2xl bg-white p-5 shadow-sm">
        <div class="mb-2 flex items-center justify-between">
            <label class="text-sm font-medium text-slate-700">入力</label>
            <span class="text-xs text-slate-400" x-text="stats"></span>
        </div>
        <textarea x-model="input" rows="4" placeholder="ここに文字を入力…"
                  class="w-full rounded-lg border-slate-300 text-sm shadow-sm"></textarea>
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" @click="input = ''" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">クリア</button>
            <button type="button" @click="paste()" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">📋 貼り付け</button>
        </div>
    </div>

    {{-- タブ --}}
    <div class="mb-4 flex flex-wrap gap-1.5">
        <template x-for="t in tabs" :key="t.id">
            <button type="button" @click="tab = t.id"
                    :class="tab === t.id ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                    class="rounded-lg px-3 py-2 text-sm font-semibold shadow-sm" x-text="t.label"></button>
        </template>
    </div>

    {{-- 装飾フォント --}}
    <div x-show="tab === 'font'" class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-1 font-bold">✨ 装飾フォント</h3>
        <p class="mb-3 text-xs text-slate-500">SNSでそのまま使えるUnicode文字に変換。タップでコピー。</p>
        <div class="space-y-1.5">
            <template x-for="f in fontList" :key="f.key">
                <button type="button" @click="copy(f.out)"
                        class="flex w-full items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 text-left hover:bg-slate-50">
                    <span class="w-24 shrink-0 text-xs text-slate-400" x-text="f.label"></span>
                    <span class="min-w-0 flex-1 truncate text-lg" x-text="f.out || '（入力してください）'"></span>
                    <span class="shrink-0 text-xs text-slate-400" x-text="copied === f.out ? '✓ コピー' : '📋'"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- ケース変換 --}}
    <div x-show="tab === 'case'" class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">🔠 ケース変換</h3>
        <div class="space-y-1.5">
            <template x-for="c in caseList" :key="c.label">
                <button type="button" @click="copy(c.out)"
                        class="flex w-full items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 text-left hover:bg-slate-50">
                    <span class="w-32 shrink-0 text-xs text-slate-400" x-text="c.label"></span>
                    <span class="min-w-0 flex-1 truncate font-mono text-sm" x-text="c.out"></span>
                    <span class="shrink-0 text-xs text-slate-400" x-text="copied === c.out ? '✓' : '📋'"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- エンコード --}}
    <div x-show="tab === 'encode'" class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">🔗 エンコード / デコード</h3>
        <div class="space-y-1.5">
            <template x-for="e in encodeList" :key="e.label">
                <button type="button" @click="copy(e.out)"
                        class="flex w-full items-start gap-3 rounded-lg border border-slate-100 px-3 py-2 text-left hover:bg-slate-50">
                    <span class="w-36 shrink-0 text-xs text-slate-400" x-text="e.label"></span>
                    <span class="min-w-0 flex-1 break-all font-mono text-xs" x-text="e.out"></span>
                    <span class="shrink-0 text-xs text-slate-400" x-text="copied === e.out ? '✓' : '📋'"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- 整形（JSON / 行操作） --}}
    <div x-show="tab === 'format'" class="space-y-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">📐 JSON整形</h3>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="jsonFormat(2)" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold hover:bg-slate-200">整形（スペース2）</button>
                <button type="button" @click="jsonFormat(4)" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold hover:bg-slate-200">整形（スペース4）</button>
                <button type="button" @click="jsonMinify()" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold hover:bg-slate-200">圧縮（1行）</button>
            </div>
            <p class="mt-2 text-xs" :class="jsonError ? 'text-rose-600' : 'text-slate-400'"
               x-text="jsonError || '結果は入力欄に反映されます'"></p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">📋 行の操作</h3>
            <div class="flex flex-wrap gap-2">
                <template x-for="op in lineOps" :key="op.label">
                    <button type="button" @click="input = op.fn(input)"
                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold hover:bg-slate-200" x-text="op.label"></button>
                </template>
            </div>
        </div>
    </div>

    {{-- ハッシュ --}}
    <div x-show="tab === 'hash'" class="rounded-2xl bg-white p-5 shadow-sm">
        <h3 class="mb-3 font-bold">🔐 ハッシュ</h3>
        <div class="space-y-1.5">
            <template x-for="h in hashes" :key="h.label">
                <button type="button" @click="copy(h.out)"
                        class="flex w-full items-start gap-3 rounded-lg border border-slate-100 px-3 py-2 text-left hover:bg-slate-50">
                    <span class="w-20 shrink-0 text-xs text-slate-400" x-text="h.label"></span>
                    <span class="min-w-0 flex-1 break-all font-mono text-xs" x-text="h.out"></span>
                </button>
            </template>
        </div>
        <p class="mt-2 text-xs text-slate-400">※ HTTPS接続時のみ利用できます（ブラウザの仕様）</p>
    </div>

    {{-- カラー / 日時 --}}
    <div x-show="tab === 'misc'" class="space-y-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">🎨 カラーコード変換</h3>
            <div class="flex flex-wrap items-center gap-3">
                <input type="color" x-model="color" class="h-10 w-16 cursor-pointer rounded border-slate-300">
                <input type="text" x-model="color" class="w-32 rounded-lg border-slate-300 font-mono text-sm shadow-sm">
            </div>
            <div class="mt-3 space-y-1.5">
                <template x-for="c in colorList" :key="c.label">
                    <button type="button" @click="copy(c.out)"
                            class="flex w-full items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 text-left hover:bg-slate-50">
                        <span class="w-16 shrink-0 text-xs text-slate-400" x-text="c.label"></span>
                        <span class="flex-1 font-mono text-sm" x-text="c.out"></span>
                        <span class="shrink-0 text-xs text-slate-400" x-text="copied === c.out ? '✓' : '📋'"></span>
                    </button>
                </template>
            </div>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <h3 class="mb-3 font-bold">🕐 日時 ⇔ UNIXタイムスタンプ</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs text-slate-500">日時</label>
                    <input type="datetime-local" x-model="dt" @input="dtToTs()" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-500">タイムスタンプ（秒）</label>
                    <input type="number" x-model="ts" @input="tsToDt()" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm">
                </div>
            </div>
            <button type="button" @click="nowStamp()" class="mt-3 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold hover:bg-slate-200">今の時刻にする</button>
        </div>
    </div>
</div>

<script>
// MD5（SubtleCryptoが対応していないため実装。RFC1321のとおり）
function md5(str) {
  const S = [
    7,12,17,22, 7,12,17,22, 7,12,17,22, 7,12,17,22,
    5, 9,14,20, 5, 9,14,20, 5, 9,14,20, 5, 9,14,20,
    4,11,16,23, 4,11,16,23, 4,11,16,23, 4,11,16,23,
    6,10,15,21, 6,10,15,21, 6,10,15,21, 6,10,15,21,
  ];
  const K = [...Array(64)].map((_, i) => Math.floor(Math.abs(Math.sin(i + 1)) * 4294967296));
  // 32bit加算（桁あふれを16bitずつ処理して符号の事故を防ぐ）
  const add = (x, y) => {
    const l = (x & 0xFFFF) + (y & 0xFFFF);
    const m = (x >> 16) + (y >> 16) + (l >> 16);
    return (m << 16) | (l & 0xFFFF);
  };
  const rl = (n, c) => (n << c) | (n >>> (32 - c));

  // UTF-8バイト列 → 32bitワード列（末尾にビット長を付ける）
  const bytes = new TextEncoder().encode(str);
  const nWords = (((bytes.length + 8) >> 6) + 1) * 16;
  const x = new Array(nWords).fill(0);
  for (let i = 0; i < bytes.length; i++) x[i >> 2] |= bytes[i] << ((i % 4) * 8);
  x[bytes.length >> 2] |= 0x80 << ((bytes.length % 4) * 8);
  x[nWords - 2] = bytes.length * 8;

  let a0 = 1732584193, b0 = -271733879, c0 = -1732584194, d0 = 271733878;
  for (let i = 0; i < nWords; i += 16) {
    let [a, b, c, d] = [a0, b0, c0, d0];
    for (let j = 0; j < 64; j++) {
      let f, g;
      if (j < 16)      { f = (b & c) | (~b & d);      g = j; }
      else if (j < 32) { f = (d & b) | (~d & c);      g = (5 * j + 1) % 16; }
      else if (j < 48) { f = b ^ c ^ d;               g = (3 * j + 5) % 16; }
      else             { f = c ^ (b | ~d);            g = (7 * j) % 16; }
      const tmp = d;
      d = c; c = b;
      b = add(b, rl(add(add(a, f), add(K[j], x[i + g])), S[j]));
      a = tmp;
    }
    a0 = add(a0, a); b0 = add(b0, b); c0 = add(c0, c); d0 = add(d0, d);
  }
  const hex = (n) => {
    let s = '';
    for (let i = 0; i < 4; i++) s += ((n >> (i * 8)) & 0xFF).toString(16).padStart(2, '0');
    return s;
  };
  return hex(a0) + hex(b0) + hex(c0) + hex(d0);
}

function textTools() {
  const U = c => String.fromCodePoint(c);
  const build = (upper, lower, digit, ex = {}) => {
    const m = {};
    for (let i = 0; i < 26; i++) {
      if (upper) m[String.fromCharCode(65 + i)] = U(upper + i);
      if (lower) m[String.fromCharCode(97 + i)] = U(lower + i);
    }
    if (digit) for (let i = 0; i < 10; i++) m[String(i)] = U(digit + i);
    return Object.assign(m, ex);
  };
  const fromPairs = (chars, from) => Object.fromEntries([...chars].map((c, i) => [from[i], c]));

  const FONTS = [
    { key:'bold',   label:'太字',        map: build(0x1D400, 0x1D41A, 0x1D7CE) },
    { key:'italic', label:'斜体',        map: build(0x1D434, 0x1D44E, null, { h:'ℎ' }) },
    { key:'bi',     label:'太字斜体',    map: build(0x1D468, 0x1D482) },
    { key:'sansB',  label:'ゴシック太字',map: build(0x1D5D4, 0x1D5EE, 0x1D7EC) },
    { key:'sansI',  label:'ゴシック斜体',map: build(0x1D608, 0x1D622) },
    { key:'script', label:'筆記体',      map: build(0x1D49C, 0x1D4B6, null,
                      { B:'ℬ',E:'ℰ',F:'ℱ',H:'ℋ',I:'ℐ',L:'ℒ',M:'ℳ',R:'ℛ',e:'ℯ',g:'ℊ',o:'ℴ' }) },
    { key:'scriptB',label:'筆記体太字',  map: build(0x1D4D0, 0x1D4EA) },
    { key:'frak',   label:'ドイツ文字',  map: build(0x1D504, 0x1D51E, null,
                      { C:'ℭ',H:'ℌ',I:'ℑ',R:'ℜ',Z:'ℨ' }) },
    { key:'frakB',  label:'ドイツ文字太',map: build(0x1D56C, 0x1D586) },
    { key:'ds',     label:'白抜き',      map: build(0x1D538, 0x1D552, 0x1D7D8,
                      { C:'ℂ',H:'ℍ',N:'ℕ',P:'ℙ',Q:'ℚ',R:'ℝ',Z:'ℤ' }) },
    { key:'mono',   label:'等幅',        map: build(0x1D670, 0x1D68A, 0x1D7F6) },
    { key:'full',   label:'全角',        map: build(0xFF21, 0xFF41, 0xFF10, { ' ':'　' }) },
    { key:'circle', label:'丸囲み',      map: build(0x24B6, 0x24D0, null, Object.assign(
                      { '0':'⓪' }, Object.fromEntries([...Array(9)].map((_, i) => [String(i+1), U(0x2460+i)])))) },
    { key:'sq',     label:'四角囲み',    map: build(0x1F130, 0x1F130) },
    { key:'negC',   label:'黒丸囲み',    map: build(0x1F150, 0x1F150) },
    { key:'sup',    label:'上付き',      map: Object.assign(
                      fromPairs('ᵃᵇᶜᵈᵉᶠᵍʰⁱʲᵏˡᵐⁿᵒᵖqʳˢᵗᵘᵛʷˣʸᶻ', 'abcdefghijklmnopqrstuvwxyz'),
                      fromPairs('⁰¹²³⁴⁵⁶⁷⁸⁹', '0123456789')) },
    { key:'sub',    label:'下付き',      map: Object.assign(
                      fromPairs('ₐbcdₑfgₕᵢⱼₖₗₘₙₒₚqᵣₛₜᵤᵥwₓyz', 'abcdefghijklmnopqrstuvwxyz'),
                      fromPairs('₀₁₂₃₄₅₆₇₈₉', '0123456789')) },
    { key:'small',  label:'小型大文字',  map: fromPairs('ᴀʙᴄᴅᴇꜰɢʜɪᴊᴋʟᴍɴᴏᴘqʀsᴛᴜᴠᴡxʏᴢ', 'abcdefghijklmnopqrstuvwxyz') },
  ];

  const UPSIDE = fromPairs("ɐqɔpǝɟƃɥıɾʞlɯuodbɹsʇnʌʍxʎz", "abcdefghijklmnopqrstuvwxyz");
  Object.assign(UPSIDE, fromPairs("∀ᗺƆᗡƎℲƃHIſʞ˥WNOԀΌᴚS⊥∩ΛMX⅄Z", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"));
  Object.assign(UPSIDE, fromPairs("0ƖᄅƐㄣϛ9ㄥ86", "0123456789"), { '.':'˙', ',':"'", '?':'¿', '!':'¡', '(':')', ')':'(' });

  const conv = (s, m) => [...s].map(c => m[c] ?? c).join('');
  const words = s => s.replace(/[_\-]+/g, ' ').replace(/([a-z0-9])([A-Z])/g, '$1 $2')
                      .split(/\s+/).filter(Boolean);

  return {
    input: '', tab: 'font', copied: '', color: '#3b82f6', dt: '', ts: '', jsonError: '',
    tabs: [
      { id:'font',   label:'✨ 装飾フォント' },
      { id:'case',   label:'🔠 ケース' },
      { id:'encode', label:'🔗 エンコード' },
      { id:'format', label:'📐 整形' },
      { id:'hash',   label:'🔐 ハッシュ' },
      { id:'misc',   label:'🎨 色/日時' },
    ],
    hashes: [],

    init() {
      this.nowStamp();
      this.$watch('input', () => { if (this.tab === 'hash') this.calcHash(); });
      this.$watch('tab', v => { if (v === 'hash') this.calcHash(); });
    },

    get stats() {
      const s = this.input;
      const bytes = new TextEncoder().encode(s).length;
      return `${[...s].length}文字 / ${s ? s.split(/\n/).length : 0}行 / ${bytes}バイト`;
    },

    get fontList() {
      const extra = [
        { key:'up',     label:'上下反転',   out: [...conv(this.input.toLowerCase(), UPSIDE)].reverse().join('') },
        { key:'strike', label:'取り消し線', out: [...this.input].map(c => c + '̶').join('') },
        { key:'under',  label:'下線',       out: [...this.input].map(c => c + '̲').join('') },
        { key:'space',  label:'空白入り',   out: [...this.input].join(' ') },
      ];
      return FONTS.map(f => ({ key: f.key, label: f.label, out: conv(this.input, f.map) }))
                  .concat(this.input ? extra : extra.map(e => ({ ...e, out: '' })));
    },

    get caseList() {
      const w = words(this.input);
      return [
        { label:'大文字',       out: this.input.toUpperCase() },
        { label:'小文字',       out: this.input.toLowerCase() },
        { label:'先頭大文字',   out: this.input.replace(/\b\w/g, c => c.toUpperCase()) },
        { label:'camelCase',    out: w.map((x, i) => i ? x[0].toUpperCase() + x.slice(1).toLowerCase() : x.toLowerCase()).join('') },
        { label:'PascalCase',   out: w.map(x => x[0].toUpperCase() + x.slice(1).toLowerCase()).join('') },
        { label:'snake_case',   out: w.map(x => x.toLowerCase()).join('_') },
        { label:'kebab-case',   out: w.map(x => x.toLowerCase()).join('-') },
        { label:'CONSTANT',     out: w.map(x => x.toUpperCase()).join('_') },
        { label:'全角→半角',    out: this.input.replace(/[Ａ-Ｚａ-ｚ０-９]/g, c => String.fromCharCode(c.charCodeAt(0) - 0xFEE0)).replace(/　/g, ' ') },
        { label:'半角→全角',    out: this.input.replace(/[A-Za-z0-9]/g, c => String.fromCharCode(c.charCodeAt(0) + 0xFEE0)).replace(/ /g, '　') },
      ];
    },

    get encodeList() {
      const s = this.input;
      const try_ = fn => { try { return fn(); } catch (e) { return '(変換できません)'; } };
      return [
        { label:'URLエンコード',   out: try_(() => encodeURIComponent(s)) },
        { label:'URLデコード',     out: try_(() => decodeURIComponent(s)) },
        { label:'Base64',          out: try_(() => btoa(String.fromCharCode(...new TextEncoder().encode(s)))) },
        { label:'Base64デコード',  out: try_(() => new TextDecoder().decode(Uint8Array.from(atob(s), c => c.charCodeAt(0)))) },
        { label:'HTMLエスケープ',  out: s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])) },
        { label:'Unicodeエスケープ', out: [...s].map(c => c.codePointAt(0) > 127 ? '\\u' + c.codePointAt(0).toString(16).padStart(4,'0') : c).join('') },
      ];
    },

    get lineOps() {
      const lines = s => s.split(/\r?\n/);
      return [
        { label:'並べ替え（昇順）', fn: s => lines(s).sort().join('\n') },
        { label:'並べ替え（降順）', fn: s => lines(s).sort().reverse().join('\n') },
        { label:'重複を削除',       fn: s => [...new Set(lines(s))].join('\n') },
        { label:'空行を削除',       fn: s => lines(s).filter(l => l.trim()).join('\n') },
        { label:'行を逆順',         fn: s => lines(s).reverse().join('\n') },
        { label:'前後の空白を削除', fn: s => lines(s).map(l => l.trim()).join('\n') },
        { label:'連番をつける',     fn: s => lines(s).map((l, i) => `${i + 1}. ${l}`).join('\n') },
      ];
    },

    get colorList() {
      const m = /^#?([0-9a-f]{6})$/i.exec(this.color.trim());
      if (!m) return [{ label:'HEX', out:'(不正な色コード)' }];
      const n = parseInt(m[1], 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
      const max = Math.max(r,g,b)/255, min = Math.min(r,g,b)/255, l = (max+min)/2;
      const d = max - min;
      const sat = d === 0 ? 0 : d / (1 - Math.abs(2*l - 1));
      let h = 0;
      if (d !== 0) {
        const rr=r/255, gg=g/255, bb=b/255;
        h = max === rr ? ((gg-bb)/d) % 6 : max === gg ? (bb-rr)/d + 2 : (rr-gg)/d + 4;
        h = Math.round(h * 60); if (h < 0) h += 360;
      }
      return [
        { label:'HEX', out: '#' + m[1].toLowerCase() },
        { label:'RGB', out: `rgb(${r}, ${g}, ${b})` },
        { label:'HSL', out: `hsl(${h}, ${Math.round(sat*100)}%, ${Math.round(l*100)}%)` },
      ];
    },

    async calcHash() {
      const data = new TextEncoder().encode(this.input);
      const out = [{ label: 'MD5', out: md5(this.input) }];
      if (!crypto?.subtle) {
        this.hashes = out.concat([{ label:'SHA系', out:'HTTPS接続時のみ利用できます' }]);
        return;
      }
      for (const algo of ['SHA-1', 'SHA-256', 'SHA-512']) {
        const buf = await crypto.subtle.digest(algo, data);
        out.push({ label: algo, out: [...new Uint8Array(buf)].map(b => b.toString(16).padStart(2,'0')).join('') });
      }
      this.hashes = out;
    },

    jsonFormat(n) {
      try { this.input = JSON.stringify(JSON.parse(this.input), null, n); this.jsonError = ''; }
      catch (e) { this.jsonError = 'JSONとして解析できません: ' + e.message; }
    },
    jsonMinify() {
      try { this.input = JSON.stringify(JSON.parse(this.input)); this.jsonError = ''; }
      catch (e) { this.jsonError = 'JSONとして解析できません: ' + e.message; }
    },

    nowStamp() { const d = new Date(); this.ts = Math.floor(d.getTime()/1000); this.tsToDt(); },
    tsToDt() {
      const d = new Date(Number(this.ts) * 1000);
      if (isNaN(d)) return;
      const p = n => String(n).padStart(2, '0');
      this.dt = `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
    },
    dtToTs() { const d = new Date(this.dt); if (!isNaN(d)) this.ts = Math.floor(d.getTime()/1000); },

    async copy(text) {
      if (!text) return;
      try { await navigator.clipboard.writeText(text); } catch (e) {
        const ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); ta.remove();
      }
      this.copied = text;
      setTimeout(() => { if (this.copied === text) this.copied = ''; }, 1500);
    },
    async paste() {
      try { this.input = await navigator.clipboard.readText(); } catch (e) { /* 権限拒否時は何もしない */ }
    },
  };
}
</script>
@endsection
