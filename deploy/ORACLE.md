# ☁️ Oracle Cloud Always Free 移行ガイド（恒久無料・更新作業ゼロ）

XServer無料VPSの「12時間ごと更新」地獄から解放されるための移行手順。
**Oracle Cloud Infrastructure (OCI) の Always Free 枠**は、クレカ登録は要るが
**期限も更新作業も無い**（払わなければ0円）。スペックも大幅に上がる。

`deploy/` の構築スクリプトはほぼそのまま使える。違いは「Oracle特有の初期設定」だけで、
そこを本ガイドと `deploy/oracle-firewall.sh` でカバーする。

| | XServer無料VPS | Oracle Always Free |
|---|---|---|
| 更新作業 | **12時間ごと(地獄)** | **無し・恒久無料** |
| スペック | 2GB RAM | Ampere: **4コア/24GB** or AMD: 1GB |
| リージョン | 国内 | **東京/大阪** あり |
| IP | 固定 | 予約すれば固定 |

---

## 0. 事前に決めること：どのシェイプ（VM種別）にするか

| シェイプ | スペック | 入手性 | おすすめ |
|---|---|---|---|
| **VM.Standard.A1.Flex**（Ampere/Arm） | 最大4 OCPU・24GB（Always Free枠） | ★人気で「Out of capacity」が出やすい | 取れれば最高 |
| **VM.Standard.E2.1.Micro**（AMD/x86） | 1 OCPU・1GB | ★常に空いている | 確実に作れる。うちのポータルは1GBでも十分動く |

> 迷ったら **まずA1 Flexを1 OCPU/6GBで狙い、`Out of host capacity`が出たらE2.1.Micro**に切り替える。
> このポータル(Laravel+SQLite)は1GBでも快適なので、E2でも問題ない。あとからA1に載せ替えも可能。

---

## 1. アカウント作成（無料・クレカは本人確認用）

1. https://www.oracle.com/cloud/free/ →「Start for free」
2. 国=Japan、メール、電話番号（SMS認証）
3. **クレジットカード登録**（本人確認のみ。Always Free枠内なら**課金されない**）
4. ホームリージョンに **Japan East (Tokyo)** か **Japan Central (Osaka)** を選ぶ（後から変更不可）

> 💡 課金が不安なら、コンソール →「支払いとコスト管理」→ **予算アラート**を$1で設定しておくと安心。
> Always Freeのリソースしか作らなければ請求は発生しない。

---

## 2. インスタンス（VM）を作る

1. コンソール →「コンピュート」→「インスタンス」→ **インスタンスの作成**
2. **名前**: 例 `portal`
3. **イメージとシェイプ**:
   - イメージ: **Canonical Ubuntu 24.04**（またはLTS最新）
   - シェイプ: 上の表で選ぶ（A1.Flex or E2.1.Micro）
4. **SSHキー**:
   - 「自分のキーを追加」で手元の公開鍵（`~/.ssh/id_ed25519.pub` 等）を貼る
   - 鍵が無ければ「キーペアを生成」で秘密鍵をDLして安全に保管
5. **ネットワーク**: 新規VCN作成でOK（自動）。「パブリックIPの割り当て」= **はい**
6. 作成 → 数分で「実行中」。**パブリックIPアドレス**を控える（例 `140.83.x.x`）

> ⚠️ `Out of host capacity` が出たら: シェイプをE2.1.Microにする／別のAvailability Domainを選ぶ／
> 時間をおいて再試行。A1はPay As You Go化(無料枠は維持)すると取りやすくなることがある。

### （推奨）パブリックIPを予約IPにする
既定のIPは「エフェメラル」で、インスタンス停止で変わることがある。no-ipが指すので固定したい:
- インスタンス →「アタッチされたVNIC」→ IPv4アドレス →「エフェメラル」を **予約済みに変換**

---

## 3. ポートを開ける（**2か所**。XServerのパケットフィルターと同じ構図）

### ① OCI セキュリティリスト（外側の壁・コンソール）
1. コンソール →「ネットワーキング」→「仮想クラウド・ネットワーク」→ 作成されたVCN
2. 「セキュリティ・リスト」→ デフォルトのもの →「イングレス・ルールの追加」
3. 以下を2つ追加（ソース `0.0.0.0/0`・IPプロトコル TCP）:
   - 宛先ポート **80**
   - 宛先ポート **443**
   （22はデフォルトで開いている）

### ② OS内 iptables（内側の壁・Oracle特有の罠）
Oracleの公式Ubuntuは初期状態で **22以外を REJECT** している。①を開けても②で弾かれるので、
サーバー上でこれを実行（後述の手順4で clone 後）:
```bash
sudo bash /root/portal-src/deploy/oracle-firewall.sh   # 80/443 を許可して永続化
```

---

## 4. 接続してサーバー構築

Oracle Ubuntu の初期ユーザーは **`ubuntu`**（rootでは直接入らない）:
```bash
# 手元PCから
ssh ubuntu@140.83.x.x          # ← あなたのIP

# サーバー上で
sudo apt-get update -y && sudo apt-get install -y git
sudo git clone https://github.com/cho868/claude-test.git /root/portal-src
cd /root/portal-src

# ① ファイアウォール(OS内) を開ける
sudo bash deploy/oracle-firewall.sh

# ② 初期セットアップ（ufwは使わずiptablesに任せるので SKIP_UFW=1）
sudo SKIP_UFW=1 bash deploy/setup-server.sh

# ③ アプリ配置
sudo bash deploy/deploy-app.sh main
```

`http://140.83.x.x/` でログイン画面が出れば成功。
（出ない時は「①OCIセキュリティリスト」と「②oracle-firewall.sh」の両方を再確認）

---

## 5. DBを復元（Discordバックアップから）

XServerが消えていても、**Discordに退避したDBがあれば戻せる**:
```bash
# Discordから最新の portal-YYYY-MM-DD.sqlite.gz(.enc) をサーバーに置いてから
cd /tmp
openssl enc -d -aes-256-cbc -pbkdf2 -in portal-YYYY-MM-DD.sqlite.gz.enc -out portal.sqlite.gz -pass pass:'あなたのBACKUP_PASSPHRASE'
gunzip portal.sqlite.gz
sudo cp portal.sqlite /var/www/portal/database/database.sqlite
sudo chown www-data:www-data /var/www/portal/database/database.sqlite
sudo -u www-data php /var/www/portal/artisan migrate --force
```
（バックアップが無い場合は空スタート。最初に新規登録した人が管理者）

---

## 6. ドメイン切替（no-ip）と HTTPS

1. no-ip: `madgear.sytes.net` の IPv4 を **Oracleの新IP**に更新 → `nslookup madgear.sytes.net 8.8.8.8` で確認
2. 手元PCの古い鍵を掃除: `ssh-keygen -R 新IP` / `ssh-keygen -R madgear.sytes.net`
3. HTTPS:
   ```bash
   sudo vi /etc/nginx/sites-available/portal   # server_name _; → server_name madgear.sytes.net;
   sudo nginx -t && sudo systemctl reload nginx
   sudo certbot --nginx -d madgear.sytes.net   # リダイレクトは 2
   sudo vi /var/www/portal/.env                # APP_URL=https://madgear.sytes.net / SESSION_SECURE_COOKIE=true
   sudo -u www-data php /var/www/portal/artisan config:cache
   ```

詳しくは「[DEPLOY.md](DEPLOY.md) の IP変更時チェックリスト」と同じ（クラウド側の壁がパケットフィルター→セキュリティリストに変わるだけ）。

---

## 7. .env の秘密情報・通知・バックアップ

```bash
sudo vi /var/www/portal/.env
#   STEAM_API_KEY / BOT_ADMIN_KEY / REGISTRATION_INVITE_CODE など再投入
sudo -u www-data php /var/www/portal/artisan config:cache

# 通知・バックアップ（Discord/LINE/Gmail監視はそのまま使える）
sudo cp deploy/portal-notify.conf.example /etc/portal-notify.conf
sudo vi /etc/portal-notify.conf
sudo crontab -e
0 9 * * *   /var/www/portal/deploy/notify.sh
10 3 * * *  /var/www/portal/deploy/backup-to-discord.sh
```

### 🎉 更新関連はもう不要
Oracleは期限が無いので、**VPS更新の cron と設定は削除でOK**:
- cron の `--vps-final` / `--reauth-remind` 行は**入れない**
- `/etc/portal-notify.conf` の `REAUTH_INTERVAL_HOURS` は**空**にする（→「認証リマインド」行が出なくなる）
- `renewed.sh` / `--vps-final` はもう使わない（XServerに戻る時のために残置）
- no-ipの30日確認と Let's Encrypt自動更新の監視だけは引き続き有効

### アイドル停止に注意（Always Free特有）
無料インスタンスは「CPUほぼ0%が7日続く」と停止対象になることがある。
うちは cron が毎日動く＋身内アクセスがあるので通常は該当しないが、
気になるなら健康監視(healthchecks)や外形監視(UptimeRobot)で稼働も兼ねられる。

---

## まとめ

- クレカ登録さえ済めば、**あとはこのポータルの構築スクリプトがそのまま動く**
- Oracle特有なのは「セキュリティリスト(コンソール)」＋「oracle-firewall.sh(OS内iptables)」の2点だけ
- 移行後は**更新作業ゼロ**。今までの綱渡り・LINE枠の心配からも解放される
- 万一に備え、Discordへの毎日バックアップだけは Oracle でも必ず設定すること
