# 🤝 Etherpad 構築セッションへの引き継ぎ書（既存環境の前提）

このサーバーには **すでに本番稼働中の Web サイト（身内ポータル）があります**。
Etherpad はその**同じVPS・同じドメイン・同じnginx**に**相乗り**する形で構築します。
**既存サイトを絶対に止めないこと**が最優先です（過去に nginx 設定ミスで全停止した事故あり。後述）。

---

## 1. 既存の地盤（いま動いているもの）

| 項目 | 値 |
|------|----|
| VPS | **XServer VPS 無料体験 / 2GB RAM・2コア・NVMe 30GB** |
| OS | **Ubuntu 26.04 LTS** |
| ドメイン | **madgear.sytes.net**（no-ip 無料・固定IP・30日ごと確認が必要） |
| HTTPS | **Let's Encrypt（certbot 管理・自動更新）**。`madgear.sytes.net` の証明書は **既に存在** |
| Web サーバ | **nginx**（稼働中）。設定: `/etc/nginx/sites-available/portal`（`server_name madgear.sytes.net`、443 はcertbotが追記済み） |
| 既存アプリ | **Laravel 13 + PHP 8.5-fpm** のポータル。場所 `/var/www/portal`。`https://madgear.sytes.net/` で公開中 |
| DB | ポータルは SQLite（`/var/www/portal/database/database.sqlite`） |
| ヘルスチェック | `https://madgear.sytes.net/health` が nginx から `ok` を返す（PHP非依存） |

> 既存ポータルのリポジトリ: `github.com/cho868/claude-test`（`deploy/` に nginx設定・トラブル対応 runbook あり）

---

## 2. やりたいこと（Etherpad の置き場所と公開URL）

- **コードの置き場所**: `/var/common/etherpad`（`/var/www` は使わない＝そこはポータル専用）
- **公開URL**: **`https://madgear.sytes.net/etherpad`**（サブドメインではなく**サブパス**）
  - 理由: no-ip 無料では別サブドメイン（pad.madgear.sytes.net 等）が取りにくいため、**既存ドメインのサブパス相乗り**にする
- **構成**: Etherpad は **127.0.0.1 のローカルポート（既定 9001）** で動かし、**既存nginxがリバースプロキシ**して `/etherpad` に出す
  ```
  ブラウザ ──https://madgear.sytes.net/etherpad──> [既存nginx:443] ──proxy──> 127.0.0.1:9001 (Etherpad)
  ```

---

## 3. 🚫 触ってはいけない / ⚠️ 守ること（最重要）

1. **既存の nginx server ブロックを壊さない**。
   - Etherpad 用には、`/etc/nginx/sites-available/portal` の **既存 `server {}`（443）の中に `location /etherpad/ { ... }` を1つ追加**するだけ。
   - **新しい server ブロックを作らない**（`server_name madgear.sytes.net` が重複すると競合）。
   - **`server_tokens` など http レベルのディレクティブを足さない**（後述の事故原因）。
2. **設定を変えたら必ず `sudo nginx -t` → 成功を確認してから `sudo systemctl reload nginx`**。
   テストが失敗したら**絶対に reload しない**（壊れた設定を残すと、自動更新でnginxが再起動した瞬間に全停止する）。
3. **新しい外部ポートを開けない**。Etherpad は **127.0.0.1 にバインド**し、外部公開は nginx 経由のみ。
   （XServerのパケットフィルターと ufw は 80/443/22 のみ許可。Etherpadのポートは外に出さない）
4. **メモリに注意（2GBで既に約70%使用）**。
   - Etherpad の DB は **PostgreSQL/MySQL ではなく軽量な dirtyDB(SQLite的) か SQLite** を使う。
   - Node のメモリを絞る。**OOM で nginx/php-fpm が巻き添えで死ぬと既存サイトも落ちる**。
   - 起動後に `free -h` / `ps aux --sort=-%mem | head` で消費を確認すること。
5. **root 常用で動かさない**。Etherpad 専用ユーザー＋ **systemd サービス**で常駐させる。
6. **証明書は新規取得しない**。`madgear.sytes.net` の証明書は既存。サブパスなので**同じ証明書でHTTPS化済み**。certbot は触らない。

---

## 4. 連携ポイント：nginx に足す `location`（雛形）

> ⚠️ この追加作業は**既存ポータルの設定ファイル**に対して行うため、
> 既存側（このポータルのセッション/オーナー）と**調整**してから入れること。
> Etherpad セッションは「この location ブロックを用意したので、既存の `server{}` 内に追記してほしい」と**雛形を渡す**形が安全。

```nginx
# /etc/nginx/sites-available/portal の、443 の server { } の中に追加
location /etherpad/ {
    proxy_pass http://127.0.0.1:9001/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;        # WebSocket（Etherpadの同時編集に必須）
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_buffering off;
}
```
追記後:
```bash
sudo nginx -t && sudo systemctl reload nginx
```

### サブパス運用の注意（Etherpad 側の設定が要る）
Etherpad は本来「ホストのルート（/）」で動く前提のアプリで、**`/etherpad` のようなサブパス配下はクセがある**。
- Etherpad の `settings.json` で**ベースパス/リバースプロキシ設定**を行い、`/etherpad` 配下でも
  静的アセットやWebSocketのURLが正しく解決されるようにすること。
- 動かしてみて崩れる場合のフォールバック: ①別ポートでルート運用にして直リンク、
  ②（もし将来サブドメインを取れたら）`pad.madgear.sytes.net` に切替。
- ここは **Etherpad セッション側の責務**。サブパスで本当に動くか必ず検証すること。

---

## 5. 過去の事故（同じ轍を踏まないために）

**2026-06-23、ポータルが全停止した**。原因は nginx の設定ミス：
`/etc/nginx/conf.d/security-headers.conf` に `server_tokens off;` を置いたが、
**Ubuntu標準の `/etc/nginx/nginx.conf` に既に `server_tokens build;` があり重複** → `nginx -t` が
`[emerg] "server_tokens" directive is duplicate` で失敗。
稼働中は無症状だったが、**自動更新で nginx が再起動した瞬間に起動失敗 → サイト全停止**。

教訓（Etherpad側も厳守）:
- **同じディレクティブを2回書かない**。`grep -rn 〇〇 /etc/nginx/` で既存を確認してから足す。
- **設定変更後は必ず `nginx -t`**。失敗を残さない。
- 詳しくはポータルリポジトリの `deploy/TROUBLESHOOTING.md` 参照。

---

## 6. Etherpad セッションへの依頼（まとめ）

1. `/var/common/etherpad` に Etherpad を構築（**専用ユーザー + systemd**、**127.0.0.1:9001**、**軽量DB**）。
2. `/etherpad` **サブパス**で正しく動くよう Etherpad 側を設定（WebSocket/アセットのパス）。
3. nginx には**上記 location 雛形を用意するだけ**にして、**既存 `server{}` への追記は調整のうえ慎重に**。
   `nginx -t` 成功を確認してから reload。
4. **メモリ消費を必ず確認**（2GB・既存サイト同居。OOMは共倒れ）。
5. 外部ポートは開けない／証明書は新規取得しない（既存のHTTPSに相乗り）。
6. 完成したら公開URLは **`https://madgear.sytes.net/etherpad`**。これをポータルの「🔗リンク集」に登録する。

> 困ったら「既存ポータル側の nginx 設定・証明書・ドメイン」はこの引き継ぎ書が前提。
> 不明点は madgear.sytes.net の構成（このサーバー）を確認しながら進めること。
