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
sudo micro /etc/portal-notify.conf
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

## 5. cron で毎日自動実行

```bash
sudo crontab -e
```
最下部に追記（毎朝 9:00 に通知）:
```cron
0 9 * * * /var/www/portal/deploy/notify.sh >> /var/log/portal-notify.log 2>&1
```

> 認証間隔が短いのが不安なら、1日2回（朝晩）にも増やせます:
> `0 9,21 * * * /var/www/portal/deploy/notify.sh`

---

## 体験終了日が「前日しか分からない/更新できない」問題について

- XServer 側に終了日を取りに行く API はありません。
- 対策: **健康監視（healthchecks.io）で「止まった瞬間」を必ず検知**できるので、最悪見逃しても気づけます。
- さらに、終了日が判明したら `TRIAL_END` に入れておけば、**前日に🔴強調通知**（`@everyone` 付き）が飛びます。
- 「更新は前日のみ」という仕様にも、この前日アラートが噛み合います。

## Let's Encrypt の自動更新について

証明書の更新は **certbot が自動でやってくれます**（`certbot.timer` が標準で有効）。確認:
```bash
systemctl list-timers | grep certbot      # タイマーが動いているか
sudo certbot renew --dry-run               # 更新テスト
```
no-ip の無料ホスト名だけは **約30日ごとに確認メールのクリック**が必要なので、これも上記 Discord 通知の習慣でカバーできます（毎日見る場所に出る）。
