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
- ストレージ方針は下の「ストレージの選び方」を参照（**この規模ならまずSDで十分**）

### ストレージの方針（結論: 付属SD + 対策 で始める）

**SDの唯一の弱点は「書き込み回数の寿命」**。容量や品質の問題ではない。
そして身内数人のポータル(SQLite)は**書き込みが極小**なので、以下2つの対策を入れれば
**付属の32GB SDで問題なく常用できる**。追加出費も電力問題もゼロ。

1. **log2ram**（ログをRAMに置きSDへの書き込みを1日1回に削減）→ 後述の運用メモ
2. **backup-to-discord.sh を毎日**（SDが飛んでもDBは戻せる最大の保険）

将来もっと安心度を上げたくなった場合の比較:

| 方法 | 費用 | 電力 | 評価 |
|---|---|---|---|
| **付属SD + 上記2対策** | 0円 | 問題なし | ⭐**まずこれ。これで運用できる** |
| **高耐久microSD**（High/MAX Endurance） | ¥2,000前後 | 問題なし | ⭕SDのまま寿命を伸ばす正攻法。ドラレコ/監視カメラ用として実売されている実在カテゴリ（SanDisk High Endurance / Samsung PRO Endurance 等）。**次の一手として最も素直** |
| USB-SSD | ¥3,000〜 | ⚠️対策必要 | 性能は最良だが、Pi4のUSB給電は弱く2.5"SATAは起動スパイクで電圧不足になりがち。使うなら**セルフパワー(AC付き)USBハブ**経由が確実 |
| USBメモリ | 安い | 問題なし | ❌**非推奨**。SDと同じフラッシュだがコントローラが簡素で**耐久性はSD以下**。常時書き込みのOS用途向けに作られていない＝SDの代わりにする意味がない |
| USB外付けHDD | — | ⚠️最も食う | ❌**OS用途は非推奨**。回転部品ゆえ電力消費最大・発熱・振動・衝撃に弱い・低速。※**バックアップの保存先としては優秀** |

> 電圧不足の確認: `vcgencmd get_throttled` が `0x0` なら正常（0以外は電圧/温度の問題履歴あり）。
> 発熱対策はストレージではなく本体側の話。**スターターキット付属のヒートシンク+ファンを付ければ常用OK**
> （`vcgencmd measure_temp` で60℃台なら健全、80℃超で性能制限がかかる）。

---

## 1. OSを焼く（Windows PCで）

1. **Raspberry Pi Imager** をダウンロードしてインストール: https://www.raspberrypi.com/software/
2. microSDをPCのカードリーダーに挿して Imager を起動:
   - **デバイスを選択**: `Raspberry Pi 4`
   - **OSを選択**: `Other general-purpose OS` → `Ubuntu` → **`Ubuntu Server 24.04 LTS (64-bit)`**
     （※Desktop版ではなく **Server** を選ぶ。GUI無しで軽い）
   - **ストレージを選択**: microSD（容量表示で間違いないか必ず確認。**選択を間違えると別ドライブを消す**）
3. 「次へ」→ **「設定を編集する」** を押して以下を設定（ここが重要。最初からSSHで入れる）:
   - ✅ **ホスト名**: `portal`（→ 後で `portal.local` で繋げる）
   - ✅ **ユーザー名とパスワードを設定**: 例 ユーザー名 `pi` / 好きなパスワード（後でSSHに使う）
   - ⬜ **Wi-Fi設定はスキップでOK**（有線LANで繋ぐ場合。他の回線に影響を出したくないなら有線が正解）
   - ✅ **ロケール設定**: タイムゾーン `Asia/Tokyo` / キーボード `jp`
   - 「サービス」タブ → ✅ **SSHを有効化** →「パスワード認証を使う」
4. 保存 → 「はい」で書き込み開始 → 完了したらSDを取り出してPiに挿す

### 組み立ての参考リンク（初めての人向け）

- 動画: [Raspberry Pi 4 キット 開封・組み立て・OSインストール（YouTube）](https://www.youtube.com/watch?v=Iwmcp80PLQE)
- 写真解説: [ヒートシンク＆ファン付きラズパイ4の組み立て（ファンのピン位置写真あり）](https://note.com/bft_nagoya/n/nb04b9d1ce970) /
  [TRASKIT系キットのケース組み立て](https://wakky.tech/raspberry-pi-1-traskit-starter-kit-install/) /
  [DevelopersIOの組み立て記録](https://dev.classmethod.jp/articles/raspberry-pi-4-assembly/)
- 💡 キットの箱のブランド名(LABISTS/Vemico/TRASKIT等)でYouTube検索すると**純正の組み立て動画**が見つかることが多い
- ⚠️ 失敗しうるのは実質 **ファンの配線だけ**（赤=5V・黒=GND。説明書の図と写真記事で確認してから電源ON）。
  ヒートシンクは大きいチップに貼るだけ・多少ズレても問題なし。作業前に金属に触れて静電気を逃がす。

## 2. 組み立て & 初回起動

1. **ヒートシンク**をCPU等のチップに貼る（発熱対策。必ず付ける）
2. **ファン**をGPIOピンに接続（赤=5V / 黒=GND。キットの説明書に従う）
3. ケースに収める
4. **LANケーブルを接続**（有線。ルーターのLANポートへ）
5. SDを挿して**電源ON**（Type-C）。初回は起動に1〜2分かかる
6. WindowsのPowerShell / コマンドプロンプトから接続:
   ```powershell
   ssh pi@portal.local
   ```
   - 初回は `fingerprint...(yes/no)` と聞かれるので `yes`
   - `portal.local` で引けない場合は**ルーターの管理画面でPiのIPを確認**して
     `ssh pi@192.168.x.x` で接続（有線なら「接続機器一覧」に `portal` が出る）
7. 繋がったら更新とヘルスチェック:
   ```bash
   sudo apt-get update && sudo apt-get upgrade -y
   vcgencmd measure_temp     # 温度（60℃台なら健全）
   vcgencmd get_throttled    # 0x0 なら電源も正常
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

## 8. アクセスログと異常検知

「身内しか使わないのに大量アクセスが来たら異常」というシンプルで強い検知を入れる。

```bash
# 1) nginxのログに実IPを記録（Funnel経由は素のままだと全部127.0.0.1になるため）
sudo bash /var/www/portal/deploy/setup-access-log.sh

# 2) cron 2行を追加
sudo crontab -e
*/10 * * * * /var/www/portal/deploy/access-monitor.sh >> /var/log/portal-access.log 2>&1
5 9 * * *    /var/www/portal/deploy/access-monitor.sh --daily >> /var/log/portal-access.log 2>&1
```

- **10分ごとの検知**: リクエスト数/ユニークIP数/404数/攻撃風パス(wp-login・.env等)が
  しきい値（`/etc/portal-notify.conf` の `ACCESS_*` で調整）を超えたら Discord に @everyone。
  IP/パスのTOP5つき。初回実行はベースライン記録のみで誤発報しない。/health は除外。
- **毎朝9:05のダイジェスト**: 昨日の合計・訪問IP数・404・よく見られたページ
- 生ログの場所: `/var/log/nginx/access.log`（実IPは行末の `xff="..."`）
  ```bash
  sudo tail -f /var/log/nginx/access.log      # リアルタイムで眺める
  ```

## 運用メモ

- **自動起動**: nginx/php-fpm/tailscale は systemd で自動起動。停電後も電源が戻れば自動復帰。
  Funnelは `--bg` で常駐設定として保存される。
- **停電/再起動対策**: UPS(無停電電源)まではなくても、`backup-to-discord.sh` があれば最悪データは戻せる。
### 🍓 SDカードを長持ちさせる（SD運用なら推奨・費用0円）

書き込み回数を減らせばSDの寿命は大幅に伸びる。ログをRAMに逃がすのが効果的:
```bash
# log2ram: /var/log をRAM上に置き、1日1回だけSDに書き戻す
sudo apt-get install -y git
git clone https://github.com/azlux/log2ram.git /tmp/log2ram
cd /tmp/log2ram && sudo ./install.sh
sudo reboot

# スワップを切る（SDへの書き込みを減らす。RAMに余裕があるPi4なら可）
sudo systemctl disable --now dphys-swapfile 2>/dev/null || sudo swapoff -a

# 電圧・温度に問題が出ていないか確認
vcgencmd get_throttled     # 0x0 なら正常
vcgencmd measure_temp
```
加えて **`backup-to-discord.sh` の毎日実行が最大の保険**（SDが飛んでもDBは戻せる）。

- **SD→SSD移行**: 安定運用するなら USB-SSD にOSを焼き直して起動元を変更（`rpi-clone` 等でも移せる）。
  その際は上記の電力対策（セルフパワーUSBハブ推奨）を忘れずに。
- **熱**: スターターキットのファン+ヒートシンクを付けていれば常用で問題なし。`vcgencmd measure_temp` で温度確認可。
- **セキュリティ**: 公開はTailscale経由のみ・SSHは家庭内LANのみなので露出は小さい。`deploy/harden-server.sh` も任意で。

## まとめ

- MAP-E回線でも **Tailscale Funnel** で公開できる（ポート開放・固定IP・ドメイン購入すべて不要）
- 構築は既存の `setup-server.sh`/`deploy-app.sh` がそのまま使える
- **更新作業ゼロ・カード不要・恒久無料**。唯一の注意はSDカード寿命→SSD化 or バックアップ徹底
