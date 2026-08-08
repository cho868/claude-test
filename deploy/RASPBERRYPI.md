# 🍓 ラズパイ自宅サーバー構築ガイド（Tailscale Funnel で公開・完全無料）

Raspberry Pi 4 を自宅サーバーにして身内ポータルを公開する手順。
**カード不要・更新作業なし・恒久無料**（電気代だけ）。XServer/Oracleの悩みから完全に解放される。

- 公開方法: **Tailscale Funnel**（MAP-E/CGNATでもOK・ポート開放不要・HTTPS自動）
- URL: `https://<マシン名>.<テイルネット名>.ts.net`（固定。身内はこれをブックマーク）
- 前提: この家の回線は **MAP-E**（IPv4 over IPv6）＝80/443のポート開放不可 → だからトンネル方式

> なぜ Cloudflare Tunnel でなく Tailscale Funnel か:
> Cloudflare Tunnel は「Cloudflareで管理するドメイン」が必須で、no-ipの `madgear.sytes.net` は載せられない。
> Tailscale Funnel はドメイン不要（ts.netをくれる）でMAP-Eでも動くので、今の環境に最適。

---

## 0. 用意するもの

- Raspberry Pi 4 Model B スターターキット（本体・ケース・ヒートシンク・ファン・電源・microSD）
- microSDを焼くためのPC（カードリーダー）
- **できれば USB接続のSSD**（microSDは書き込み寿命があり突然死しやすい。SSD起動が安定・推奨）
  - 最初はmicroSDで始めてOK。ただし **Discordバックアップ（後述）は必須**

---

## 1. OSを焼く（PCで）

1. PCに **Raspberry Pi Imager** をインストール: https://www.raspberrypi.com/software/
2. 起動して:
   - デバイス: **Raspberry Pi 4**
   - OS: **Other general-purpose OS → Ubuntu → Ubuntu Server 24.04 LTS (64-bit)**
   - ストレージ: microSD（or USB-SSD）
3. **⚙️（歯車/次へ→設定を編集）で以下を必ず設定**（これで最初からSSH接続できる）:
   - ホスト名: 例 `portal`
   - **SSHを有効化**（パスワード認証 or 公開鍵）
   - ユーザー名/パスワード: 例 `pi` / 好きなパスワード
   - **Wi-Fi**: 家のSSID/パスワード（有線LANなら不要）
   - ロケール: Asia/Tokyo
4. 書き込み → 完了したらSDをPiに挿す

## 2. 組み立て & 初回起動

1. ヒートシンクを貼り、ファンをGPIOに接続、ケースに収める
2. SD（orSSD）を挿して電源ON。1〜2分待つ
3. PCから接続（同じ家のネット内）:
   ```bash
   ssh pi@portal.local        # ダメなら ssh pi@<PiのIP>（ルーター管理画面でIP確認）
   ```
4. 更新:
   ```bash
   sudo apt-get update && sudo apt-get upgrade -y
   ```

## 3. ポータルを構築（既存スクリプトそのまま）

```bash
sudo apt-get install -y git
sudo git clone https://github.com/cho868/claude-test.git /root/portal-src
cd /root/portal-src
sudo bash deploy/setup-server.sh          # nginx/PHP/Composer/certbot等
sudo bash deploy/deploy-app.sh main       # アプリ配置
```
※ certbot は入るが、**公開HTTPSは Tailscale が肩代わりする**ので certbot は使わなくてよい（内部向けに残るだけ）。
※ nginx はローカルの80番でHTTP配信のままでOK（外向けHTTPS化はTailscaleが担当）。

`http://portal.local/` を家のPCブラウザで開いてログイン画面が出れば、アプリ自体は動いている。

## 4. Tailscale を入れて公開（Funnel）

```bash
# インストール
curl -fsSL https://tailscale.com/install.sh | sh

# サインイン（表示されるURLをPCブラウザで開いて認証。無料・カード不要。
#   Google/GitHub/メール等でログインでOK）
sudo tailscale up

# 管理コンソール(https://login.tailscale.com/admin) で先に:
#   - DNS → MagicDNS を有効化 & HTTPS証明書を有効化(Enable HTTPS)
#   - （Funnelが未許可なら）ACL/デバイス設定で Funnel を許可

# ローカルの nginx(80番) を、公開HTTPSとして Funnel で出す
sudo tailscale funnel --bg 80

# 公開URLを確認
tailscale funnel status
```
表示された **`https://portal.<テイルネット名>.ts.net`** が公開URL。外（モバイル回線など）から開いて、ログイン画面が出れば公開成功！

## 5. アプリを公開URLに合わせる

```bash
sudo vi /var/www/portal/.env
#   APP_URL=https://portal.xxxx.ts.net    ← Funnelの実URL
#   SESSION_SECURE_COOKIE=true
sudo -u www-data php /var/www/portal/artisan config:cache
```
※ 前段HTTPSの信頼設定は `bootstrap/app.php` で `trustProxies(at: '*')` 済みなので、
　 リダイレクトループや mixed-content は起きない想定。もし出たら APP_URL のhttpsを再確認。

## 6. DBを復元（Discordバックアップがあれば）

```bash
cd /tmp
openssl enc -d -aes-256-cbc -pbkdf2 -in portal-YYYY-MM-DD.sqlite.gz.enc -out portal.sqlite.gz -pass pass:'あなたのBACKUP_PASSPHRASE'
gunzip portal.sqlite.gz
sudo cp portal.sqlite /var/www/portal/database/database.sqlite
sudo chown www-data:www-data /var/www/portal/database/database.sqlite
sudo -u www-data php /var/www/portal/artisan migrate --force
```
（無ければ空スタート。最初に新規登録した人が管理者）

## 7. バックアップと通知（更新監視はもう不要）

```bash
sudo cp deploy/portal-notify.conf.example /etc/portal-notify.conf
sudo vi /etc/portal-notify.conf
#   DISCORD_WEBHOOK / BACKUP_WEBHOOK / BACKUP_PASSPHRASE を設定
#   REAUTH_INTERVAL_HOURS は空でよい（自宅サーバーは更新不要）
sudo crontab -e
0 9 * * *   /var/www/portal/deploy/notify.sh
10 3 * * *  /var/www/portal/deploy/backup-to-discord.sh
sudo /var/www/portal/deploy/backup-to-discord.sh   # 手動テスト
```
- **VPS更新関連(`--vps-final`/`--reauth-remind`)は入れない**（期限が無い）
- no-ip はもう使わないなら確認不要（ts.netに移行するため）。使い続けるなら別途
- 🍓 **SDカード運用なら backup-to-discord.sh を必ず動かす**（SD破損対策の生命線）

---

## 運用メモ

- **自動起動**: nginx/php-fpm/tailscale は systemd で自動起動。停電後も電源が戻れば自動復帰。
  Funnelは `--bg` で常駐設定として保存される。
- **停電/再起動対策**: UPS(無停電電源)まではなくても、`backup-to-discord.sh` があれば最悪データは戻せる。
- **SD→SSD移行**: 安定運用するなら USB-SSD にOSを焼き直して起動元を変更（`rpi-clone` 等でも移せる）。
- **熱**: スターターキットのファン+ヒートシンクを付けていれば常用で問題なし。`vcgencmd measure_temp` で温度確認可。
- **セキュリティ**: 公開はTailscale経由のみ・SSHは家庭内LANのみなので露出は小さい。`deploy/harden-server.sh` も任意で。

## まとめ

- MAP-E回線でも **Tailscale Funnel** で公開できる（ポート開放・固定IP・ドメイン購入すべて不要）
- 構築は既存の `setup-server.sh`/`deploy-app.sh` がそのまま使える
- **更新作業ゼロ・カード不要・恒久無料**。唯一の注意はSDカード寿命→SSD化 or バックアップ徹底
