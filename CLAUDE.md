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
| テスト | PHPUnit — `php artisan test`（**現在 38 tests / 全passing**） |
| Lint | `./vendor/bin/pint` |

### ⚠️ ビルドステップは無い
npm も Vite も**使っていない**。CSS/JS を足すときは CDN か Blade 内の `<script>` で完結させる。
`npm install` や `vite build` を前提にした提案はしないこと。

### PWA（ビルド不要で成立させている）
`public/manifest.json` と `public/sw.js` を直接置いてあるだけ。両レイアウトの `<head>` から読ませている。
- Service Worker の役割は **Web Push の受け口** と **オフライン案内（`public/offline.html`）** の2つだけ。
  ログイン後のHTMLはキャッシュしない（古い内容が出る/端末に残るのを避ける）。
- アイコンは `php scripts/make-icons.php` が GD で生成（画像ファイルを外から持ち込まない）。
- **Push は Laravel 側からしか送れない**。Service Worker は自力で時刻トリガーを起こせず、
  `TimestampTrigger` は Chrome の Origin Trial 止まりで **iOS Safari は非対応**。
  つまり「定時通知がほしい＝サーバーが要る」。静的PWA単体でやろうとしないこと。

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

### ⑤ 通知はDBに書かない
リマインドは cron の毎時実行（`php artisan routines:remind`）。
**「実行時刻そのもの」を重複送信よけに使い、送信履歴を保存しない**＝SD書き込みゼロ。
`schedule:run`（毎分PHP起動）はラズパイには重いので**入れていない**。cronに直接1行だけ。
LINEは無料枠が月200通しかなく、緊急通知の枠を食うので**日課通知には使わない**。

### ⑥ XSS対策
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

## 6. 直近の状態（2026-09-15）

### 最後にやったこと
**📋 ソシャゲ日課の作り直し + PWA化 + Web Push 通知**
- **リセット時刻をゲーム単位で設定**（日課の時刻 / 週課の曜日 / 月課の日）。
  判定は全部 `GameRoutine::periodStart()` 起点。`RoutineTask::currentPeriodKey()` はゲーム側に委譲するだけ。
  期間キーは 日課=`Y-m-d`（旧形式と互換）/ 週課=`W:Y-m-d` / 月課=`M:Y-m`。
- **チェックが fetch + 楽観的UI に**（ページ遷移なし）。toggle は `done` を受け取る**冪等**な作りで、連打・再送で裏返らない。
- 「今日やること」でゲーム横断の残件とリセット残り時間。ゲーム別 / 設定 の3タブ構成。
- 原神/FGO/ウマ娘/プロセカのテンプレをワンタップ投入（`SocialGameController::TEMPLATES`）。
- **Web Push**: `php artisan push:vapid` で鍵生成 → `.env` → cron `0 * * * * php artisan routines:remind`。
  未完了がある時だけ鳴る。⚠️ **iPhoneは「ホーム画面に追加」した状態でないと購読できない**（iOS 16.4+）。
- ファイル: `app/Models/GameRoutine.php` / `RoutineTask.php` / `PushSubscription.php`、
  `app/Http/Controllers/SocialGameController.php` / `PushController.php`、
  `app/Services/PushService.php`、`app/Console/Commands/{RemindRoutines,GenerateVapidKeys}.php`、
  `resources/views/social/index.blade.php`、`public/{manifest.json,sw.js,offline.html,icons/}`、
  `database/migrations/2026_09_14_100002_add_reset_rules_and_push_to_routines.php`
- 依存追加: `minishlink/web-push`（ext-gmp 不要。VAPID署名と本文暗号化は openssl で通る）

### 「PWAを別に作るか」の判断（済み・蒸し返さない）
ポータルとは別に静的PWA（cho-feedly方式）を建てる案は**採らなかった**。理由:
1. **定時通知はサーバーが要る**（上記のとおり静的PWA単体では原理的に無理）。別に建てても結局
   Laravel側の実装が必要になり二重管理になる。
2. データ同期・Discordへのオフサイトバックアップ・ポイント連携・認証が既にポータル側にある。
   localStorage だと機種変で消える。
3. 「使いづらい」の正体はアーキテクチャではなく**UI**（1チェック=1フルページリロード）だった。直せば済む話。

→ **ポータル自体をPWA化する**という形で決着。タスク管理も同じ方針で足す（別機能として `/tasks` を後日）。

### ラズパイ側で未確認のもの（本人が実施する）
- [ ] 最新コードのデプロイ（`deploy-app.sh main`）— ソシャゲ/Pushのマイグレーションを含む
- [ ] `php artisan push:vapid` → `.env` に鍵を設定 → `config:cache`
- [ ] cron に `0 * * * * cd /var/www/portal && php artisan routines:remind`（RASPBERRYPI.md 9.5）
- [ ] スマホで「ホーム画面に追加」→ 設定 → 通知を購読 → テスト送信
- [ ] `log2ram` のインストール
- [ ] SD週次レポートの cron 登録
- [ ] `REGISTRATION_INVITE_CODE` の設定
- [ ] （任意）Chromium — Webページ→PDF/画像ツール用。変換時に300〜500MB使う

### やりたいことリスト（未着手）
- **タスク管理（`/tasks`）** — ソシャゲ以外のToDo。別機能として分ける方針で確定済み
- ソシャゲ日課のオフライン対応（SWでチェックをIndexedDBに貯めて復帰時に同期）
- スイスドロー形式のトーナメント
- 身内同士の対戦成績表（総当たりマトリクス）
- CSV⇔JSON 変換 / テキスト差分比較 / サブスク管理

### 削除済み（復活させないこと）
- **Steamのセール機能**（本人の指示で全削除）

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
作業ブランチは会話ごとに指定される（直近は `claude/bold-cori-b1hmys`）。本人の指示があれば `main` にも直接 push する。
