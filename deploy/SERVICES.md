# 🧭 利用サービス・ツール全体マップ

身内ポータルの運用で使っている外部サービスを一覧化。**何のために・どこで設定して・何に注意するか**をここに集約する。
（新しいサービスを使い始めたらこの表に追記すること）

## 全体像

```
[利用者] ──HTTPS──> madgear.sytes.net ──DNS(no-ip)──> XServer VPS
                                                        ├─ nginx + SSL(Let's Encrypt)
                                                        ├─ Laravel ポータル ←── GitHub からデプロイ
                                                        ├─ Discord Bot (server.js・別リポジトリ)
                                                        └─ cron: notify.sh / backup-to-discord.sh
[Gmail] ──GAS(30分ごと監視)──> Discord / LINE に強アラート   ← VPSの外で動く見張り番
```

---

## 🌐 インフラ・ドメイン

| サービス | 用途 | 管理画面 | 設定場所 | ⚠️ 期限・注意 |
|---------|------|---------|---------|--------------|
| **XServer VPS** | サーバー本体（無料体験） | https://vps.xserver.ne.jp/ | パケットフィルターで22/80/443を許可 | **再認証: 2GB=4日 / 4GB=2日ごと**。期限メール到着日=更新期限日。忘れると**VPSごと消える**（2回経験済み） |
| **no-ip** | 無料ドメイン `madgear.sytes.net` | https://my.noip.com/ | Dynamic DNS → ホスト名のIPv4 | **30日ごとに確認クリック必須**（23日目にメール）。**IP更新では延長されない**。失効すると名前解決が止まる |
| **Let's Encrypt** | 無料SSL証明書 | （なし・certbotで管理） | サーバー上 `sudo certbot` | 更新は自動（certbot.timer）。**VPS再構築時は `certbot --nginx -d ドメイン` を忘れない**（ケースE-2） |
| **GitHub** | コード・手順書・スクリプト置き場 | https://github.com/cho868/claude-test | `deploy-app.sh main` で取得 | ここにあるものは消えない。**DBと.envだけはGitに無い**ので別途保護 |

## 🔔 通知・監視（4系統）

| サービス | 用途 | 管理画面 | 設定場所 | ⚠️ 注意 |
|---------|------|---------|---------|--------|
| **Discord Webhook** | 毎日の状態通知・緊急アラート・**DBバックアップ退避先** | Discordチャンネル設定→連携サービス→ウェブフック | `/etc/portal-notify.conf` の `DISCORD_WEBHOOK` / `BACKUP_WEBHOOK`、GASのプロパティ | URLは実質パスワード。漏らさない |
| **LINE Messaging API** | 通知のLINE配信（Bot友だち全員に届く） | https://developers.line.biz/console/ | `/etc/portal-notify.conf` の `LINE_TOKEN`、GASのプロパティ | 無料枠は月200通。トークン再発行したら両方更新 |
| **Google Apps Script** | **Gmail監視の見張り番**（XServer期限メール・no-ip確認メールを検知→30分ごと強アラート） | https://script.google.com/ | プロジェクト内 `gmail-xserver-alert.gs`（元は `deploy/gmail-xserver-alert.gs`） | **VPSの外で動くのでVPSが死んでも生きている**。止めるにはGmailで「VPS対応済み」ラベル。コード更新は貼り替えるだけ（トリガー再登録不要） |
| **healthchecks.io** | 死活監視（毎日のping が**来ない**と通知） | https://healthchecks.io/ | `/etc/portal-notify.conf` の `HEALTHCHECK_URL` | notify.sh の通常実行時にping。落ちた「事後」に気づく用 |
| **UptimeRobot** | 外形監視（外から `/health` を5分ごと確認） | https://uptimerobot.com/ | UptimeRobot側でURL登録 | **ドメイン監視にしておけばIP変更時も設定不要** |

## 🔌 連携API

| サービス | 用途 | 取得場所 | 設定場所 | ⚠️ 注意 |
|---------|------|---------|---------|--------|
| **Steam Web API** | いまプレイ中・共通所持・実績・プレイ時間 | https://steamcommunity.com/dev/apikey | `/var/www/portal/.env` の `STEAM_API_KEY` | キーはアカウントに常設（再取得はコピーし直すだけ）。サーバー全体で1つ |
| **Discord Bot（server.js）** | Discord内の機能＋ポータルから設定編集 | 別リポジトリ（VPS上で常駐） | Bot側 `.env` の `ADMIN_KEY` ⇔ ポータル側 `.env` の `BOT_ADMIN_KEY`（**同じ値にする**） | localhost:3000 で待機。VPS再構築時はBotも立て直し＋キー合わせ直し |
| **DiceBear** | プロフィールの自動生成アバター | （不要・公開API） | コード内から直接利用 | アカウント不要・無料 |

## 🗝️ 秘密情報の所在まとめ（消えたら困る順）

| 情報 | 置き場所 | 失った時 |
|------|---------|---------|
| **DBファイル（database.sqlite）** | VPS上 ＋ 毎日Discordへ暗号化退避 | Discordの最新バックアップから復元（DEPLOY.md） |
| **BACKUP_PASSPHRASE** | `/etc/portal-notify.conf` ＋ **パスワードマネージャ等VPSの外にも控える** | **バックアップが復号できなくなる**。絶対に外に控えを |
| STEAM_API_KEY | `/var/www/portal/.env` | Steamのページから再コピー |
| BOT_ADMIN_KEY / ADMIN_KEY | ポータル `.env` ＋ Bot `.env` | 新しい値を決めて両方に設定し直すだけ |
| Discord Webhook / LINE_TOKEN | `/etc/portal-notify.conf` ＋ GASプロパティ | 各管理画面から再発行・再コピー |
| APP_KEY | `/var/www/portal/.env` | `php artisan key:generate` で再生成（全員ログインし直しになるだけ） |

## 📚 関連ドキュメント

- 構築・再構築・IP変更: [DEPLOY.md](DEPLOY.md)
- 通知・監視・Gmail見張り番の設定: [NOTIFY.md](NOTIFY.md)
- セキュリティ: [SECURITY.md](SECURITY.md)
- 障害対応ナレッジ: [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
- Discord Bot API仕様: [../docs/ADMIN_API.md](../docs/ADMIN_API.md)
