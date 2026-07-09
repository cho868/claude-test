{{--
  🎂 誕生日パーティー演出。
  今日が誰かの誕生日なら全ページで発動（本人以外の画面でも祝う）。
  自動再生はセッション中1回、右下の🎂ボタンでいつでも再発射できる。
  すべて vanilla JS + CSS（CDN・ビルド不要、pointer-events:none で操作の邪魔をしない）。
--}}
@php
    // Eloquentモデルはキャッシュせず、プレーン配列のみ保存する(unserialize事故防止)
    $bday = \Illuminate\Support\Facades\Cache::remember(
        'birthday:today:' . now()->format('m-d'),
        600,
        fn () => \App\Models\User::where('birth_month', (int) now()->format('n'))
            ->where('birth_day', (int) now()->format('j'))
            ->get(['name', 'avatar_emoji'])
            ->map(fn ($u) => ['name' => $u->name, 'emoji' => $u->avatar_emoji])
            ->all(),
    );
@endphp

@if (! empty($bday))
@php
    $names = implode('さん、', array_column($bday, 'name')) . 'さん';
    $emojis = array_values(array_filter(array_column($bday, 'emoji')));
@endphp

<style>
  #bday-stage { position: fixed; inset: 0; pointer-events: none; z-index: 90; overflow: hidden; }

  /* 虹色バナー */
  .bday-banner {
    position: fixed; top: 64px; left: 50%; transform: translateX(-50%);
    z-index: 91; pointer-events: none; text-align: center;
    animation: bdayBannerIn .8s cubic-bezier(.2,1.6,.4,1) both;
  }
  .bday-banner h2 {
    font-size: clamp(1.4rem, 5vw, 2.6rem); font-weight: 900; white-space: nowrap;
    background: linear-gradient(90deg,#f43f5e,#f59e0b,#22c55e,#3b82f6,#a855f7,#f43f5e);
    background-size: 300% 100%; -webkit-background-clip: text; background-clip: text; color: transparent;
    animation: bdayRainbow 3s linear infinite;
    text-shadow: none; filter: drop-shadow(0 2px 8px rgba(0,0,0,.15));
  }
  .bday-banner p { font-weight: 700; color: #f59e0b; animation: bdayPulse 1.2s ease-in-out infinite; }
  @keyframes bdayBannerIn { from { transform: translateX(-50%) translateY(-80px) scale(.5); opacity: 0; } }
  @keyframes bdayRainbow { to { background-position: 300% 0; } }
  @keyframes bdayPulse { 50% { transform: scale(1.08); } }

  /* 紙吹雪 */
  .bday-confetti {
    position: absolute; top: -20px; will-change: transform;
    animation: bdayFall linear forwards;
  }
  @keyframes bdayFall {
    to { transform: translateY(110vh) rotate(var(--rot)) translateX(var(--sway)); opacity: .9; }
  }

  /* クラッカー粒子（コーナーから放射） */
  .bday-pop {
    position: absolute; will-change: transform; font-size: 14px;
    animation: bdayPop .9s cubic-bezier(.1,.8,.4,1) forwards;
  }
  @keyframes bdayPop {
    to { transform: translate(var(--dx), var(--dy)) rotate(var(--rot)); opacity: 0; }
  }

  /* 風船 */
  .bday-balloon {
    position: absolute; bottom: -80px; will-change: transform;
    animation: bdayFloat var(--dur) ease-in forwards;
  }
  .bday-balloon > span { display: inline-block; animation: bdaySway 2.2s ease-in-out infinite alternate; }
  @keyframes bdayFloat { to { transform: translateY(-120vh); } }
  @keyframes bdaySway { from { transform: translateX(-14px) rotate(-6deg); } to { transform: translateX(14px) rotate(6deg); } }

  /* ちびキャラ（画面下をちょこまか走る） */
  .bday-chibi {
    position: fixed; bottom: 0; left: -60px; z-index: 91; pointer-events: none;
    font-size: 34px; will-change: transform;
    animation: bdayRun var(--dur) linear infinite; animation-delay: var(--delay);
  }
  .bday-chibi > span { display: inline-block; animation: bdayHop .35s ease-in-out infinite alternate; }
  .bday-chibi.rev { animation-name: bdayRunRev; }
  .bday-chibi.rev > span { transform: scaleX(-1); }
  @keyframes bdayHop { from { transform: translateY(0) rotate(-8deg); } to { transform: translateY(-14px) rotate(8deg); } }
  @keyframes bdayRun { to { transform: translateX(calc(100vw + 140px)); } }
  @keyframes bdayRunRev { from { transform: translateX(calc(100vw + 140px)) scaleX(-1); } to { transform: translateX(0) scaleX(-1); } }

  /* 再発射ボタン */
  #bday-again {
    position: fixed; right: 16px; bottom: 16px; z-index: 92;
    font-size: 26px; line-height: 1; padding: 10px 12px; border-radius: 9999px;
    background: linear-gradient(135deg,#f43f5e,#f59e0b); color: white;
    box-shadow: 0 4px 14px rgba(244,63,94,.4); cursor: pointer;
    animation: bdayPulse 1.6s ease-in-out infinite;
  }
  @media (prefers-reduced-motion: reduce) {
    .bday-confetti, .bday-pop, .bday-balloon, .bday-chibi, .bday-banner p, #bday-again { animation: none !important; }
  }
</style>

<div id="bday-stage" aria-hidden="true"></div>
<div class="bday-banner" id="bday-banner" style="display:none">
    <h2>🎂 HAPPY BIRTHDAY 🎂</h2>
    <p>{{ $names }}、お誕生日おめでとう！！🎉</p>
</div>
<button id="bday-again" title="もう一回お祝いする！" aria-label="もう一回お祝いする">🎂</button>

<script>
(() => {
  const stage = document.getElementById('bday-stage');
  const banner = document.getElementById('bday-banner');
  const btn = document.getElementById('bday-again');
  const CHIBIS = [...@json($emojis), '🐱', '🐶', '🦊', '🐸', '🐹', '🦄', '🤖', '🐧'].filter(Boolean);
  const COLORS = ['#f43f5e','#f59e0b','#22c55e','#3b82f6','#a855f7','#ec4899','#14b8a6','#facc15'];
  const BALLOONS = ['🎈','🎈','🎈','🟡','❤️','💙','💜'];
  const rand = (a, b) => a + Math.random() * (b - a);
  const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];

  function confettiBurst(n) {
    for (let i = 0; i < n; i++) {
      const c = document.createElement('div');
      c.className = 'bday-confetti';
      const size = rand(6, 13);
      c.style.cssText = `left:${rand(0,100)}vw;width:${size}px;height:${size * rand(1,2.2)}px;` +
        `background:${pick(COLORS)};border-radius:${Math.random() < .3 ? '50%' : '2px'};` +
        `--rot:${rand(360,1440)}deg;--sway:${rand(-120,120)}px;` +
        `animation-duration:${rand(2.5,5.5)}s;animation-delay:${rand(0,1.2)}s;`;
      stage.appendChild(c);
      setTimeout(() => c.remove(), 8000);
    }
  }

  function cracker(cornerLeft) {
    const origin = document.createElement('div');
    origin.style.cssText = `position:absolute;bottom:8vh;${cornerLeft ? 'left:2vw' : 'right:2vw'};`;
    stage.appendChild(origin);
    const bang = document.createElement('div');
    bang.textContent = '🎉';
    bang.style.cssText = 'position:absolute;font-size:40px;transform:translate(-50%,-50%);';
    origin.appendChild(bang);
    for (let i = 0; i < 26; i++) {
      const p = document.createElement('div');
      p.className = 'bday-pop';
      p.textContent = pick(['🎊','✨','⭐','🎉','💥']);
      const ang = cornerLeft ? rand(-80, -10) : rand(-170, -100);
      const dist = rand(120, Math.min(innerWidth * .6, 560));
      p.style.cssText = `--dx:${Math.cos(ang * Math.PI/180) * dist}px;--dy:${Math.sin(ang * Math.PI/180) * dist}px;` +
        `--rot:${rand(-360,360)}deg;animation-delay:${rand(0,.15)}s;`;
      origin.appendChild(p);
    }
    setTimeout(() => origin.remove(), 1600);
  }

  function balloons(n) {
    for (let i = 0; i < n; i++) {
      const b = document.createElement('div');
      b.className = 'bday-balloon';
      b.innerHTML = `<span>${pick(BALLOONS)}</span>`;
      b.style.cssText = `left:${rand(2,94)}vw;font-size:${rand(26,52)}px;--dur:${rand(6,12)}s;animation-delay:${rand(0,4)}s;`;
      stage.appendChild(b);
      setTimeout(() => b.remove(), 17000);
    }
  }

  let chibisSpawned = false;
  function chibis() {
    if (chibisSpawned) return;
    chibisSpawned = true;
    const n = Math.min(8, Math.max(4, CHIBIS.length));
    for (let i = 0; i < n; i++) {
      const c = document.createElement('div');
      c.className = 'bday-chibi' + (Math.random() < .5 ? ' rev' : '');
      c.innerHTML = `<span>${CHIBIS[i % CHIBIS.length]}</span>`;
      c.style.cssText = `--dur:${rand(7,16)}s;--delay:${rand(0,6)}s;font-size:${rand(26,40)}px;bottom:${rand(0,3)}vh;`;
      document.body.appendChild(c);
    }
  }

  function party(big) {
    banner.style.display = '';
    chibis();
    confettiBurst(big ? 160 : 80);
    cracker(true);
    setTimeout(() => cracker(false), 350);
    balloons(big ? 16 : 8);
    if (big) {
      setTimeout(() => confettiBurst(90), 1800);
      setTimeout(() => { cracker(true); cracker(false); }, 2600);
      setTimeout(() => confettiBurst(70), 4200);
    }
  }

  const key = 'bday-played-{{ now()->format('Y-m-d') }}';
  if (!sessionStorage.getItem(key)) {
    sessionStorage.setItem(key, '1');
    setTimeout(() => party(true), 400);
  } else {
    banner.style.display = '';
    chibis();
  }
  btn.addEventListener('click', () => party(true));
})();
</script>
@endif
