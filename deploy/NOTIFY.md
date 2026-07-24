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
# 毎朝 9:00 に通常通知（稼働状況・各種残り時間。Discord）
0 9 * * * /var/www/portal/deploy/notify.sh >> /var/log/portal-notify.log 2>&1
# 毎時0分: no-ip/SSL/メモリの緊急がある時だけ再送
0 * * * * /var/www/portal/deploy/notify.sh --urgent-only >> /var/log/portal-notify.log 2>&1
# 6時間ごと: VPSが「更新可能時間」に入ったら通知（それ以前・更新直後は黙る）
0 */6 * * * /var/www/portal/deploy/notify.sh --reauth-remind >> /var/log/portal-notify.log 2>&1
# 10分ごと: VPS更新の期限1時間前だけ @everyone で連打（それ以外は黙る）
*/10 * * * * /var/www/portal/deploy/notify.sh --vps-final >> /var/log/portal-notify.log 2>&1
```

各モードは条件を満たす時だけ送信するので、毎時/10分ごとcronでも普段は静か。
期限当日などは **1時間ごとに @everyone 付きで鳴り続けます**（対応するまで）。

## 🕛 24時間契約・更新は期限12時間前から（新仕様）への対応

2026-07 に無料VPSの仕様が変わった。XServerの実際の挙動は
**「有効期限は24時間・更新できるのは期限の12時間前から」**（更新メールにも
「利用期限の12時間前から更新手続きが可能です」と記載）。
つまり**更新可能になる前に鳴らしても更新できない**ので、その時間帯は黙るのが正解。

1. `/etc/portal-notify.conf` に設定:
   ```ini
   REAUTH_INTERVAL_HOURS="24"   # 有効期限(時間)
   RENEW_WINDOW_HOURS="12"      # 期限の何時間前から更新できるか
   ```
2. **VPSを更新するたびに** サーバーで:
   ```bash
   sudo /var/www/portal/deploy/renewed.sh
   ```
   → 更新時刻が記録され、Discordにも「✅更新しました」が流れる（誰が更新したか身内で共有）
3. すると通知はこう動く（前回更新からの経過時間で判定）:
   - **0〜12時間（更新不可の時間帯）**: 🟢「あと約◯hで更新可能」表示のみ・**鳴らさない**
   - **12時間経過（更新可能に）**: 🟠「VPS更新できます」。6時間ごとの `--reauth-remind` がここから鳴る
   - **期限1時間前から**: `--vps-final` が **10分ごとに @everyone**（「残り◯分！」）で連打
   - **期限超過**: ⚫「**消えた可能性があります**」と表示するだけ（もう手遅れなので連打しない）
   - 記録を忘れても `--reauth-remind` が「更新可能時間に入ったら」鳴らすので取りこぼしにくい

> ⚠️ それでも**外出・睡眠中に更新可能時間(12時間)の壁を越えられない**ことがある。
> この仕様で長期運用は現実的でないため、**Oracle Cloud Always Free（恒久無料・更新不要）への移行を強く推奨**。
> `deploy/` のスクリプトはほぼそのまま使える。移行するまでの延命策として上記を使うこと。
> 万一消えても、オフサイトバックアップ（backup-to-discord.sh）があれば30分で復旧できる。

---

## 🚨 XServer の期限メールを Gmail で検知する（最重要・強アラート）

XServer は期限が近づくと
**「【XServer VPS】■重要■無料サーバーのご利用期限と更新に関するご案内 (サーバー名)」**
というメールを送ってくる。**このメールの到着日＝更新期限日**。ここを取りこぼすとVPSごと消える。

`deploy/gmail-xserver-alert.gs`（Google Apps Script）が、このメールを **Gmail側で30分ごとに監視**し、
見つけたら **本文の抜粋＋期限日＋更新ページへのリンク**を Discord / LINE に **繰り返し送る**。

**no-ip の確認メール（confirm/expire）も同時に監視**する。no-ipは期限日が管理画面から分かりにくい
（Active期間中は表示されない）ので、23日目に届く確認メールを検知して鳴らすのが確実。
リンクをクリックしたら `/etc/portal-notify.conf` の `NOIP_LAST_CONFIRMED` を更新すること。

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

## 🌐 no-ip 無料ホスト名の失効ルール（公式仕様・調査済み 2026-07）

「VPSを何度作り直してもホスト名が生きてる」ので30日ルールが本当か曖昧だったが、公式KBで確認した正確な仕様:

| 期間 | 状態 |
|------|------|
| 1〜23日目 | Active。何もしなくてよい（この間は確認**できない**） |
| 23〜30日目 | **確認期間**。23日目に確認メールが届く（期限当日にもう1通）。マイページにも Confirm ボタンが出る |
| 30日で失効 | **名前解決が止まる**（サイトに繋がらなくなる）。7日間は無料で復活可能 |
| 失効+7日〜 | Redemption。**無料では取り戻せない** |

**⚠️ 最重要: IPアドレスの更新・DUC・ログインでは30日タイマーはリセットされない。**
「確認メールのリンクをクリック」または「マイページの Confirm ボタン」だけが延長手段。
（＝VPS再構築でIPを変えても延長にはなっていない。ホスト名が生きていたのは単にまだ30日以内だったか、確認メールを踏んでいたから）

運用ルール:
1. 確認メール（件名に "Confirm" / No-IP から）が来たら**即クリック**
2. クリックしたらサーバーで **`sudo /var/www/portal/deploy/noip-confirmed.sh`** を実行
   （`NOIP_LAST_CONFIRMED` を今日付で自動記録＋Discordに共有。手で編集してもよい）
   → notify.sh が「23日目〜」で🟠、「27日目〜」で🔴@everyone（毎時再送対象）を出す
3. いま何日目か分からない場合は **no-ip のマイページ → Dynamic DNS** でホスト名の有効期限が見られるので、一度ログインして確認する

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

> ⚠️ **LINEが突然来なくなった時は「月200通の無料枠」超過をまず疑う**（Discordは無制限なので生きているのに、LINEだけ止まる＝典型症状）。
> - 無料枠 = **月200通**。しかも「**配信数 × 友だち人数**」で消費（3人に届けば3通）。毎時の緊急再送や毎朝の稼働通知まで流すと簡単に超える。
> - 超えると**翌月1日まで**送信が止まる（LINE側で429）。消費状況は **LINE Official Account Manager → 分析** で確認できる。
> - 対策1: `LINE_URGENT_ONLY="1"`（既定）で **LINEは緊急時（@everyone級）と更新リマインドだけ**に絞る。日常の「稼働中✅」はDiscordのみ。
> - 対策2: `LINE_MIN_INTERVAL_MIN="30"`（既定）で **LINEは30分に1通まで**に間引く。Discordは全通知が残る（10分ごとの連打もDiscordには出る）ので情報は失われない。この2つで概ね月100〜150通に収まる（友だち人数×なので人数が多いと増える）。
> - どうしても足りなければ間隔を延ばす（例 `LINE_MIN_INTERVAL_MIN="60"`）、友だち人数を減らす、有料プラン、または LINE をやめて Discord のモバイルプッシュ通知に一本化する手もある。

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
