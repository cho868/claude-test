# 🔔 体験サーバーを止めない・忘れないための通知設定

XServer VPS の無料体験は **API が無い**ため、体験終了日や認証期限をプログラムから取得できません。
（2026/4 開始の「XServer API」も共有サーバー/ビジネス向けで **VPS非対応**）。
そこで「**自前で毎日リマインド + 死活監視**」で取りこぼしを防ぎます。

> 📌 LINE Notify は 2025/3/31 に終了したため、通知は **Discord Webhook** を使います（無料・最短）。
> どうしても LINE が良い場合は LINE Messaging API（Bot作成・トークン発行が必要）でも実装可能です。

## 仕組み

| 仕組み | 役割 | 失敗を防ぐ対象 |
|--------|------|----------------|
| 毎日の Discord 通知 | 「稼働中✅ / 体験残りN日 / 認証リマインド / no-ip確認 / SSL残り日数」を毎朝投稿 | 体験終了・認証忘れ・no-ip失効の**事前**防止 |
| healthchecks.io（死活監視） | サーバーが毎日 ping。**届かないと逆に通知が来る** | 停止・消滅の**事後**検知（自動） |

この2つで「気づいたら消えてた」を防ぎます。毎日の通知は **3つの期限（① XServer体験の再認証 ② no-ipの30日確認 ③ Let's Encryptの証明書）** をまとめて面倒見ます。

---

## 1. Discord の Webhook を作る

1. 通知を受け取りたい Discord サーバーのチャンネルを用意
2. チャンネルの **設定（⚙）→ 連携サービス → ウェブフック → 新しいウェブフック**
3. 名前を付けて **「ウェブフックURLをコピー」**（`https://discord.com/api/webhooks/...`）

## 2. healthchecks.io を作る（死活監視・任意だが推奨）

1. https://healthchecks.io/ に無料登録
2. **Add Check** → 名前を付ける
3. **Period（期待間隔）= 1 day**、**Grace（猶予）= 1 day** などに設定
   （= 1日 ping が来なかったら「落ちた」と判定）
4. 表示される **Ping URL**（`https://hc-ping.com/xxxx`）をコピー
5. Integrations で Discord/メール等の通知先を登録（落ちたらここへ飛ぶ）

## 3. サーバーに設定を置く

```bash
cd /var/www/portal
sudo cp deploy/portal-notify.conf.example /etc/portal-notify.conf
sudo vi /etc/portal-notify.conf
```
記入する項目:
- `DISCORD_WEBHOOK` … 手順1のURL
- `DOMAIN` … 公開ドメイン（例 `madgear.sytes.net`）。SSL証明書の残り日数チェックに使用
- `SITE_URL` … 公開URL（例 `https://madgear.sytes.net`）
- `TRIAL_END` … 体験終了日（**分かったら** `YYYY-MM-DD`。最初は空でOK）
- `REAUTH_INTERVAL_DAYS` … 2GB=`4` / 4GB=`2`
- `NOIP_LAST_CONFIRMED` … no-ipを最後に確認した日（`YYYY-MM-DD`）。確認のたびに更新すると残り日数を計算
- `HEALTHCHECK_URL` … 手順2のPing URL

## 4. 動作テスト

```bash
sudo bash /var/www/portal/deploy/notify.sh
```
Discord にメッセージが届けば成功。

## 5. cron で毎日自動実行 ＋ 緊急時はしつこく鳴らす

```bash
sudo crontab -e
```
最下部に追記:
```cron
# 毎朝 9:00 に通常通知
0 9 * * * /var/www/portal/deploy/notify.sh >> /var/log/portal-notify.log 2>&1
# 毎時0分: 緊急事項（期限が今日/明日・SSL切れ・no-ip間近・メモリ逼迫）がある時だけ再送
0 * * * * /var/www/portal/deploy/notify.sh --urgent-only >> /var/log/portal-notify.log 2>&1
```

`--urgent-only` は**緊急が無ければ何も送らない**ので、毎時cronに入れても普段は静か。
期限当日などは **1時間ごとに @everyone 付きで鳴り続けます**（対応するまで）。

---

## 🚨 XServer の期限メールを Gmail で検知する（最重要・強アラート）

XServer は期限が近づくと
**「【XServer VPS】■重要■無料サーバーのご利用期限と更新に関するご案内 (サーバー名)」**
というメールを送ってくる。**このメールの到着日＝更新期限日**。ここを取りこぼすとVPSごと消える。

`deploy/gmail-xserver-alert.gs`（Google Apps Script）が、このメールを **Gmail側で30分ごとに監視**し、
見つけたら **本文の抜粋＋期限日＋更新ページへのリンク**を Discord / LINE に **繰り返し送る**。

**VPS上ではなく Google のサーバーで動く**のがポイント（VPSが死んでも監視は生きている。メール本文も読める）。

### セットアップ（5分・無料）
1. https://script.google.com → 「新しいプロジェクト」→ `deploy/gmail-xserver-alert.gs` の中身を貼り付け
2. ⚙「プロジェクトの設定」→「スクリプト プロパティ」に追加:
   - `DISCORD_WEBHOOK` = いつものWebhook URL（必須）
   - `LINE_TOKEN` = LINEのチャネルアクセストークン（任意）
   - `RENEW_URL` = XServerの更新ページURL（任意。省略時はログインページ）
3. 関数 `setup` を選んで ▶実行（初回はGmailへのアクセス許可を承認）→ 30分ごとの監視が始まる
4. 関数 `sendTest` を ▶実行 → Discordにテスト通知が届けば完成

### 止め方（更新対応が終わったら）
Gmail でそのメールに **「VPS対応済み」ラベル**を付ける（スマホのGmailアプリからでも可）。
付けるまで30分ごとに鳴り続けます。5日経過したメールは自動で対象外になります。

---

## 体験終了日が「前日しか分からない/更新できない」問題について

- XServer 側に終了日を取りに行く API はありません。
- 対策: **健康監視（healthchecks.io）で「止まった瞬間」を必ず検知**できるので、最悪見逃しても気づけます。
- さらに、終了日が判明したら `TRIAL_END` に入れておけば、**前日に🔴強調通知**（`@everyone` 付き）が飛び、毎時の `--urgent-only` でも鳴り続けます。
- 「更新は前日のみ」という仕様にも、この前日アラートが噛み合います。

## 自動更新はできないの？

**技術的には可能だが、おすすめしない。** 理由:
- 更新には XServer 会員パネルへの**ログイン操作の自動化**（ID/パスワードの平文保存 + ブラウザ自動操縦）が必要
- CAPTCHA や画面変更で**予告なく壊れる**。壊れたことに気づかず「自動更新してるから大丈夫」と思い込むのが最悪パターン（手動より危険）
- 規約面でも自動化ツールによるパネル操作はグレー
- 対して「メール検知→スマホに強アラート→リンクをタップして更新」なら**人間の作業は1タップ**で、壊れない

根本的に更新作業自体を無くしたいなら、**Oracle Cloud Always Free（恒久無料・期限更新なし）**への移行が正攻法。`deploy/` のスクリプトはほぼそのまま使えます。

## Let's Encrypt の自動更新について

証明書の更新は **certbot が自動でやってくれます**（`certbot.timer` が標準で有効）。確認:
```bash
systemctl list-timers | grep certbot      # タイマーが動いているか
sudo certbot renew --dry-run               # 更新テスト
```
no-ip の無料ホスト名だけは **約30日ごとに確認メールのクリック**が必要なので、これも上記 Discord 通知の習慣でカバーできます（毎日見る場所に出る）。

---

## 付録：各種URL・トークンの「取り方」（詳細マニュアル）

通知に必要な `DISCORD_WEBHOOK` / `HEALTHCHECK_URL` /（任意）`LINE_TOKEN` の取得手順です。
`DOMAIN` は自分の公開ドメイン（例 `madgear.sytes.net`）をそのまま書けばOK。

### A. Discord Webhook URL の取り方
1. PC版/ブラウザ版の Discord で、通知を受けたい**サーバー**と**チャンネル**を用意（無ければ「+」でサーバー作成）
2. そのチャンネルの右の **⚙（チャンネルの編集）** をクリック
3. 左メニュー **「連携サービス（Integrations）」** → **「ウェブフック（Webhooks）」**
4. **「新しいウェブフック」** → 名前/アイコンを設定（例: ポータル通知）
5. **「ウェブフックURLをコピー」** → これが `DISCORD_WEBHOOK`
   （`https://discord.com/api/webhooks/数字/英数字` の形）
6. `/etc/portal-notify.conf` の `DISCORD_WEBHOOK="..."` に貼る
   - 動作テスト: `sudo bash /var/www/portal/deploy/notify.sh` → チャンネルに投稿されれば成功

> 📱 スマホに通知を出したいだけなら、これが一番簡単。Discordアプリの通知をONにしておけばOK。

### B. healthchecks.io（死活監視）の Ping URL の取り方
1. https://healthchecks.io/ に無料登録（Googleログイン可）
2. **「Add Check」** で監視枠を作成 → 名前（例: portal-alive）
3. **Schedule**: 「Simple」/ **Period = 1 day** / **Grace = 1 day**
   （= 1日pingが来なかったら「落ちた」と判定して通知）
4. 表示される **Ping URL**（`https://hc-ping.com/英数字`）をコピー → `HEALTHCHECK_URL`
5. 落ちた時の通知先を増やす: **Integrations** タブ → **Discord** や **Email** を追加
   （Discord連携を入れておくと、サーバーが死んだ瞬間に同じチャンネルへ警告が飛ぶ）
6. `/etc/portal-notify.conf` の `HEALTHCHECK_URL="..."` に貼る

> 仕組み: 毎日の `notify.sh` が最後にこのURLへ ping。**pingが途絶える＝サーバー停止**を healthchecks 側が検知して通知してくれる（＝こちらが気づかなくても向こうから教えてくれる）。

### C. LINE 通知の出し方（任意・Messaging API）
> ⚠️ 旧 **LINE Notify は 2025/3 で終了**。今は **Messaging API** を使います（少し手順多め）。Discordで足りるなら不要。

1. **LINE Developers** にログイン: https://developers.line.biz/console/
2. **プロバイダー**を作成（例: 身内ポータル）
3. **「Messaging API」チャネル**を新規作成（チャネル名・アイコン等を入力）
4. 作成後、**「Messaging API設定」タブ** → 下部の **「チャネルアクセストークン（長期）」** を **発行** → コピー
   → これが `LINE_TOKEN`
5. 同じ画面の **QRコード**から、通知を受け取りたい人が**Botを友だち追加**（追加した全員に届く＝broadcast）
6. `/etc/portal-notify.conf` の `LINE_TOKEN="..."` に貼る
   - `notify.sh` は `LINE_TOKEN` があれば Discord と**両方**に送ります（片方だけでもOK）
   - 応答メッセージ等の自動返信が邪魔なら、4の画面で「応答メッセージ」をオフに

> まとめ: **手軽さは Discord ＞ LINE**。まず Discord で運用し、LINEも欲しくなったら C を足す、で十分です。

---

## 🌐 外形監視（外から「ページが生きているか」を見る）

> ⚠️ 重要な落とし穴: 上の **healthchecks.io（死活監視）はサーバーから ping を送る方式**なので、
> **サーバーは生きているが nginx だけ落ちている**ケース（＝サイトは `ERR_CONNECTION_REFUSED`、でも cron は動くので ping は届く）を**検知できません**。
> 「ページが外から見えるか」は、**外部からURLを叩く"外形監視"**で見る必要があります。

### なぜ「同じサーバー内の別ヘルスページ」では不十分か
nginx 自体が落ちると、同じサーバーに置いた `/var/www/health` のような別ページも**一緒に落ちます**（同じ nginx が配信するため）。
→ 「外から生死を判定する」には、**別の場所（外部サービス）からURLを叩く**のが正解です。

### おすすめ: UptimeRobot（無料・外形監視 + ステータスページ）
1. https://uptimerobot.com/ に無料登録
2. **Add New Monitor** → Type: **HTTP(s)** → URL に公開URL（例 `https://madgear.sytes.net/health`）
   - 監視間隔: 無料で5分ごと
3. **Alert Contacts** にメール / Discord 等を登録（落ちたら通知）
4. （任意）**Status Page** を作成すると、`https://stats.uptimerobot.com/xxxx` のような
   **外部ホストの公開ステータスページ**が手に入る（ポータルが落ちても見られる）

これで「サイトが外から見えなくなった瞬間」に通知が届きます。

### PHP非依存のヘルスURL `/health`
本リポジトリの nginx 設定には `location = /health` を用意済み（`setup-server.sh` が生成）。
これは **PHP/Laravel を介さず nginx が直接 200 を返す**ので、
「nginx は生きているが PHP/アプリが壊れている」状態の切り分けにも使えます。

既存サーバーに後から足す場合は、`/etc/nginx/sites-available/portal` の `server { }` 内に1行追加:
```nginx
location = /health { default_type text/plain; return 200 "ok\n"; }
```
```bash
sudo nginx -t && sudo systemctl reload nginx
curl -i http://localhost/health   # ok が返ればOK
```

### まとめ（3層で監視）
| 層 | ツール | 検知できるもの |
|----|--------|----------------|
| サーバー死活 | healthchecks.io（内→外ping） | サーバー停止・cron停止・体験終了 |
| **外形監視** | **UptimeRobot（外→内HTTP）** | **nginx停止・SSL切れ・サイト到達不可** |
| 期限通知 | 毎日Discord | 体験/no-ip/SSLの期限リマインド |
