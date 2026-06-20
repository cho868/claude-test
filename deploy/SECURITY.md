# 🔐 セキュリティ：懸念点・対策・監視

公開した身内ポータル（XServer VPS）について、**どんな攻撃リスクがあるか / どう守るか / どう監視するか**をまとめます。

## まず結論：あなたのPCは攻撃される？

**基本、直接は狙われません。**
- あなたのPC → VPS への SSH は「あなたから外への接続（アウトバウンド）」。PC側のポートを公開しているわけではないので、PCが直接攻撃対象になることはほぼありません。
- 公開されて攻撃にさらされるのは **VPS（サーバー）の方**です。
- PC側で気をつけるのは主に **SSH秘密鍵の管理**：
  - 秘密鍵（`~/.ssh/id_ed25519`）は他人に渡さない・Gitに上げない
  - できれば鍵に**パスフレーズ**を付ける（盗まれても即使われない）
  - 公開鍵（`.pub`）は配ってOK、秘密鍵は絶対NG

---

## VPS の攻撃対象（attack surface）と対策

公開サーバーには世界中から自動スキャン/総当たりが**常時**来ます（これは正常で、対策していれば問題なし）。

| # | リスク | 対策 | 本リポジトリでの状態 |
|---|--------|------|----------------------|
| 1 | **SSH 総当たり**（22番への大量ログイン試行） | 鍵認証のみ＋パスワード認証無効＋root直ログイン無効＋fail2banで自動BAN | `harden-server.sh`(fail2ban)。鍵化・root無効は手動（下記） |
| 2 | **ログイン画面の総当たり**（アプリの /login） | レート制限（1分6回まで） | ✅ 実装済み（`throttle`） |
| 3 | **野良ユーザー登録**（公開URLなので誰でも登録できる） | 招待コードを必須化 | ✅ 実装済み（`REGISTRATION_INVITE_CODE`） |
| 4 | **デバッグ情報の漏洩**（エラー画面にコード/設定が出る） | 本番は `APP_DEBUG=false` | ✅ `.env.production` で false |
| 5 | **XSS**（資料のMarkdownに悪意あるHTML） | 生HTML除去・危険リンク無効 | ✅ commonmark で対策済み |
| 6 | **クリックジャッキング/MIMEスニッフィング等** | セキュリティヘッダ | ✅ `security-headers.conf`（`harden-server.sh`で配置） |
| 7 | **既知の脆弱性の放置** | OSの自動セキュリティ更新 | ✅ `harden-server.sh`(unattended-upgrades) |
| 8 | **秘密情報の流出**（.env, APP_KEY, DB） | .env は Git 除外・Web非公開、DBは public 外 | ✅ gitignore / nginxでdotfile拒否 / SQLiteは`database/` |
| 9 | **通信の盗聴** | HTTPS + HSTS | ✅ Let's Encrypt + HSTSヘッダ |
| 10 | **データ消失**（攻撃/障害/体験終了） | DBの定期バックアップ | ✅ cron例（DEPLOY.md） |

### まだ手動でやるべき重要対策（強く推奨）

```bash
# (1) 鍵ログインを確認してから、パスワード認証と root直ログインを無効化
sudo grep -ri 'permitrootlogin\|passwordauthentication' /etc/ssh/sshd_config /etc/ssh/sshd_config.d/
#   PasswordAuthentication no
#   PermitRootLogin no
sudo systemctl restart ssh

# (2) 堅牢化スクリプト（fail2ban・自動更新・セキュリティヘッダ）
cd /var/www/portal && sudo bash deploy/harden-server.sh
```
> 🛟 SSHを締め出しても XServer の管理コンソール（VNC）から復旧できます。

---

## アクセス状況・攻撃を「調べる」コマンド集

### Webアクセス（nginx）
```bash
# リアルタイムでアクセスを見る
sudo tail -f /var/log/nginx/access.log
# エラーを見る
sudo tail -f /var/log/nginx/error.log
# アクセスの多いIP上位20
sudo awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -rn | head -20
# 怪しいパスへのアクセス（/wp-login, /.env, /phpmyadmin 等を探られる定番）
sudo grep -iE "wp-login|\.env|phpmyadmin|/admin|\.git" /var/log/nginx/access.log | tail -30
```

### SSHへの侵入試行
```bash
# 失敗したログイン試行（総当たりの痕跡）
sudo lastb | head -30
# 認証ログ
sudo journalctl -u ssh --since "1 day ago" | grep -i "failed\|invalid" | tail -30
# fail2ban が今BANしているIP
sudo fail2ban-client status sshd
```

### 開いているポートの確認（意図しない公開がないか）
```bash
sudo ss -tlnp        # 22/80/443 以外がLISTENしていないか
sudo ufw status      # ファイアウォール状態
```

### 死活・継続監視
- `healthchecks.io`：落ちたら通知（→ `NOTIFY.md`）
- 毎日のDiscord通知：稼働・各種期限（→ `NOTIFY.md`）
- （任意）**GoAccess** でアクセスを可視化:
  ```bash
  sudo apt-get install -y goaccess
  sudo goaccess /var/log/nginx/access.log -c
  ```

---

## 「攻撃されてる？」の見極めかた

- **失敗ログイン/不審アクセスが大量にある** → 正常です。公開サーバーには常時自動攻撃が来ます。fail2ban と鍵認証で**防げていればOK**。
- **気にすべきサイン**:
  - `lastb` に**成功**した見覚えのないログインがある → 即対応（鍵更新・パスワード無効化・調査）
  - CPU/通信が常時高い（`top` / `vnstat`）→ 不正利用の可能性
  - `sudo ss -tlnp` に**身に覚えのないポート**がLISTENしている
- 困ったら、ログの該当行を貼ってください。一緒に判断します。

---

## まとめ：今すぐやる優先順位
1. **SSH鍵のみ＋root/パスワード無効化**（最重要）
2. `sudo bash deploy/harden-server.sh`（fail2ban・自動更新・ヘッダ）
3. **招待コードを設定**（`REGISTRATION_INVITE_CODE`）して野良登録を防ぐ
4. **DBバックアップのcron**（DEPLOY.md）
5. 通知・死活監視（NOTIFY.md）
