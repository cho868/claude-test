@extends('layouts.app')
@section('title', 'ツール')

@section('content')
<x-page-header title="ツール" icon="🧰" subtitle="よく使う変換・確認ツールをまとめました" />

@php
    $groups = [
        '🖼️ 画像・ファイル' => [
            ['tools.image', '🖼️', '画像変換', 'JPEG/PNG/WebP/HEIC/TIFF/PDF・透明化・リサイズ'],
            ['tools.base64', '🧩', 'Base64→画像', 'Base64文字列を画像に復元'],
            ['tools.capture', '📸', 'Webページ→PDF/画像', 'URLを指定して丸ごと保存'],
        ],
        '🔤 テキスト' => [
            ['tools.text', '🔤', '文字変換', '装飾フォント・ケース・エンコード・JSON整形・ハッシュ'],
            ['tools.qr', '🔳', 'QRコード作成', 'URL・テキスト・Wi-Fiなどを QR に'],
        ],
        '🌐 ウェブ・ネットワーク' => [
            ['tools.ogp', '🔍', 'OGP確認', 'SNSシェア時の見え方をプレビュー'],
            ['tools.ssl', '🔒', 'SSLチェッカー', '証明書の有効期限・発行者を確認'],
            ['tools.ip', '📡', 'IPアドレス確認', '接続元IPと端末情報'],
        ],
        '🎲 その他' => [
            ['tools.lottery', '🎲', 'くじ引き・抽選', 'リストからランダムに選ぶ'],
        ],
    ];
@endphp

<div class="space-y-5">
    @foreach ($groups as $label => $items)
        <div>
            <p class="mb-2 text-sm font-bold text-slate-500">{{ $label }}</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as [$route, $icon, $name, $desc])
                    <a href="{{ route($route) }}"
                       class="group flex items-start gap-3 rounded-2xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="text-2xl">{{ $icon }}</div>
                        <div class="min-w-0">
                            <p class="font-semibold group-hover:text-slate-900">{{ $name }}</p>
                            <p class="text-xs text-slate-500">{{ $desc }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection
