<?php

namespace App\Http\Controllers;

/**
 * 画像変換ツール。
 * 変換処理は 100% ブラウザ内(Canvas/WASM)で完結し、サーバーには一切送信しない。
 * → プライバシー安全・サーバー(ラズパイ)に負荷ゼロ・SDへの書き込みもゼロ。
 */
class ImageToolController extends Controller
{
    public function index()
    {
        return view('tools.image');
    }
}
