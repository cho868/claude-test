<?php
/**
 * PWA用アイコンを生成する（public/icons/）。
 * 画像ファイルを外から持ち込まずに済むよう、GDで描いて書き出す。
 *
 *   php scripts/make-icons.php
 */
const BG = [15, 23, 42];      // slate-900
const FG = [52, 211, 153];    // emerald-400

/** 4倍で描いて縮小することで、GDでもエッジを滑らかにする */
function draw(int $size, bool $rounded, float $inset): \GdImage
{
    $s = $size * 4;
    $im = imagecreatetruecolor($s, $s);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefilledrectangle($im, 0, 0, $s, $s, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);

    $bg = imagecolorallocate($im, ...BG);

    if ($rounded) {
        $r = (int) ($s * 0.22);
        imagefilledrectangle($im, $r, 0, $s - $r, $s, $bg);
        imagefilledrectangle($im, 0, $r, $s, $s - $r, $bg);
        foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $bg);
        }
    } else {
        imagefilledrectangle($im, 0, 0, $s, $s, $bg);
    }

    // チェックマーク（日課を消化した感じ）
    $fg = imagecolorallocate($im, ...FG);
    $pad = $s * $inset;
    $w = $s - $pad * 2;
    $pts = [[0.06, 0.52], [0.38, 0.80], [0.94, 0.20]];
    imagesetthickness($im, (int) max(1, $w * 0.16));

    for ($i = 0; $i < count($pts) - 1; $i++) {
        imageline($im,
            (int) ($pad + $pts[$i][0] * $w), (int) ($pad + $pts[$i][1] * $w),
            (int) ($pad + $pts[$i + 1][0] * $w), (int) ($pad + $pts[$i + 1][1] * $w),
            $fg);
    }
    // 折れ点が欠けないよう丸で埋める
    foreach ($pts as $p) {
        $d = (int) ($w * 0.16);
        imagefilledellipse($im, (int) ($pad + $p[0] * $w), (int) ($pad + $p[1] * $w), $d, $d, $fg);
    }

    $out = imagescale($im, $size, $size, IMG_BICUBIC);
    imagesavealpha($out, true);
    imagedestroy($im);

    return $out;
}

$dir = __DIR__.'/../public/icons';
@mkdir($dir, 0755, true);

$files = [
    // [ファイル名, サイズ, 角丸, 余白(マスク対応は内側80%に収める)]
    ['icon-192.png', 192, true, 0.24],
    ['icon-512.png', 512, true, 0.24],
    ['icon-maskable-512.png', 512, false, 0.30],
    ['apple-touch-icon.png', 180, false, 0.24],
];

foreach ($files as [$name, $size, $rounded, $inset]) {
    $im = draw($size, $rounded, $inset);
    imagepng($im, "{$dir}/{$name}", 9);
    imagedestroy($im);
    echo "generated: {$name} ({$size}px)\n";
}
