function monsterSVG(species, stage, seed = 0, size = 120) {
  const P = {
    slime:  { c1:'#7dd3fc', c2:'#0ea5e9', body:'blob' },
    dragon: { c1:'#86efac', c2:'#16a34a', body:'quad' },
    beast:  { c1:'#fdba74', c2:'#ea580c', body:'quad' },
    bird:   { c1:'#a5b4fc', c2:'#4f46e5', body:'egg'  },
    golem:  { c1:'#d6d3d1', c2:'#78716c', body:'rock' },
    ghost:  { c1:'#e9d5ff', c2:'#9333ea', body:'ghost'},
  }[species] || { c1:'#7dd3fc', c2:'#0ea5e9', body:'blob' };
  const s = Math.max(1, Math.min(4, stage));
  const R = (n => () => (n = (n * 1103515245 + 12345) & 0x7fffffff) / 0x7fffffff)(seed + 1);
  const eyeY = 46, gid = `g${species}${s}${seed}`;

  // 体（種族ごとの輪郭）
  const bodies = {
    blob:  `<path d="M18 78 Q10 44 50 34 Q90 44 82 78 Q50 90 18 78 Z" fill="url(#${gid})"/>`,
    quad:  `<path d="M20 76 Q16 42 50 36 Q84 42 80 76 L72 82 L64 74 L50 80 L36 74 L28 82 Z" fill="url(#${gid})"/>`,
    egg:   `<ellipse cx="50" cy="58" rx="30" ry="26" fill="url(#${gid})"/>`,
    rock:  `<path d="M22 78 L18 48 L34 34 L66 34 L82 48 L78 78 Z" fill="url(#${gid})"/>`,
    ghost: `<path d="M20 74 Q18 34 50 32 Q82 34 80 74 Q72 66 66 74 Q58 66 50 74 Q42 66 34 74 Q28 66 20 74 Z" fill="url(#${gid})" opacity=".92"/>`,
  };

  // 進化で増えるパーツ
  const horns = s >= 2 ? `<path d="M34 36 L30 22 L42 32 Z" fill="${P.c2}"/><path d="M66 36 L70 22 L58 32 Z" fill="${P.c2}"/>` : '';
  const wings = s >= 3 ? `<path d="M18 54 Q-2 40 4 66 Q10 72 20 64 Z" fill="${P.c2}" opacity=".75"/>
                          <path d="M82 54 Q102 40 96 66 Q90 72 80 64 Z" fill="${P.c2}" opacity=".75"/>` : '';
  const crown = s >= 4 ? `<path d="M36 24 L40 14 L46 22 L50 10 L54 22 L60 14 L64 24 Z" fill="#fbbf24" stroke="#f59e0b"/>
                          <circle cx="50" cy="8" r="2.5" fill="#fde68a"/>` : '';
  const aura = s >= 4 ? `<ellipse cx="50" cy="58" rx="44" ry="40" fill="none" stroke="#fbbf24" stroke-width="1.5" opacity=".5" stroke-dasharray="4 4"/>` : '';
  const tail = species === 'dragon' || species === 'beast'
    ? `<path d="M80 72 Q94 70 92 58 Q90 52 86 56 Q88 66 78 66 Z" fill="${P.c2}"/>` : '';
  const legs = ['quad','rock'].includes(P.body)
    ? `<rect x="30" y="76" width="9" height="10" rx="3" fill="${P.c2}"/><rect x="61" y="76" width="9" height="10" rx="3" fill="${P.c2}"/>` : '';

  // 目（進化で鋭くなる）
  const eyes = s >= 3
    ? `<path d="M34 ${eyeY-4} L46 ${eyeY} L34 ${eyeY+3} Z" fill="#1e293b"/><path d="M66 ${eyeY-4} L54 ${eyeY} L66 ${eyeY+3} Z" fill="#1e293b"/>`
    : `<ellipse cx="39" cy="${eyeY}" rx="5" ry="6" fill="#fff"/><circle cx="40" cy="${eyeY+1}" r="3" fill="#1e293b"/>
       <ellipse cx="61" cy="${eyeY}" rx="5" ry="6" fill="#fff"/><circle cx="60" cy="${eyeY+1}" r="3" fill="#1e293b"/>`;
  const mouth = s >= 3
    ? `<path d="M42 60 L50 66 L58 60 Q50 62 42 60 Z" fill="#7f1d1d"/>`
    : `<path d="M44 60 Q50 65 56 60" stroke="#1e293b" stroke-width="2" fill="none" stroke-linecap="round"/>`;

  return `<svg viewBox="0 0 100 100" width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg">
    <defs><radialGradient id="${gid}" cx="40%" cy="35%">
      <stop offset="0%" stop-color="${P.c1}"/><stop offset="100%" stop-color="${P.c2}"/>
    </radialGradient></defs>
    ${aura}${wings}${tail}${legs}${bodies[P.body]}${horns}${crown}${eyes}${mouth}
    <ellipse cx="50" cy="90" rx="26" ry="4" fill="#000" opacity=".12"/>
  </svg>`;
}
