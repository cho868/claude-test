# CLAUDE.md — 身内ポータル 開発メモリ

> このファイルは新しい会話でも自動的に読み込まれます。
> 会話を移行したら、まずこれと `README.md`（機能一覧）を読んでから作業を始めてください。
> **内容が古くなったら必ず更新すること。**

---

## 1. このプロジェクトは何か

**身内ポータル（Uchiwa Portal）** — 友人・家族だけが使う招待制のポータルサイト。
ログインでポイントが貯まって称号が上がり、便利ツールを詰め込んだ「秘密基地」。

- **リポジトリ**: `cho868/claude-test`
- **本番URL**: `https://chopi.crocodile-anoles.ts.net`（Tailscale Funnel 経由）
- **機能の一覧は `README.md` にある**（ここでは繰り返さない）
- **UIは全部日本語**。コメントも日本語で書く。

---

## 2. 技術スタック

| 項目 | 内容 |
|---|---|
| フレームワーク | Laravel 13（PHP 8.3+／ローカル 8.4・ラズパイ 8.5） |
| DB | **SQLite**（単一ファイル。`database/database.sqlite`） |
| CSS | **Tailwind CSS Play CDN**（`?plugins=typography`） |
| JS | **Alpine.js 3 CDN**／必要なライブラリは都度 CDN |
| テスト | PHPUnit — `php artisan test`（**現在 33 tests / 全passing**） |
| Lint | `./vendor/bin/pint` |

### ⚠️ ビルドステップは無い
npm も Vite も**使っていない**。CSS/JS を足すときは CDN か Blade 内の `<script>` で完結させる。
`npm install` や `vite build` を前提にした提案はしないこと。

---

## 3. 絶対に守る設計原則

### ① サーバー（ラズパイ）に仕事をさせない
本番は**自宅のRaspberry Pi 4**。非力で、ストレージは**microSD**。

- 変換系・計算系のツールは**全部クライアント（ブラウザ）側で処理**する。
  画像変換・PDF・ハッシュ・文字変換などはサーバーにデータを送らない（プライバシー面でも有利）。
- サーバー側で外部に出る処理（OGP取得・SSLチェック等）は**必ずレート制限**を付ける。

### ② SDカードへの書き込みを最小化する
SDの唯一の弱点は**書き込み寿命**。これが判断の軸で、ここはブレさせない。

- **保存せずに済むものは保存しない。既存データから毎回計算する。**
  例: モンスターの能力値・レベルは `points` / `total_logins` / `login_streak` から算出。
  レイドの進捗は `point_logs` から集計。バトルは**シードと勝敗だけ**保存（〜50バイト）して、
  ログは決定論的シミュレーションで再生する（`App\Services\MonsterService::simulate()`）。
- ポイント付与は「1日1回だけ」の形にして連投で行が増えないようにする。
- 対策として `log2ram` + 日次バックアップを併用。**付属の32GB SDで十分**という結論で運用している。

### ③ SSRF対策（重要）
**サーバーは自宅LANの中にいる**。ユーザー入力のURLにサーバーからアクセスする機能を足すときは、
**必ず `App\Services\SafeUrl` を通す**。プライベートIP・予約IPへのアクセスを弾く。新規に自作しないこと。

### ④ プライバシーは「自分のみ」が既定
個人的なデータ（家計簿・日記）は `visibility`/`is_private` の**既定を非公開**にして、共有はオプトイン。
とくに**日記は管理者でも他人の非公開分は見られない**（`Diary::canBeViewedBy()` に admin バイパスを入れていない）。
資料（`Document`）だけは性質が違うので admin が全件見られる。ここは意図的な差。

### ⑤ XSS対策
ユーザーが書いた Markdown は必ず以下で描画する（生HTMLを除去）。

```php
Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
```

---

## 4. コードの書き方（既存に合わせる）

### ディレクトリ／命名
- コントローラは `app/Http/Controllers/XxxController.php`、ビューは `resources/views/xxx/`。
- ルートは `routes/web.php` の `Route::middleware('auth')` グループ内にコメント付きで追加。
- **リソースルートより前に固定パスを置く**（例: `diaries/feed` は `diaries/{diary}` より先）。
- マイグレーションは `database/migrations/YYYY_MM_DD_1000NN_xxx.php` 形式。

### 共通 Blade コンポーネント（必ず使い回す）
| コンポーネント | 用途 |
|---|---|
| `<x-page-header title icon subtitle back>` + `<x-slot:actions>` | 各ページの見出し |
| `<x-btn href variant type>` | ボタン（`primary` / `secondary` / `success` / `danger`） |
| `<x-avatar :user :size>` | ユーザーアイコン |
| `<x-title-badge :title>` | 称号バッジ |

### 新機能を足したら必ずやる3点セット
1. `resources/views/layouts/app.blade.php` の `$menu` にナビ項目を追加
2. `resources/views/dashboard.blade.php` の `$groups` にカード追加
3. `tests/Feature/PortalTest.php` にテストを追加（**特に権限まわり**）

### Blade × Alpine の落とし穴（実際に踏んだ）
- `@error` は **Blade のディレクティブと衝突する**。Alpine で使うときは `x-on:error` と書く。
  （`@click` `@submit` など他は問題ない）
- `@submit="prepare"` は**発火しない**。Alpine は式を評価するので `@submit="prepare()"` と書く。
- キャッシュに **Eloquent モデルを入れない**（アンシリアライズで壊れる）。配列に変換してから入れる。

### テストの落とし穴
- `actingAs()` は**そのテスト内でずっと効き続ける**。ゲスト状態の検証は先にまとめて済ませる。
- レート制限に引っかかるテストは `$this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)`。
- `assertDontSee()` はフォームの placeholder にも当たる。**検証用の文字列は特徴的なものにする。**

### 設定まわりで既に入っている重要な設定
```php
// bootstrap/app.php — Funnel(リバースプロキシ)越しでHTTPSを正しく判定するのに必須
$middleware->trustProxies(at: '*');

// config/app.php — UTCのままだと誕生日判定が9時間ズレる
'timezone' => env('APP_TIMEZONE', 'Asia/Tokyo'),
```

---

## 5. インフラ / 運用

### 現在の構成（2026-09時点）
```
スマホ/PC ──HTTPS──> Tailscale Funnel ──> 自宅ラズパイ4 (nginx + PHP-FPM + SQLite)
```
- ISPが **MAP-E**（IPv4 over IPv6）のため **80/443 のポート開放が物理的に不可能**。
  だから Tailscale Funnel でトンネルしている。**ポート開放やDDNSを前提にした提案はしない。**
- **完全無料・更新期限なし**が絶対条件。有料・無料期限つきのサービスは提案しない。
- 過去に XServer無料VPS で**2回全損**、Oracle Cloud は**サインアップが通らなかった**。
  この2つには戻らない。

### デプロイ
```bash
# ラズパイ上で
cd /var/www/portal && sudo bash deploy/deploy-app.sh main
```

### 運用スクリプト（`deploy/`）
| ファイル | 役割 |
|---|---|
| `RASPBERRYPI.md` | **現行の正典**。構築からTermius接続・別プロジェクト追加まで |
| `TROUBLESHOOTING.md` | 実際に踏んだ障害と対処 |
| `SERVICES.md` | サービス/ツールの配置マップ（Discord Bot 含む） |
| `backup-to-discord.sh` | **オフサイト**DBバックアップ（VPS全損の教訓。同じ機械に置かない） |
| `notify.sh` | 通知。`--urgent-only` `--reauth-remind` `--vps-final` モードあり。LINE無料枠(月200通)を守る送信間隔制御つき |
| `access-monitor.sh` | アクセスログ異常検知。**2段階の深刻度**（スキャンが2xxを返した時だけ @everyone） |
| `sd-health.sh` | SDカードの週次書き込み量レポート |
| `harden-server.sh` | fail2ban 等（任意） |

### ボットのスキャンについて
公開すると `.env` や `phpinfo.php` を狙う自動スキャンが**必ず来る**。
**全部 403/404 なら実害ゼロ**。2xx が無いことだけ確認すればいい。毎回騒がない。

---

## 6. 直近の状態（2026-09-14）

### 最後にやったこと
**📔 日記機能を追加**（commit `c20c1fe`／`main` と `claude/busy-faraday-jvrw2n` の両方に push 済み）
- 月カレンダー、気分/天気の絵文字、連続記録日数、前後の日記リンク
- Markdown（書く/プレビュー切替）、既定は「自分のみ」、1日1回 +10pt
- ファイル: `app/Models/Diary.php` / `app/Http/Controllers/DiaryController.php` / `resources/views/diaries/*`
  / `database/migrations/2026_09_14_100001_create_diaries_table.php`

### ラズパイ側で未確認のもの（本人が実施する）
- [ ] 最新コードのデプロイ（`deploy-app.sh main`）— 日記のマイグレーションを含む
- [ ] `log2ram` のインストール
- [ ] SD週次レポートの cron 登録
- [ ] `REGISTRATION_INVITE_CODE` の設定
- [ ] （任意）Chromium — Webページ→PDF/画像ツール用。変換時に300〜500MB使う

### やりたいことリスト（未着手）
- スイスドロー形式のトーナメント
- 身内同士の対戦成績表（総当たりマトリクス）
- CSV⇔JSON 変換 / テキスト差分比較 / サブスク管理

### 削除済み（復活させないこと）
- **Steamのセール機能**（本人の指示で全削除）

---

## 7. 進め方の約束

- **軸をブレさせない。** 一度出した技術判断（例：SDカードの扱い）を、あとから理由なく逆のことを言わない。
  意見を変えるなら、何が変わったのかを明示する。
- **本人の状況を知らないまま手順を断定しない。** 「〜してください」と命令する前に、
  前提が本当に成り立っているかを確認する。すでに試している可能性を常に想定する。
- **できないことは正直に言う。** 迂回策を無限に提案するより、
  「これは無理、代わりにこれができる」とはっきり言うほうが役に立つ。
- コミットメッセージ・PR本文・コード内コメントに**モデル名を書かない**。
- 作業が終わったら `php artisan test` を通してから push する。

### コミット
```
Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_xxxxx
```
作業ブランチは `claude/busy-faraday-jvrw2n`。本人の指示で `main` にも直接 push している。
