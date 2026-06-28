# 🏠 身内ポータル (Uchiwa Portal)

身内（友人・家族）専用のポータルサイト。**Laravel 13 + SQLite/MySQL** 製。
ログインするほどポイントが貯まり、称号がアップ。便利ツールを詰め込んだ秘密基地です。

## ✨ 機能

### ログイン & ポイント & 称号
- メール + パスワードのログイン / 新規登録
- **ログインボーナス**: 1日1回ログインで +10pt
- **連続ログインストリーク**: 連続日数に応じてボーナス加算（最大 +14pt/日）
- 各ツールの利用でもポイント獲得（作成 +10〜15pt など）
- ポイント到達で **称号** が自動昇格（新参者 → 見習い → 常連 → 古参 → 主 → 伝説 → 神）
- ダッシュボードにポイントランキング・称号一覧・獲得履歴を表示

### 便利ツール
| ツール | 説明 |
|--------|------|
| 🏆 トーナメント作成 | 参加者を入力するとシングルイリミネーションの対戦表を自動生成。勝者をクリックしてラウンドを進行。 |
| 📊 ソート / ランキング | ドラッグ&ドロップで項目を S〜D ティアに振り分け。身内に共有可能。 |
| 📝 GMQ2 メモ | 攻略メモなどを記録。公開すると身内で共有。カテゴリ切替対応。 |
| 😴 睡眠時間チェック | 就寝・起床を記録（**1日複数回の分割睡眠も合算**）。直近7日をグラフで可視化、平均も表示。 |
| 🎮 ゲーム時間 | プレイ時間を手動記録 or **Steam 連携**で自動取り込み。ゲーム別集計と今月の身内ランキング。 |
| 🕹️ Steam | Steam Web API 連携。**いま誰が何をプレイ中**・**みんなの共通所持ゲーム**・**実績コンプ率の比較**・**自分の全期間プレイ時間TOP**。共通ゲームからワンクリックで実績比較。各自プロフィールに Steam ID（バニティ名/URLでも可）を登録。 |
| ⚔️ 戦績 | 対戦ゲームの勝敗を手動記録。ゲーム別の勝率バー・総合勝率・連勝数を集計。 |
| 📋 ソシャゲ管理 | ゲームごとに日課/週課/月課を登録してチェック。日付が変われば自動リセット。カスタマイズ可。 |
| 🔴 ポケモン ダメージ計算 | ポケモンチャンピオンズ仕様（Lv50固定）対応。実数値・技威力・タイプ相性（18タイプ表）・STAB/急所/やけど補正からダメージと確定数を計算（クライアント完結）。 |
| 🗳️ アンケート | 選択肢を作って投票。単一/複数選択、締切設定、結果のグラフ表示。 |
| 📅 スケジュール共有 | 予定を立てて出欠（参加/未定/不参加）を登録。コメント付き。 |
| 💪 フィットネス / チャレンジ | 体重・運動を記録して推移グラフ・運動量を可視化。期間を決めた**チャレンジ**で登録者と競う（減量率 or 運動時間の合計でランキング）。 |
| 📚 資料 / ナレッジ | Markdown で手順書・記事を書いて共有（Qiita風）。カテゴリ絞り込み・ライブプレビュー・コードハイライト。**公開範囲（身内/自分のみ/管理者のみ）** を設定可能で、サーバー/セキュリティ系の機密資料は管理者だけに表示。 |
| 🔗 リンク集 | 共同編集ツール（HackMD等）・スプレッドシート・各種ツールへの入口をカテゴリ別に集約。アイコン/説明付き・身内共有・インライン編集。 |
| 🛠️ 管理エリア（管理者のみ） | フロントの `/admin` から、**セットアップ/セキュリティ チェックリスト（ToDo）**・統計・サーバー状況・**ユーザー管理**・**🤖 Discord Bot設定**（ステータス/機能ON-OFF/文面を再起動なしで編集。キーはサーバー側で秘匿し localhost API を中継）を操作。 |
| ⚙️ プロフィール | Discord ID / Steam ID の連携設定。 |

## 🚀 セットアップ

```bash
# 1. 依存インストール & 初期化（まとめてやるなら）
bash scripts/setup.sh

# もしくは手動で:
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed   # 称号マスタ + 管理者アカウントを投入

# 2. 起動
php artisan serve
# → http://127.0.0.1:8000
```

最初に新規登録したユーザーは自動で **管理者** になります。

## 🗄️ データベース

デフォルトは **SQLite**（`database/database.sqlite`）。設定不要で動きます。

**MySQL に切り替える**場合は `.env` を編集:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portal
DB_USERNAME=root
DB_PASSWORD=secret
```

その後 `php artisan migrate --seed` を実行。

## 🌐 公開デプロイ（無料運用）

自分のPCだけでなく、インターネットに公開して身内で使うには **[deploy/DEPLOY.md](deploy/DEPLOY.md)** を参照。
XServer VPS の無料体験 + no-ip の無料ドメイン + Let's Encrypt の無料SSL で **HTTPS 公開** する手順と、
サーバー構築スクリプト（`deploy/setup-server.sh` / `deploy/deploy-app.sh` / `deploy/nginx-portal.conf`）を同梱しています。

```bash
# サーバー(Ubuntu 26.04 等)上で:
sudo bash deploy/setup-server.sh    # nginx + PHP(標準) + Composer + certbot + nginx設定 を自動構築
sudo bash deploy/deploy-app.sh main # 取得 → composer → migrate → 最適化
```

再構築・トラブルシュート・通知・セキュリティまで含めた完全手順は以下にまとめてあります（そのまま Qiita / Zenn 記事や、ポータル内「📚資料」に転用可）。

- **[deploy/DEPLOY.md](deploy/DEPLOY.md)** … 構築・再構築・更新・トラブルシュート
- **[deploy/NOTIFY.md](deploy/NOTIFY.md)** … 通知・死活監視（no-ip / Let's Encrypt / XServer の期限を毎日Discord通知）
- **[deploy/SECURITY.md](deploy/SECURITY.md)** … セキュリティ懸念・対策・監視。`deploy/harden-server.sh` で fail2ban / 自動更新 / セキュリティヘッダを導入
- **[deploy/TROUBLESHOOTING.md](deploy/TROUBLESHOOTING.md)** … サイトが落ちた時の自己診断・修正ナレッジ（症状→診断→原因→直し方をパターン別に記録）

セキュリティ補足:
- 新規登録は `REGISTRATION_INVITE_CODE` を設定すると招待コード必須（野良登録を防止）
- ログイン/登録はレート制限（1分6回）でブルートフォース対策
- 資料の Markdown は生HTML除去で XSS 対策、本番は `APP_DEBUG=false`

## 🔗 外部連携

`.env` にキーを設定すると有効になります（任意）。

```env
# Steam: https://steamcommunity.com/dev/apikey で取得
STEAM_API_KEY=xxxxxxxx

# Discord（ゲーム時間反映の拡張用）
DISCORD_BOT_TOKEN=
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
```

- **Steam**: プロフィールに Steam ID(64bit) を登録 → 「ゲーム時間」画面の「Steam から取り込む」で直近2週間のプレイ実績を反映。
- **Discord**: Bot トークン等を設定して、リッチプレゼンス(ゲーム時間)を取り込む拡張の足場を用意済み（`config/services.php` の `discord`）。

## 🧪 テスト

```bash
php artisan test
```

ポイント/ストリーク/称号付与/アンケート投票/認証ガードを検証する Feature テストを同梱。

## 🏗️ 構成

```
app/
  Http/Controllers/   各ツールのコントローラ + Auth
  Models/             User, Title, PointLog, Tournament, TierList,
                      Memo, SleepRecord, Survey(+Option/Vote),
                      ScheduleEvent(+Attendance), GameSession
  Services/           PointService(ポイント/称号エンジン), SteamService
database/migrations/  ポータル用テーブル定義
database/seeders/     TitleSeeder(称号マスタ)
resources/views/      Blade テンプレート（Tailwind CDN 利用、ビルド不要）
routes/web.php        ルーティング
```

## 📌 今後の拡張アイデア
- Discord Bot 常駐によるボイスチャンネル滞在時間の自動ポイント化
- トーナメントのダブルイリミネーション完全対応
- メール認証 / パスワードリセット
- ツール利用通知の Discord Webhook 連携
