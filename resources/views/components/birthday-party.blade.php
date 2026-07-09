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
    position: fixed; bottom: 0; left: -70px; z-index: 91; pointer-events: none;
    font-size: 34px; will-change: transform;
    animation: bdayRun var(--dur) linear infinite; animation-delay: var(--delay);
  }
  .bday-chibi > span { display: inline-block; animation: bdayHop .35s ease-in-out infinite alternate; }
  .bday-chibi.rev { animation-name: bdayRunRev; }
  .bday-chibi.rev > span { transform: scaleX(-1); }
  @keyframes bdayHop { from { transform: translateY(0) rotate(-8deg); } to { transform: translateY(-14px) rotate(8deg); } }
  @keyframes bdayRun { to { transform: translateX(calc(100vw + 160px)); } }
  @keyframes bdayRunRev { from { transform: translateX(calc(100vw + 160px)) scaleX(-1); } to { transform: translateX(0) scaleX(-1); } }

  /* 萌えちびキャラ(オリジナルSVG): 走りに合わせて髪・手足・スカートが揺れる */
  .bday-chibi svg { overflow: visible; display: block; }
  .bday-chibi .cb-part { transform-box: fill-box; }
  .bday-chibi .cb-leg { transform-origin: 50% 8%; animation: cbKick .28s ease-in-out infinite alternate; }
  .bday-chibi .cb-leg.r { animation-delay: .14s; }
  .bday-chibi .cb-arm { transform-origin: 50% 10%; animation: cbSwing .28s ease-in-out infinite alternate; }
  .bday-chibi .cb-arm.r { animation-delay: .14s; }
  .bday-chibi .cb-tail { transform-origin: 50% 12%; animation: cbTail .5s ease-in-out infinite alternate; }
  .bday-chibi .cb-skirt { transform-origin: 50% 5%; animation: cbSkirt .3s ease-in-out infinite alternate; }
  @keyframes cbKick { from { transform: rotate(-22deg); } to { transform: rotate(22deg); } }
  @keyframes cbSwing { from { transform: rotate(18deg); } to { transform: rotate(-18deg); } }
  @keyframes cbTail { from { transform: rotate(-10deg); } to { transform: rotate(12deg); } }
  @keyframes cbSkirt { from { transform: skewX(-4deg); } to { transform: skewX(4deg); } }

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
  const AVATAR_EMOJIS = @json($emojis).filter(Boolean);

  // 萌えちびキャラ(オリジナル)。野球ユニフォーム女子: 髪色/髪型/チームカラーのバリエーション
  const GIRLS = [
    { hair: '#f472b6', style: 'twin', uni: '#ef4444' },  // ピンクツインテ × 赤
    { hair: '#fbbf24', style: 'pony', uni: '#3b82f6' },  // 金髪ポニテ × 青
    { hair: '#a78bfa', style: 'bob',  uni: '#10b981' },  // 紫ボブ × 緑
    { hair: '#7dd3fc', style: 'twin', uni: '#f59e0b' },  // 水色ツインテ × 橙
    { hair: '#e2e8f0', style: 'pony', uni: '#a855f7' },  // 銀髪ポニテ × 紫
    { hair: '#fb7185', style: 'bob',  uni: '#14b8a6' },  // 赤髪ボブ × 青緑
    { hair: '#92400e', style: 'twin', uni: '#ec4899' },  // 茶髪ツインテ × ピンク
    { hair: '#334155', style: 'pony', uni: '#facc15' },  // 黒髪ポニテ × 黄
  ];

  function girlSVG({ hair, style, uni }) {
    const skin = '#ffe4d0';
    // 後ろ髪(髪型別) + キャップはツインテ以外
    const tails =
      style === 'twin' ? `
        <path class="cb-part cb-tail" d="M13 22 Q1 30 6 48 Q8 55 13 50 Q10 35 17 26 Z" fill="${hair}"/>
        <path class="cb-part cb-tail" d="M51 22 Q63 30 58 48 Q56 55 51 50 Q54 35 47 26 Z" fill="${hair}"/>`
      : style === 'pony' ? `
        <path class="cb-part cb-tail" d="M47 16 Q60 24 55 45 Q53 52 48 47 Q52 32 44 22 Z" fill="${hair}"/>`
      : `<path d="M13 24 Q11 38 17 42 L20 30 Z" fill="${hair}"/><path d="M51 24 Q53 38 47 42 L44 30 Z" fill="${hair}"/>`;
    const cap = style !== 'twin' ? `
        <path d="M16 19 Q32 1 48 19 L48 22 Q32 12 16 22 Z" fill="${uni}"/>
        <ellipse cx="49" cy="20" rx="7" ry="2.6" fill="${uni}" opacity=".9"/>
        <circle cx="32" cy="8" r="2" fill="#fff" opacity=".85"/>`
      : `<path d="M22 8 L26 3 L28 9 Z" fill="${uni}"/>`; // ツインテ勢はリボン
    return `
    <svg width="52" height="72" viewBox="0 0 64 88" xmlns="http://www.w3.org/2000/svg">
      ${tails}
      <g class="cb-part cb-arm" ><rect x="19" y="45" width="5.5" height="14" rx="2.7" fill="#fff" stroke="${uni}" stroke-width="1"/><circle cx="21.7" cy="60" r="2.6" fill="${skin}"/></g>
      <g class="cb-part cb-arm r"><rect x="39.5" y="45" width="5.5" height="14" rx="2.7" fill="#fff" stroke="${uni}" stroke-width="1"/><circle cx="42.2" cy="60" r="2.6" fill="${skin}"/></g>
      <g class="cb-part cb-leg" ><rect x="26" y="62" width="5.5" height="15" rx="2.7" fill="${skin}"/><ellipse cx="28.7" cy="78" rx="4" ry="2.6" fill="${uni}"/></g>
      <g class="cb-part cb-leg r"><rect x="32.5" y="62" width="5.5" height="15" rx="2.7" fill="${skin}"/><ellipse cx="35.2" cy="78" rx="4" ry="2.6" fill="${uni}"/></g>
      <rect x="24" y="42" width="16" height="16" rx="5" fill="#fff" stroke="${uni}" stroke-width="1.2"/>
      <path d="M32 42 L28 48 L32 46 L36 48 Z" fill="${uni}"/>
      <g class="cb-part cb-skirt"><path d="M22 55 L42 55 L46 68 L40 64 L36 69 L32 64 L28 69 L24 64 L18 68 Z" fill="${uni}"/></g>
      <circle cx="32" cy="26" r="17" fill="${skin}"/>
      <path d="M15 27 Q13 6 32 6 Q51 6 49 27 Q45 16 39 19 Q36 12 30 18 Q24 13 20 21 Q16 19 15 27 Z" fill="${hair}"/>
      ${cap}
      <ellipse cx="25" cy="30" rx="2.7" ry="4.2" fill="#334155"/><circle cx="24" cy="28.4" r="1.1" fill="#fff"/>
      <ellipse cx="39" cy="30" rx="2.7" ry="4.2" fill="#334155"/><circle cx="38" cy="28.4" r="1.1" fill="#fff"/>
      <ellipse cx="20.5" cy="35" rx="2.6" ry="1.5" fill="#fda4af" opacity=".75"/>
      <ellipse cx="43.5" cy="35" rx="2.6" ry="1.5" fill="#fda4af" opacity=".75"/>
      <path d="M29.5 37 Q32 39.5 34.5 37" stroke="#e11d48" stroke-width="1.4" fill="none" stroke-linecap="round"/>
    </svg>`;
  }
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
    // 萌えちびキャラ6人 + 誕生日の人のアバター絵文字も一緒に走る
    const lineup = [...GIRLS.slice(0, 6).map((g) => girlSVG(g)),
                    ...AVATAR_EMOJIS.map((e) => `<span style="font-size:34px">${e}</span>`)];
    lineup.forEach((html, i) => {
      const c = document.createElement('div');
      c.className = 'bday-chibi' + (Math.random() < .5 ? ' rev' : '');
      c.innerHTML = `<span>${html}</span>`;
      const scale = rand(.8, 1.15);
      c.style.cssText = `--dur:${rand(8,18)}s;--delay:${rand(0,6)}s;bottom:${rand(0,2)}vh;` +
        `transform-origin:bottom;scale:${scale.toFixed(2)};`;
      document.body.appendChild(c);
    });
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
