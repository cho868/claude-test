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
| 体験の再認証 | 2GB=**4日ごと** / 4GB=2日ごとに管理画面で認証。忘れると停止 | Discord毎日通知 + 死活監視（NOTIFY.md） |
| 体験終了日 | API で取得不可。更新は**前日のみ** | 終了日を控えて前日アラート（NOTIFY.md） |
| no-ip | 無料ホスト名は**約30日ごと**に確認メールのクリック | 同上の毎日通知でカバー |
| データ保護 | 体験終了/認証漏れで**消える可能性** | SQLite を毎日バックアップ（後述）。コードは GitHub に有り |

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

## 11. バックアップ（重要）

```bash
sudo crontab -e
# 毎日3時に SQLite をコピー
0 3 * * * cp /var/www/portal/database/database.sqlite /root/portal-backup-$(date +\%F).sqlite
# 7日より古いバックアップを削除
30 3 * * * find /root -name 'portal-backup-*.sqlite' -mtime +7 -delete
```
※ 守るべきは **DBファイル**。コードは GitHub にあるので再デプロイで戻せます。

---

## 🔁 更新のしかた（コード変更後）

GitHub に push したら、サーバーで:
```bash
cd /var/www/portal && sudo bash deploy/deploy-app.sh main
```

## 🆘 ゼロからの再構築（サーバーが消えた時）

1. 新しい XServer VPS を作成（手順1〜3）
2. `setup-server.sh` → `deploy-app.sh`（手順4〜5）
3. バックアップした `database.sqlite` を戻す:
   ```bash
   sudo cp /path/to/portal-backup-YYYY-MM-DD.sqlite /var/www/portal/database/database.sqlite
   sudo chown www-data:www-data /var/www/portal/database/database.sqlite
   ```
4. no-ip のIPを新IPに更新（手順7）→ certbot（手順8）→ 通知（手順10）

> バックアップさえ持っていれば、**30分ほどで完全復旧**できます。

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
