# 🚀 身内ポータル 構築・再構築 手順書（XServer VPS 無料運用）

XServer VPS の無料体験サーバーに、身内ポータル（Laravel 13）を **無料で公開** する完全手順。
**落ちた時の再構築・友人への共有**を想定し、ゼロから同じ環境を作れるようにまとめています。

- サーバー: **XServer VPS 無料体験 / 2GBプラン / Ubuntu 26.04 LTS**
- ドメイン: **no-ip（無料）** … 例 `myportal.ddns.net`
- SSL: **Let's Encrypt（無料・自動更新）**
- DB: **SQLite**（DBサーバー不要・省メモリ）
- 通知/死活監視: **Discord + healthchecks.io**（→ [NOTIFY.md](NOTIFY.md)）

> このリポジトリ `cho868/claude-test` を friend と共有すれば、**コードも手順もスクリプトも一式**が渡せます。
> この `DEPLOY.md` はそのまま Qiita / Zenn 記事にも転用できます。

---

## 全体像

```
[ブラウザ] ──https──> [no-ip ドメイン] ──A record──> [XServer VPS の固定IP]
                                                          │
                                          nginx → PHP(8.5) → Laravel → SQLite
```

無料で必要なアカウント: XServer / no-ip / Discord / healthchecks.io / GitHub

---

## 0. ⚠️ 無料運用で忘れてはいけないこと

| 項目 | 内容 | 対策 |
|------|------|------|
| 体験の再認証 | ⚠️**2026-07から新仕様: 24時間で終了・12時間ごとに更新必須**（旧: 2GB=4日/4GB=2日） | `REAUTH_INTERVAL_HOURS="12"` を設定し、更新のたびに `sudo deploy/renewed.sh` で記録（NOTIFY.md「🕛12時間更新」参照）。長期運用はOracle移行を推奨 |
| 体験終了日 | API で取得不可。更新は**前日のみ** | 終了日を控えて前日アラート（NOTIFY.md） |
| no-ip | 無料ホスト名は**約30日ごと**に確認メールのクリック | 同上の毎日通知でカバー |
| データ保護 | 体験終了/認証漏れ/障害で**VPSごと消える**（＝VPS上のバックアップも道連れ） | **オフサイト**でDBを毎日バックアップ（後述・Discordへ退避）。コードは GitHub に有り |

> 🕛 **12時間更新の運用フロー**: ①`REAUTH_INTERVAL_HOURS="12"` を設定 → ②6時間ごとの `--reauth-remind` cron が「更新した？」と聞いてくる → ③管理画面で更新したら `sudo /var/www/portal/deploy/renewed.sh` で記録（残り時間の正確なカウントダウンになり、残り3時間で毎時@everyone）。詳細は NOTIFY.md。
> ※旧仕様(日単位)のプランなら従来どおり `REAUTH_INTERVAL_DAYS` だけでOK。

---

## 1. XServer VPS を作る

1. XServer VPS にログイン →「無料体験」→ **2GBプラン**
2. OSイメージ: **Ubuntu 26.04 LTS**
3. SSHキー（推奨）or rootパスワードを設定して作成
4. 表示される **グローバルIP**（例 `203.0.113.45`）を控える

## 2. ポートを開ける（**2か所**ある！）

### ① XServer VPS の「パケットフィルター」＝ Web管理画面
OSの外側のファイアウォール。**コマンドではなく管理画面**で設定。
- VPSパネル →「パケットフィルター設定」
- **SSH(22) / HTTP(80) / HTTPS(443)** を許可（「Web」プリセットで80/443、SSHも残す）

> ここを開けないと、OS側を開けても外から繋がりません。**先にここ。**

### ② OS側の `ufw`
こちらは **`setup-server.sh` が自動で設定**（SSH/HTTP/HTTPS許可 + 有効化）するので、手打ち不要。
手動でやる場合は **SSH(22)を必ず先に許可**してから enable（順番を間違えると締め出されます）:
```bash
sudo ufw allow 22/tcp && sudo ufw allow 80/tcp && sudo ufw allow 443/tcp && sudo ufw enable
```

## 3. SSH で接続

```bash
ssh root@203.0.113.45        # ← あなたのIP
cat /etc/os-release | head -2 # Ubuntu 26.04 LTS を確認
```

## 4. サーバー初期セットアップ（自動）

```bash
cd /root
git clone https://github.com/cho868/claude-test.git portal-src
cd portal-src
sudo bash deploy/setup-server.sh
```
これで **nginx / PHP（標準8.5）/ 必要拡張 / Composer / certbot / jq / ufw / nginx site設定** が一括で入ります。
PHPバージョンとFPMソケットは自動検出して nginx 設定に反映されます。

## 5. アプリを配置（自動）

```bash
sudo bash deploy/deploy-app.sh main
```
（取得 → `composer install` → `.env`作成 → `key:generate` → `migrate --seed` → キャッシュ最適化 → 権限設定）

> まだ `main` にマージしていない場合はブランチ名を指定:
> `sudo bash deploy/deploy-app.sh claude/busy-faraday-jvrw2n`

`.env` を編集（後でURL確定後でもOK）:
```bash
sudo vi /var/www/portal/.env      # APP_URL を後でドメインに
```

## 6. 動作確認（IP直打ち）

ブラウザで `http://203.0.113.45/` → **ログイン画面**が出れば成功。
（502 の場合は `sudo systemctl status php*-fpm nginx` と `sudo tail -f /var/log/nginx/error.log`）

## 7. no-ip で無料ドメイン

1. https://www.noip.com/ で登録
2. Dynamic DNS → **Create Hostname**
   - Hostname: 例 `myportal` / Domain: `ddns.net` → `myportal.ddns.net`
   - **IPv4 Address: VPSのIP**
3. `ping myportal.ddns.net` でVPSのIPが返ればOK
   - XServer VPS のIPは固定なので更新クライアント(DUC)は不要。無料名の**30日確認**だけ必要。

## 8. HTTPS（無料SSL）

```bash
sudo certbot --nginx -d myportal.ddns.net
# メール入力 → 同意 → HTTPSへリダイレクト(2) を選択
```
`.env` を本番向けに:
```bash
sudo vi /var/www/portal/.env
#   APP_URL=https://myportal.ddns.net
#   SESSION_SECURE_COOKIE=true
sudo -u www-data php /var/www/portal/artisan config:cache
```
✅ `https://myportal.ddns.net` で公開完了！

> 証明書の更新は **certbot が自動**（`systemctl list-timers | grep certbot` で確認、`sudo certbot renew --dry-run` でテスト）。

## 9. 最初の管理者

ブラウザで「新規登録」→ **最初に登録した人が自動で管理者**。

## 10. 通知・死活監視を設定

体験終了/認証忘れ/停止に気づけるように **→ [NOTIFY.md](NOTIFY.md)** を実施（Discord Webhook + healthchecks.io + cron）。

## 11. バックアップ（最重要）

> 💀 **過去にVPSごと消えて全データを失った事故あり。** 原因は「バックアップ先が同じVPS上(`/root`)」だったこと。
> VPSが消えるとVPS上のバックアップも一緒に消える。**バックアップは必ず "VPSの外" に置く。**

### ✅ 推奨: オフサイト・バックアップ（Discordへ毎日退避）
`deploy/backup-to-discord.sh` が DBスナップショットを gz（任意でAES暗号化）にして Discord チャンネルへアップロードします。VPSが全損しても Discord から落として復元できます。

```bash
# 1) 設定（notifyと共用。暗号化パスフレーズは強く推奨。専用チャンネルのWebhook推奨）
sudo vi /etc/portal-notify.conf
#   BACKUP_WEBHOOK="https://discord.com/api/webhooks/..."   # 空なら DISCORD_WEBHOOK を流用
#   BACKUP_PASSPHRASE="長めのパスフレーズ"                    # 復元に必須→パスワードマネージャ等でVPSの外に控える

# 2) cron 登録（毎日3:10）
sudo crontab -e
10 3 * * * /var/www/portal/deploy/backup-to-discord.sh

# 3) 手動で一度テスト（Discordにファイルが届けばOK）
sudo /var/www/portal/deploy/backup-to-discord.sh
```

※ スクリプトはローカル(`/root/portal-backups`)にも7世代コピーしますが、それは**二次**。一次防衛はあくまでオフサイト。
※ DBが大きくなり Discord 上限(目安9MB)を超えると送信をスキップして警告します。その時は rclone で Google Drive 等へ切替（希望あれば手順追加）。

### 代替: 手元PCへ定期ダウンロード
Discordを使わないなら、手元PCの cron/タスクで:
```bash
scp user@<VPS_IP>:/var/www/portal/database/database.sqlite ./portal-backup-$(date +%F).sqlite
```

守るべきは **DBファイルと `.env`**。コードは GitHub にあるので再デプロイで戻せます（`.env` の秘密情報＝APP_KEY / STEAM_API_KEY / Discord Bot キー等はgit管理外なので別途控える）。

---

## 🔁 更新のしかた（コード変更後）

GitHub に push したら、サーバーで:
```bash
cd /var/www/portal && sudo bash deploy/deploy-app.sh main
```

## 🆘 ゼロからの再構築（サーバーが消えた時）

新しいVPSは**IPが変わる**。コードはGitHubにあるので、やることは「再デプロイ＋IP関連の付け替え＋データ復元」の3つ。

### 手順
1. 新しい XServer VPS を作成（手順1〜3）。**新しいグローバルIPを控える。**
2. `setup-server.sh` → `deploy-app.sh`（手順4〜5）。この時点で**空のDB**が seed 付きで作られる。
3. **DBを復元**（オフサイト・バックアップがある場合）:
   ```bash
   # Discordから最新の portal-YYYY-MM-DD.sqlite.gz(.enc) をVPSに置いてから
   cd /tmp
   # 暗号化している場合はまず復号（パスフレーズが必要）
   openssl enc -d -aes-256-cbc -pbkdf2 -in portal-YYYY-MM-DD.sqlite.gz.enc -out portal.sqlite.gz -pass pass:'あなたのBACKUP_PASSPHRASE'
   gunzip portal.sqlite.gz
   sudo cp portal.sqlite /var/www/portal/database/database.sqlite
   sudo chown www-data:www-data /var/www/portal/database/database.sqlite
   sudo -u www-data php /var/www/portal/artisan migrate --force   # スキーマ差分があれば適用
   ```
4. **`.env` の秘密情報を再投入**（別途控えたもの: `APP_KEY` / `STEAM_API_KEY` / `BOT_ADMIN_KEY` / `REGISTRATION_INVITE_CODE` / メール等）。控えが無ければ `APP_KEY` は再生成でOK（既存セッションが無効になるだけ）。
5. **IP関連の付け替え → 下の「IP変更時チェックリスト」を実施。**
6. certbot（手順8）→ 通知（手順10）→ **バックアップcronを再設定（手順11）** ← これを忘れると同じ事故を繰り返す。

> オフサイト・バックアップさえあれば、**30分ほどで完全復旧**できます。

### ⚠️ バックアップが無い（＝今回のように全損した）場合
DBはVPS上にしか無く、オフサイトの控えも無ければ、**アカウント・ポイント・各種記録は復元できません**（コードと手順は無傷）。
- 手順1〜2で**空の状態から作り直し**、「新規登録した最初の人が管理者」になる（手順9）。
- 資料/ナレッジ（`deploy/*.md` や `docs/ADMIN_API.md`）は seeder で自動的にポータル内に復活する。
- 今後のために**必ず手順11のオフサイト・バックアップを設定**しておくこと。

---

## 🔀 IP変更時チェックリスト（VPSを作り直したら必ず）

新IPを `NEW_IP`、ドメインを `madgear.sytes.net` とする。

| # | 対象 | やること |
|---|------|----------|
| 1 | **no-ip** | Dynamic DNS → 対象ホスト名の **IPv4 を `NEW_IP` に更新** → `ping madgear.sytes.net` で `NEW_IP` が返るまで待つ（DNS反映に数分） |
| 2 | **XServer パケットフィルター** | 新VPSで **SSH(22)/HTTP(80)/HTTPS(443)** を再度許可（新サーバーは初期状態） |
| 3 | **手元PCの SSH known_hosts** | ⚠️**自分のPCで実行**（サーバーではない）。`ssh-keygen -R NEW_IP` と `ssh-keygen -R madgear.sytes.net`。これは鍵を作るコマンドではなく、`~/.ssh/known_hosts` に残った**旧サーバーのホスト鍵のメモを消すだけ**（残っていると REMOTE HOST IDENTIFICATION HAS CHANGED 警告で接続拒否される）。消したら次の接続で fingerprint に yes |
| 4 | **~/.ssh/config** | IP直指定していたら `HostName` を `NEW_IP` に更新。自分の鍵ペアはPC側にあるので無事だが、サーバー側の `authorized_keys` は消えたため、公開鍵を新サーバーへ再登録（`ssh-copy-id` か XServerパネルのSSHキー設定）。それまではパスワードログイン |
| 5 | **Let's Encrypt** | DNSが新IPを指した後に `sudo certbot --nginx -d madgear.sytes.net`（ドメインは同じなので再取得でOK。※短時間に何度も失敗すると週次レート制限あり） |
| 6 | **`.env` の APP_URL** | ドメイン運用なら `https://madgear.sytes.net` のまま。IP直運用中なら `NEW_IP` に。変更後 `php artisan config:cache` |
| 7 | **通知/死活監視** | `/etc/portal-notify.conf` を作り直し。**UptimeRobot / healthchecks はドメイン監視なら変更不要**、IP監視にしていたら `NEW_IP` に更新 |
| 8 | **バックアップ cron** | 手順11を再設定（最重要・再発防止） |

> **変わらないもの**（付け替え不要）: Discord Webhook URL、no-ip の**ホスト名そのもの**（madgear.sytes.net）、GitHubリポジトリ、STEAM_API_KEY、Discord Bot トークン/キー。
> つまり「変わるのは実質 **IP** だけ」で、それに紐づく **DNS・SSH鍵・SSL・ファイアウォール**を付け替えれば戻ります。

## 🧰 トラブルシュート

| 症状 | 確認 |
|------|------|
| 外から繋がらない | XServer **パケットフィルター**（①）と `sudo ufw status`（②）両方 |
| 502 Bad Gateway | `sudo systemctl status php*-fpm`、ソケットパスが nginx 設定と一致してるか |
| 500 / 真っ白 | `sudo tail -50 /var/www/portal/storage/logs/laravel.log`、`storage`権限 |
| 書き込みエラー | `sudo chown -R www-data:www-data storage database bootstrap/cache` |
| HTTPSにならない | ドメインがVPSのIPを指しているか（`ping`）→ certbot再実行 |

---

## ずっと無料にしたい場合

XServer の体験再認証が面倒なら、**Oracle Cloud の Always Free（恒久無料VM）** に
同じ `deploy/` スクリプトでほぼそのまま移行できます（Ubuntuを選べば手順は同じ）。希望あれば手順を用意します。
