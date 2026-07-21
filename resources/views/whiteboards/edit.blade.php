@extends('layouts.app')
@section('title', $board->exists ? 'ホワイトボード編集' : 'ホワイトボード作成')

@section('content')
<x-page-header :title="$board->exists ? 'ホワイトボードを編集' : '手書きホワイトボード'" icon="🖊️"
    back="{{ route('whiteboards.index') }}"
    subtitle="指やペンで手書き。スマホで書いて、家の大画面で確認できます" />

<form method="POST"
      action="{{ $board->exists ? route('whiteboards.update', $board) : route('whiteboards.store') }}"
      x-data="whiteboard(@js($board->exists ? route('whiteboards.show', $board) : null), @js($board->image_data ?: null))"
      @submit="prepare()">
    @csrf
    @if ($board->exists) @method('PUT') @endif
    <input type="hidden" name="image_data" x-ref="dataInput">

    <div class="mb-3 flex flex-wrap items-center gap-3">
        <input type="text" name="title" value="{{ old('title', $board->title) }}" maxlength="100" required
               placeholder="タイトル"
               class="w-48 rounded-lg border-slate-300 text-sm shadow-sm">
        <label class="flex items-center gap-1.5 text-sm text-slate-600">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $board->is_public ?? true)) class="rounded border-slate-300">
            身内に共有
        </label>
    </div>

    {{-- ツールバー --}}
    <div class="mb-2 flex flex-wrap items-center gap-2 rounded-xl bg-white p-2 shadow-sm">
        <div class="flex items-center gap-1">
            @foreach (['#1e293b' => '黒', '#ef4444' => '赤', '#3b82f6' => '青', '#22c55e' => '緑', '#f59e0b' => '橙'] as $c => $label)
                <button type="button" @click="setColor('{{ $c }}')"
                        :class="(color === '{{ $c }}' && !erasing) ? 'ring-2 ring-offset-1 ring-slate-500' : ''"
                        class="h-7 w-7 rounded-full border border-slate-200" style="background: {{ $c }}"
                        title="{{ $label }}"></button>
            @endforeach
        </div>
        <span class="mx-1 h-6 w-px bg-slate-200"></span>
        <div class="flex items-center gap-1">
            @foreach ([3 => 'S', 7 => 'M', 14 => 'L'] as $w => $label)
                <button type="button" @click="setSize({{ $w }})"
                        :class="(size === {{ $w }} && !erasing) ? 'bg-slate-800 text-white' : 'bg-slate-100'"
                        class="h-7 w-7 rounded-lg text-xs font-bold">{{ $label }}</button>
            @endforeach
        </div>
        <span class="mx-1 h-6 w-px bg-slate-200"></span>
        <button type="button" @click="toggleEraser()"
                :class="erasing ? 'bg-slate-800 text-white' : 'bg-slate-100'"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold">🩹 消しゴム</button>
        <button type="button" @click="undo()" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold">↩︎ 戻す</button>
        <button type="button" @click="clearAll()" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold">🗑 全消し</button>
    </div>

    {{-- キャンバス --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <canvas x-ref="canvas" class="block w-full touch-none" style="aspect-ratio: 4 / 3;"></canvas>
    </div>

    <div class="mt-3 flex items-center gap-2">
        <x-btn type="submit">💾 保存する</x-btn>
        <a href="{{ route('whiteboards.index') }}" class="text-sm text-slate-500 hover:underline">キャンセル</a>
    </div>
</form>

<script>
function whiteboard(showUrl, initialData) {
  return {
    color: '#1e293b', size: 3, erasing: false,
    ctx: null, drawing: false, last: null, stack: [],
    init() {
      const cv = this.$refs.canvas;
      // 内部解像度は固定(保存/再編集で座標を一致させる)。CSSで表示幅にスケール。
      cv.width = 1280; cv.height = 960;
      this.ctx = cv.getContext('2d');
      this.ctx.fillStyle = '#ffffff';
      this.ctx.fillRect(0, 0, cv.width, cv.height);
      this.ctx.lineCap = 'round';
      this.ctx.lineJoin = 'round';
      if (initialData) {
        const img = new Image();
        img.onload = () => { this.ctx.drawImage(img, 0, 0, cv.width, cv.height); };
        img.src = initialData;
      }
      cv.addEventListener('pointerdown', (e) => this.start(e));
      cv.addEventListener('pointermove', (e) => this.move(e));
      window.addEventListener('pointerup', () => this.end());
      cv.addEventListener('pointerleave', () => this.end());
    },
    pos(e) {
      const r = this.$refs.canvas.getBoundingClientRect();
      return {
        x: (e.clientX - r.left) / r.width * this.$refs.canvas.width,
        y: (e.clientY - r.top) / r.height * this.$refs.canvas.height,
      };
    },
    start(e) {
      e.preventDefault();
      this.pushUndo();
      this.drawing = true;
      this.last = this.pos(e);
      // 点(タップ)も描けるように一点打つ
      this.stroke(this.last, this.last);
    },
    move(e) {
      if (!this.drawing) return;
      e.preventDefault();
      const p = this.pos(e);
      this.stroke(this.last, p);
      this.last = p;
    },
    end() { this.drawing = false; },
    stroke(a, b) {
      const c = this.ctx;
      c.strokeStyle = this.erasing ? '#ffffff' : this.color;
      c.lineWidth = this.erasing ? this.size * 4 : this.size;
      c.beginPath();
      c.moveTo(a.x, a.y);
      c.lineTo(b.x, b.y);
      c.stroke();
    },
    setColor(c) { this.color = c; this.erasing = false; },
    setSize(s) { this.size = s; this.erasing = false; },
    toggleEraser() { this.erasing = !this.erasing; },
    pushUndo() {
      if (this.stack.length >= 15) this.stack.shift();
      this.stack.push(this.$refs.canvas.toDataURL());
    },
    undo() {
      if (!this.stack.length) return;
      const data = this.stack.pop();
      const img = new Image();
      img.onload = () => {
        this.ctx.clearRect(0, 0, 1280, 960);
        this.ctx.drawImage(img, 0, 0);
      };
      img.src = data;
    },
    clearAll() {
      this.pushUndo();
      this.ctx.fillStyle = '#ffffff';
      this.ctx.fillRect(0, 0, 1280, 960);
    },
    prepare() {
      this.$refs.dataInput.value = this.$refs.canvas.toDataURL('image/png');
    },
  };
}
</script>
@endsection
