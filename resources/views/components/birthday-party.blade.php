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

  // 萌えちびキャラ(オリジナル・令和仕様)。前髪しっかり/大きな瞳/アホ毛/セーラー服
  const GIRLS = [
    { hair:'#ffb7d5', hairD:'#f48fb8', eye:'#e05a8a', uni:'#f43f5e', style:'twin', ribbon:'#ff5c8a' }, // 桜ピンクツインテ
    { hair:'#ffe08a', hairD:'#f4c452', eye:'#3f7ad6', uni:'#3b82f6', style:'pony', ribbon:'#ef4444' }, // 金髪ポニテ
    { hair:'#cdb4f6', hairD:'#b092ec', eye:'#8b5cf6', uni:'#10b981', style:'bob',  ribbon:'#f59e0b' }, // ラベンダーボブ
    { hair:'#aee3ff', hairD:'#7fcdf6', eye:'#2e9edd', uni:'#f59e0b', style:'twin', ribbon:'#3b82f6' }, // 水色ツインテ
    { hair:'#eceff4', hairD:'#cbd5e1', eye:'#a855f7', uni:'#a855f7', style:'pony', ribbon:'#f472b6' }, // 銀髪ポニテ
    { hair:'#ffb3a0', hairD:'#f98d75', eye:'#0d9488', uni:'#14b8a6', style:'bob',  ribbon:'#f43f5e' }, // アプリコットボブ
  ];

  function girlSVG({ hair, hairD, eye, uni, style, ribbon }) {
    const skin = '#ffe9dc';
    // 後ろ髪(ボリューム層+サイドの房)
    const backHair = `<ellipse cx="32" cy="24" rx="20" ry="17" fill="${hairD}"/>
      <path d="M13 26 Q9 44 14 54 Q19 57 20 50 Q17 38 19 30 Z" fill="${hairD}"/>
      <path d="M51 26 Q55 44 50 54 Q45 57 44 50 Q47 38 45 30 Z" fill="${hairD}"/>`;
    const tails =
      style === 'twin' ? `
        <g class="cb-part cb-tail"><path d="M12 26 Q-2 36 5 56 Q8 63 13 57 Q10 42 17 32 Z" fill="${hair}"/><path d="M9 40 Q7 48 9 53" stroke="${hairD}" stroke-width="1.2" fill="none"/></g>
        <g class="cb-part cb-tail"><path d="M52 26 Q66 36 59 56 Q56 63 51 57 Q54 42 47 32 Z" fill="${hair}"/><path d="M55 40 Q57 48 55 53" stroke="${hairD}" stroke-width="1.2" fill="none"/></g>
        <circle cx="14" cy="27" r="3" fill="${ribbon}"/><circle cx="50" cy="27" r="3" fill="${ribbon}"/>`
      : style === 'pony' ? `
        <g class="cb-part cb-tail"><path d="M44 12 Q62 18 58 44 Q56 54 50 49 Q54 32 42 20 Z" fill="${hair}"/><path d="M53 28 Q55 38 52 45" stroke="${hairD}" stroke-width="1.2" fill="none"/></g>
        <circle cx="46" cy="16" r="2.8" fill="${ribbon}"/>`
      : '';
    return `
    <svg width="54" height="78" viewBox="0 0 64 92" xmlns="http://www.w3.org/2000/svg">
      ${backHair}${tails}
      <g class="cb-part cb-arm"><path d="M22 49 Q17 52 17 60 Q17 63 20 62 Q23 58 24 52 Z" fill="#fff" stroke="${uni}" stroke-width="1"/><circle cx="18.5" cy="62" r="2.4" fill="${skin}"/></g>
      <g class="cb-part cb-arm r"><path d="M42 49 Q47 52 47 60 Q47 63 44 62 Q41 58 40 52 Z" fill="#fff" stroke="${uni}" stroke-width="1"/><circle cx="45.5" cy="62" r="2.4" fill="${skin}"/></g>
      <g class="cb-part cb-leg"><rect x="26" y="66" width="5.4" height="9" rx="2.6" fill="${skin}"/><rect x="26" y="73" width="5.4" height="6" rx="2.4" fill="#fff"/><ellipse cx="28.7" cy="81" rx="4" ry="2.6" fill="${uni}"/></g>
      <g class="cb-part cb-leg r"><rect x="32.6" y="66" width="5.4" height="9" rx="2.6" fill="${skin}"/><rect x="32.6" y="73" width="5.4" height="6" rx="2.4" fill="#fff"/><ellipse cx="35.3" cy="81" rx="4" ry="2.6" fill="${uni}"/></g>
      <rect x="24" y="47" width="16" height="15" rx="4" fill="#fff"/>
      <path d="M25 47 L32 53 L39 47 L40 50.5 L32 56.5 L24 50.5 Z" fill="${uni}"/>
      <path d="M31.5 56.5 Q26 53.5 25.5 57.5 Q25 61 30.5 59 Z" fill="${ribbon}"/>
      <path d="M32.5 56.5 Q38 53.5 38.5 57.5 Q39 61 33.5 59 Z" fill="${ribbon}"/>
      <circle cx="32" cy="57.5" r="2" fill="${ribbon}"/><circle cx="31.3" cy="56.8" r=".6" fill="#fff" opacity=".7"/>
      <g class="cb-part cb-skirt"><path d="M23 60 L41 60 L45 73 L39 70 L35.5 74 L32 70 L28.5 74 L25 70 L19 73 Z" fill="${uni}"/>
      <path d="M27 61 L26 69 M32 61 L32 69 M37 61 L38 69" stroke="#00000022" stroke-width="1" fill="none"/></g>
      <ellipse cx="32" cy="31" rx="16.5" ry="15.5" fill="${skin}"/>
      <path d="M15 33 Q13 12 32 11 Q51 12 49 33 Q47 27 44.5 33 Q43.5 24 39.5 31 Q37.5 22 32 30 Q26.5 22 24.5 31 Q20.5 24 19.5 33 Q17 27 15 33 Z" fill="${hair}"/>
      <path d="M30 12 Q26 4 36 2 Q30 7 34 12 Z" fill="${hair}"/>
      <path d="M19.5 31.5 Q23.5 29.5 27.5 31.8" stroke="#4a3232" stroke-width="1.7" fill="none" stroke-linecap="round"/>
      <ellipse cx="23.5" cy="36" rx="3.6" ry="4.8" fill="${eye}"/>
      <ellipse cx="23.5" cy="36.8" rx="1.9" ry="2.9" fill="#3c2a3e"/>
      <circle cx="22.2" cy="34" r="1.4" fill="#fff"/><circle cx="25" cy="38.3" r=".8" fill="#fff" opacity=".95"/>
      <path d="M36.5 31.8 Q40.5 29.5 44.5 31.5" stroke="#4a3232" stroke-width="1.7" fill="none" stroke-linecap="round"/>
      <ellipse cx="40.5" cy="36" rx="3.6" ry="4.8" fill="${eye}"/>
      <ellipse cx="40.5" cy="36.8" rx="1.9" ry="2.9" fill="#3c2a3e"/>
      <circle cx="39.2" cy="34" r="1.4" fill="#fff"/><circle cx="42" cy="38.3" r=".8" fill="#fff" opacity=".95"/>
      <ellipse cx="18.5" cy="40.5" rx="2.8" ry="1.6" fill="#ffb3c1" opacity=".8"/>
      <ellipse cx="45.5" cy="40.5" rx="2.8" ry="1.6" fill="#ffb3c1" opacity=".8"/>
      <path d="M30 42.5 Q31 44 32 42.8 Q33 44 34 42.5" stroke="#e11d48" stroke-width="1.3" fill="none" stroke-linecap="round"/>
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
