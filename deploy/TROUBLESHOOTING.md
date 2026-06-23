# 🧯 トラブル対応ナレッジ（runbook）

「サイトが見えない」「エラーが出た」ときに、**自分で原因を切り分けて直す**ための手順集。
過去に実際に起きたインシデントを「症状 → 診断 → 原因 → 直し方 → 再発防止」の形で残していく。

> 鉄則（これだけ覚える）
> 1. **設定をいじったら必ず `sudo nginx -t`**（テスト）してから `reload`。失敗したら反映しない。
> 2. サービスが落ちたら、まず **状態 → ログ** の順で見る（推測で触らない）。
> 3. 直したら **どう直したかをこのファイルに追記**する。

---

## 0. まず最初にやる「サイトが見えない」三点確認

ブラウザでサイトが見えないとき、SSHで入って上から順に:

```bash
# ① Webサーバ(nginx)は動いている? 80/443で待ち受けている?
sudo systemctl status nginx --no-pager
sudo ss -tlnp | grep -E ':80|:443'      # 何も出ない＝待ち受けなし＝nginx停止

# ② nginxの設定は正しい?（落ちてる時はだいたいここが赤）
sudo nginx -t

# ③ アプリ(PHP)は動いている?
sudo systemctl status php8.5-fpm --no-pager
curl -I http://localhost                # 200/302=正常 / 502=PHP側 / 接続不可=nginx側
```

この3つの結果で、原因がどの層か（**ネットワーク / nginx / PHP / アプリ**）がほぼ分かる。

| `curl -I http://localhost` の結果 | だいたいの原因 | 見るケース |
|---|---|---|
| `Could not connect` / 接続拒否 | nginx停止 or ポート閉 | ケースA, F |
| `502 Bad Gateway` | php-fpm停止 or ソケット不一致 | ケースB |
| `500` / 真っ白 | Laravel(アプリ)のエラー | ケースC |
| `200` / `302` | サーバは正常（DNS/ファイアウォール/SSLを疑う） | ケースE, F |

---

## ケースA. nginx が設定エラーで起動失敗（★実際に発生）

### 症状
- ブラウザ: **`ERR_CONNECTION_REFUSED`**
- SSHは入れる、VPSの期限も切れていない（＝サーバは生きている）
- `sudo systemctl status nginx` が **`Active: failed`**
- `sudo nginx -t` が **`[emerg] ... directive is duplicate ...`** など

### 実例（2026-06-23 に発生）
```
[emerg] "server_tokens" directive is duplicate in /etc/nginx/conf.d/security-headers.conf:19
nginx: configuration file /etc/nginx/nginx.conf test failed
```

### 原因
`/etc/nginx/conf.d/security-headers.conf` に `server_tokens off;` を書いていたが、
**Ubuntu標準の `/etc/nginx/nginx.conf:22` に既に `server_tokens build;` があり**、
同じディレクティブが2回 → 文法エラー。
**やっかいな点**: 稼働中のnginxは設定を読み直さないので**しばらく無症状**。
自動更新(unattended-upgrades)やcertbotで**nginxが再起動された瞬間に初めてエラーが露呈**して停止した。

### 直し方
```bash
# 1. エラーメッセージに出ているファイル:行 を見る（今回は security-headers.conf）
sudo nginx -t

# 2. 重複しているディレクティブが他のどこにあるか探す
grep -rn server_tokens /etc/nginx/      # → /etc/nginx/nginx.conf にもあった

# 3. 後から足した方（conf.d 側）の重複行を削除
sudo sed -i '/server_tokens/d' /etc/nginx/conf.d/security-headers.conf
#   ↑ ピンポイントで消す。general には「conf.d側の重複を消す」が安全

# 4. テストして起動
sudo nginx -t && sudo systemctl start nginx
curl -I http://localhost                # 302/200 が返れば復活
```

### 一般化（同じパターンの直し方）
- `nginx -t` の **`duplicate` / `unknown directive` / `unexpected }`** 系は**設定の文法ミス**。
- エラー文に **必ず「ファイル名:行番号」** が出る → そこを直すだけ。
- 「**後から足した conf.d 側を消す/直す**」のが基本（標準のnginx.confは触らない）。
- 直したら必ず `sudo nginx -t`（successful を確認）→ `systemctl start/reload nginx`。

### 再発防止（実施済み）
- `deploy/security-headers.conf` から `server_tokens` を削除。
- `deploy/harden-server.sh` は、設定配置後に `nginx -t` が失敗したら**自動でロールバック**するよう改修。

---

## ケースB. 502 Bad Gateway

### 症状 / 原因
nginx は動いているが、**php-fpm（PHP）が止まっている**か、**ソケットのパスが食い違っている**。

### 直し方
```bash
sudo systemctl status php8.5-fpm --no-pager
sudo systemctl restart php8.5-fpm

# ソケットのパスが nginx 設定と一致しているか
ls /run/php/                                   # php8.5-fpm.sock があるか
grep fastcgi_pass /etc/nginx/sites-available/portal   # 上と一致しているか
sudo tail -30 /var/log/nginx/error.log
```
- バージョン違い（例: 設定は8.3を指すが入っているのは8.5）でソケット不一致 → nginx設定の `fastcgi_pass` を実際のバージョンに合わせて `sudo nginx -t && sudo systemctl reload nginx`。

---

## ケースC. 500 エラー / 真っ白（アプリのエラー）

### 症状 / 原因
nginx・PHPは動いていて、**Laravel（アプリ）側でエラー**。設定キャッシュ・権限・migration 漏れが多い。

### 直し方
```bash
# まずアプリのログを見る（原因がそのまま書いてある）
sudo tail -50 /var/www/portal/storage/logs/laravel.log

# よくある対処
cd /var/www/portal
sudo chown -R www-data:www-data storage database bootstrap/cache   # 書き込み権限
sudo -u www-data php artisan migrate --force                        # 未適用のmigration
sudo -u www-data php artisan config:clear && sudo -u www-data php artisan config:cache
```
- 本番は `APP_DEBUG=false` なので画面には詳細が出ない。**必ず laravel.log を見る**。

---

## ケースD. メモリ不足 / OOM（プロセスが突然死）

### 症状 / 原因
2GB機でメモリが逼迫し、**OOM Killer が nginx や php-fpm を強制終了**することがある。

### 診断
```bash
dmesg | grep -iE "killed process|out of memory" | tail   # OOMの痕跡
free -h
ps aux --sort=-%mem | head -10
```

### 対処
```bash
# 落ちていたら起動
sudo systemctl start nginx php8.5-fpm
# php-fpm のワーカーを絞る等は今後 deploy/tune-php-fpm.sh で対応予定
```
> 注意: 設定エラー（ケースA）で落ちている場合、`Restart=always` 等の自動再起動は**無力**
> （再起動しても設定が不正なので失敗し続ける）。まず `nginx -t` で切り分ける。

---

## ケースE. HTTPS にならない / SSL証明書切れ

```bash
sudo certbot certificates                 # 残り日数・状態
sudo certbot renew --dry-run              # 更新テスト
systemctl list-timers | grep certbot      # 自動更新タイマーが生きているか
# 期限切れなら
sudo certbot renew && sudo systemctl reload nginx
```

---

## ケースF. 外から繋がらない（でも localhost では繋がる）

`curl -I http://localhost` は 200 なのに外部から見えない → **ファイアウォール**。

```bash
sudo ufw status                           # OS側: 80/443 が ALLOW か
```
- それでもダメなら **XServer VPS の「パケットフィルター設定」(Web管理画面)** で 80/443 が許可されているか確認（OSの外側にもう一段ある）。

---

## ケースG. サーバ自体が停止（体験認証切れ / VPS停止）

- SSHも入れない・pingも返らない → VPSごと停止の可能性。
- XServer の**体験再認証（2GB=4日 / 4GB=2日）忘れ**や体験期間終了が原因のことが多い。
- → XServer 管理画面で状態確認・再認証。データは `database.sqlite` のバックアップから復旧（DEPLOY.md「再構築」）。

---

## 付録: 監視で「落ちた」を自動で知る

今回のように「サーバは生きてるが nginx だけ停止」は、サーバ発の死活監視(healthchecks)では気づけない。
**外形監視(UptimeRobot)で `/health` を外から叩く**設定を入れておくと、落ちた瞬間に通知が来る。→ `deploy/NOTIFY.md` の「🌐外形監視」。

---

## インシデント記録（時系列メモ）

| 日付 | 症状 | 原因 | 対応 | 再発防止 |
|------|------|------|------|----------|
| 2026-06-23 | サイトが `ERR_CONNECTION_REFUSED`（SSHは可） | `security-headers.conf` の `server_tokens` が `nginx.conf` と重複し、自動更新でnginx再起動時に起動失敗 | 重複行を削除し `nginx -t` → `start` | confから`server_tokens`除去 / harden-server.shに自動ロールバック追加 / 外形監視を案内 |
