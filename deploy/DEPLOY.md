# 🚀 デプロイ手順（XServer VPS 無料体験 + no-ip + 無料SSL）

身内ポータルを **無料で公開** するための手順です。
XServer VPS の無料体験サーバーに置き、no-ip の無料ドメインと Let's Encrypt の無料SSLで HTTPS 公開します。

> **このポータルの規模なら 2GB プランで十分**です（むしろ余裕）。
> 2GBプランは再認証が4日に1回で、4GB(2日に1回)より手間が少ないので 2GB 推奨。

---

## 全体像

```
[利用者のブラウザ]
      │  https://あなた.ddns.net
      ▼
[no-ip 無料ドメイン] ──(A レコード)──> [XServer VPS のグローバルIP]
                                              │
                                   nginx → PHP 8.3 → Laravel
                                              │
                                        SQLite (database/database.sqlite)
```

必要なもの（すべて無料）:
- XServer アカウント（無料体験）
- no-ip アカウント（無料ドメイン）
- このリポジトリ（GitHub）

---

## 1. XServer VPS を作る

1. XServer VPS にログイン →「無料体験」で **2GBプラン** を選択
2. **OSイメージは「Ubuntu 24.04」** を選ぶ（PHP 8.3 がそのまま使えるため推奨）
3. SSHキー or rootパスワードを設定して作成
4. 作成後に表示される **グローバルIPアドレス**（例: `203.0.113.45`）をメモ
5. パケットフィルター/ファイアウォール設定で **80(HTTP)・443(HTTPS)・22(SSH)** を許可

> ⚠️ 無料体験は**定期的な再認証が必要**（2GB=4日に1回 / 4GB=2日に1回）。
> 再認証を忘れるとサーバーが止まります。カレンダー通知などを入れておくと安心。

## 2. SSH で接続

```bash
ssh root@203.0.113.45        # ← あなたのIP
```

## 3. サーバーの初期セットアップ

リポジトリを取得して、付属スクリプトで nginx + PHP8.3 + Composer + certbot を一括導入します。

```bash
cd /root
git clone https://github.com/cho868/claude-test.git portal-src
cd portal-src
sudo bash deploy/setup-server.sh
```

## 4. アプリを配置

```bash
sudo bash deploy/deploy-app.sh main
```

> まだ変更が `main` に入っていない場合は、GitHub で `claude/busy-faraday-jvrw2n` を `main` にマージするか、
> ブランチ名を指定して実行: `sudo bash deploy/deploy-app.sh claude/busy-faraday-jvrw2n`

`.env` が作られるので、ドメインに合わせて編集:

```bash
sudo nano /var/www/portal/.env
# APP_URL=https://あなた.ddns.net   に変更（後で）
```

## 5. nginx を設定

```bash
cd /var/www/portal
sudo cp deploy/nginx-portal.conf /etc/nginx/sites-available/portal
# server_name をあなたのドメインに変更
sudo nano /etc/nginx/sites-available/portal       # example.ddns.net → あなた.ddns.net

sudo ln -sf /etc/nginx/sites-available/portal /etc/nginx/sites-enabled/portal
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

この時点で `http://203.0.113.45/`（IP直打ち）にアクセスするとログイン画面が出ます。

## 6. no-ip で無料ドメインを設定

1. https://www.noip.com/ で無料登録
2. 「Dynamic DNS」→ **Create Hostname**
   - Hostname: 好きな名前（例 `myportal`）
   - Domain: 無料の `ddns.net` などを選択 → `myportal.ddns.net`
   - **IPv4 Address: XServer VPS のグローバルIP** を入力
3. 保存 → 数分待つと `myportal.ddns.net` が VPS を指します

確認:
```bash
ping myportal.ddns.net     # VPSのIPが返ればOK
```

> XServer VPS の IP は基本固定なので、no-ip の更新クライアント(DUC)は不要です。
> ただし **無料ホスト名は約30日ごとに確認メールのリンクをクリック** して維持する必要があります。

## 7. HTTPS（無料SSL）を有効化

ドメインが VPS を指すようになったら:

```bash
sudo certbot --nginx -d myportal.ddns.net
# メールアドレス入力 → 規約同意 → HTTPSへリダイレクトする(2)を選択
```

その後 `.env` を本番向けに:
```bash
sudo nano /var/www/portal/.env
#   APP_URL=https://myportal.ddns.net
#   SESSION_SECURE_COOKIE=true
sudo -u www-data php /var/www/portal/artisan config:cache
```

✅ これで `https://myportal.ddns.net` で公開完了！

## 8. 最初の管理者を作る

ブラウザでアクセス →「新規登録」。**最初に登録した人が自動で管理者**になります。

---

## 🔄 更新のしかた（コードを変えたとき）

GitHub に push したあと、サーバーで:
```bash
cd /var/www/portal
sudo bash deploy/deploy-app.sh main
```
（取得 → composer → migrate → キャッシュ更新までやってくれます）

## 💾 バックアップ（重要）

無料体験は**期限切れや認証漏れでデータが消えるリスク**があります。最低限 SQLite を定期保存:

```bash
# 例: 毎日3時にバックアップ（cron）
sudo crontab -e
0 3 * * * cp /var/www/portal/database/database.sqlite /root/portal-backup-$(date +\%F).sqlite
```
※ コード自体は GitHub にあるので、消えても再デプロイで復旧できます。守るべきは **DBファイル** です。

---

## ⚠️ 無料運用の注意点（正直なところ）

| 項目 | 内容 |
|------|------|
| 再認証 | XServer VPS 無料体験は 2GB=**4日ごと** / 4GB=**2日ごと** に Web 認証が必要。忘れると停止。 |
| 体験期間 | 「無料体験」は恒久無料とは限りません。期間・条件は XServer の最新規約で必ず確認を。期限が来たら有料化 or 移行（DBをバックアップしておけば移行は簡単）。 |
| no-ip | 無料ホスト名は **約30日ごとの確認**が必要。 |
| 規模 | 身内数人〜数十人なら 2GB + SQLite で快適。アクセスが増えたら MySQL へ（.env を切り替えるだけ）。 |

> もし「再認証が面倒」「ずっと無料がいい」となったら、**Oracle Cloud の Always Free**（恒久無料VM）に
> 同じ `deploy/` スクリプトでほぼそのまま移せます。希望があれば手順を用意します。

---

## 🔔 体験終了・認証忘れを防ぐ通知

XServer VPS は API が無く終了日を自動取得できないため、**毎日のDiscord通知 + 死活監視**で取りこぼしを防ぎます。
証明書(Let's Encrypt)の自動更新確認も含め、設定は **[NOTIFY.md](NOTIFY.md)** を参照してください。
