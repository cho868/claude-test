#!/usr/bin/env bash
#
# 毎日の死活通知 + 体験終了カウントダウンを Discord に送る。
# XServer VPS には API が無いため、「自前で毎日リマインド + 死活監視」で取りこぼしを防ぐ。
#
# 使い方:
#   1) 設定ファイルを作成:  sudo cp deploy/portal-notify.conf.example /etc/portal-notify.conf
#                          sudo vi /etc/portal-notify.conf   （Webhook等を記入）
#   2) cron に登録（毎朝9時 + 緊急毎時 + 12時間更新仕様なら6時間ごとの更新確認）:
#        sudo crontab -e
#        0 9 * * *    /var/www/portal/deploy/notify.sh
#        0 * * * *    /var/www/portal/deploy/notify.sh --urgent-only
#        0 */6 * * *  /var/www/portal/deploy/notify.sh --reauth-remind
#
# --urgent-only:   緊急事項（VPS更新の残り時間・体験期限・SSL切れ・no-ip期限間近・
#                  メモリ逼迫）がある時だけ送信。何もなければ黙る。
# --reauth-remind: 「VPS更新した？」の定期確認。renewed.sh の記録が新しい
#                  （間隔の半分未満）なら黙る。記録が無い/古い時だけ鳴る。
#
# 🔄 VPSを更新(再認証)したら毎回:  sudo /var/www/portal/deploy/renewed.sh
#    → 更新時刻が記録され、残り時間ベースの正確なカウントダウンになる。
set -euo pipefail

MODE="daily"
[ "${1:-}" = "--urgent-only" ] && MODE="urgent"
[ "${1:-}" = "--reauth-remind" ] && MODE="reauth"
URGENT_ONLY=0
[ "$MODE" = "urgent" ] && URGENT_ONLY=1

# ===== デフォルト値（/etc/portal-notify.conf で上書き）=====
DISCORD_WEBHOOK=""                 # Discord Webhook URL（必須）
TRIAL_END=""                       # 体験終了日 YYYY-MM-DD（分かれば。空なら未設定表示）
REAUTH_INTERVAL_DAYS="4"           # 認証間隔(日): 旧仕様用。HOURSを設定するとそちらが優先
REAUTH_INTERVAL_HOURS=""           # 有効期限(時間): 24時間契約なら "24"
RENEW_WINDOW_HOURS="12"            # 期限の何時間前から更新できるか（XServerは12）
RENEW_PANEL_URL="https://secure.xserver.ne.jp/xapanel/login/xvps/"  # 更新ページ
RENEWAL_STAMP="/var/lib/portal-renewal"   # renewed.sh が更新時刻を記録するファイル
HEALTHCHECK_URL=""                 # healthchecks.io の ping URL（任意・死活監視）
HOST_LABEL="身内ポータル"           # 通知に出す名前
SITE_URL=""                        # 公開URL（任意・通知に添える）
DOMAIN=""                          # 証明書チェック用ドメイン（例 madgear.sytes.net）
NOIP_LAST_CONFIRMED=""             # no-ip を最後に確認した日 YYYY-MM-DD（無料は約30日ごと確認）
LINE_TOKEN=""                      # LINE Messaging API のチャネルアクセストークン（任意）
LINE_URGENT_ONLY="1"               # 1=LINEは緊急時のみ送る(月200通の無料枠節約)。0=毎回送る
MEM_ALERT_THRESHOLD="85"           # メモリ使用率がこの%以上で警告（@everyone）

[ -f /etc/portal-notify.conf ] && . /etc/portal-notify.conf

# ===== Discord 送信 =====
send_discord() {
  local msg="$1"
  [ -z "$DISCORD_WEBHOOK" ] && return 0
  curl -fsS -m 15 -H "Content-Type: application/json" \
    -d "$(jq -nc --arg c "$msg" '{content:$c}')" \
    "$DISCORD_WEBHOOK" >/dev/null || echo "[notify] Discord送信失敗"
}

# ===== LINE 送信（Messaging API の broadcast。Botを友だち追加した全員に届く）=====
send_line() {
  local msg="$1"
  [ -z "$LINE_TOKEN" ] && return 0
  curl -fsS -m 15 -X POST https://api.line.me/v2/bot/message/broadcast \
    -H "Authorization: Bearer ${LINE_TOKEN}" \
    -H "Content-Type: application/json" \
    -d "$(jq -nc --arg t "$msg" '{messages:[{type:"text",text:$t}]}')" >/dev/null || echo "[notify] LINE送信失敗"
}

# ===== --reauth-remind: 「VPS更新できる？」の定期確認モード =====
# 更新できるのは期限の RENEW_WINDOW_HOURS 時間前から。それ以前は更新しても
# 意味がないので黙る。更新可能時間に入ってから鳴らす。
if [ "$MODE" = "reauth" ]; then
  interval_h="${REAUTH_INTERVAL_HOURS:-$(( ${REAUTH_INTERVAL_DAYS:-4} * 24 ))}"
  window_h="${RENEW_WINDOW_HOURS:-12}"
  open_after=$(( interval_h - window_h ))
  if [ -f "$RENEWAL_STAMP" ]; then
    last="$(cat "$RENEWAL_STAMP" 2>/dev/null || echo 0)"
    elapsed_h=$(( ( $(date +%s) - last ) / 3600 ))
    if [ "$elapsed_h" -lt "$open_after" ]; then
      open_in=$(( open_after - elapsed_h ))
      echo "[notify] まだ更新不可(あと約${open_in}h)のためリマインド不要"
      exit 0
    fi
    left_h=$(( interval_h - elapsed_h ))
    MSG="@everyone 🔄 **VPS更新できます！** 更新可能時間に入りました・期限まで残り約 **${left_h}時間**
🔗 更新: ${RENEW_PANEL_URL}
✅ 更新したら:  sudo /var/www/portal/deploy/renewed.sh"
  else
    MSG="@everyone 🔄 **VPS更新チェック！**（有効${interval_h}h・更新は期限${window_h}h前から）
⏱️ 更新時刻が未記録です。更新のたびに sudo /var/www/portal/deploy/renewed.sh を実行すると
　 「更新できる時間になったら鳴らす」正確な通知になります
🔗 更新: ${RENEW_PANEL_URL}"
  fi
  send_discord "$MSG"
  send_line "$MSG"
  echo "[notify] reauthリマインドを送信しました"
  exit 0
fi

# ===== サーバーの状態 =====
UPTIME="$(uptime -p 2>/dev/null || echo '不明')"
DISK="$(df -h / | awk 'NR==2{print $5" 使用 ("$4" 空き)"}')"
TODAY="$(date +%F)"
NOW_EPOCH="$(date -d "$TODAY" +%s)"

# ===== 体験終了までの残り日数 =====
COUNTDOWN_LINE="📅 体験終了日: 未設定（分かったら /etc/portal-notify.conf の TRIAL_END に記入）"
URGENT=""
if [ -n "$TRIAL_END" ]; then
  end_epoch=$(date -d "$TRIAL_END" +%s)
  now_epoch=$(date -d "$TODAY" +%s)
  days_left=$(( (end_epoch - now_epoch) / 86400 ))

  if   [ "$days_left" -lt 0 ]; then
    COUNTDOWN_LINE="🔴 体験終了日($TRIAL_END)を過ぎています！"
    URGENT="@everyone "
  elif [ "$days_left" -eq 0 ]; then
    COUNTDOWN_LINE="🔴 本日が体験終了日($TRIAL_END)！ すぐ更新を！"
    URGENT="@everyone "
  elif [ "$days_left" -eq 1 ]; then
    COUNTDOWN_LINE="🟠 残り1日（終了日: $TRIAL_END）。更新は前日のみ＝**今日が更新タイミング**！"
    URGENT="@everyone "
  elif [ "$days_left" -le 3 ]; then
    COUNTDOWN_LINE="🟡 残り ${days_left} 日（終了日: $TRIAL_END）"
  else
    COUNTDOWN_LINE="🟢 残り ${days_left} 日（終了日: $TRIAL_END）"
  fi
fi

# ===== VPS更新(再認証)の残り時間 =====
# モデル: 有効期限 REAUTH_INTERVAL_HOURS 時間・更新できるのは期限の
# RENEW_WINDOW_HOURS 時間前から。更新できない時間帯は緑表示のみ(鳴らさない)。
# 更新可能時間に入ったら🟠、期限3時間前から🔴@everyone。renewed.sh の記録が前提。
if [ -n "$REAUTH_INTERVAL_HOURS" ]; then
  window_h="${RENEW_WINDOW_HOURS:-12}"
  open_after=$(( REAUTH_INTERVAL_HOURS - window_h ))   # 更新可能になるまでの経過時間
  REAUTH_LINE="🔐 VPS更新: 有効${REAUTH_INTERVAL_HOURS}h・期限${window_h}h前から更新可（更新後は renewed.sh で記録）"
  if [ -f "$RENEWAL_STAMP" ]; then
    last="$(cat "$RENEWAL_STAMP" 2>/dev/null || echo 0)"
    if [ "$last" -gt 0 ]; then
      elapsed_h=$(( ( $(date +%s) - last ) / 3600 ))
      left_h=$(( REAUTH_INTERVAL_HOURS - elapsed_h ))
      open_in=$(( open_after - elapsed_h ))
      if [ "$left_h" -le 0 ]; then
        REAUTH_LINE="🔴 VPS更新期限を**超過している可能性**！今すぐ確認 → ${RENEW_PANEL_URL}"
        URGENT="@everyone "
      elif [ "$left_h" -le 3 ]; then
        REAUTH_LINE="🔴 VPS更新まで残り約 **${left_h}時間**！ → ${RENEW_PANEL_URL}"
        URGENT="@everyone "
      elif [ "$elapsed_h" -ge "$open_after" ]; then
        REAUTH_LINE="🟠 VPS更新できます（残り約${left_h}h）→ ${RENEW_PANEL_URL}"
      else
        REAUTH_LINE="🟢 VPS: あと約${open_in}hで更新可能（期限まで${left_h}h・まだ更新不可）"
      fi
    fi
  fi
else
  REAUTH_LINE="🔐 認証リマインド: ${REAUTH_INTERVAL_DAYS}日ごとに XServer の管理画面で認証を（忘れると停止）"
fi

# ===== Let's Encrypt 証明書の残り日数 =====
CERT_LINE=""
if [ -n "$DOMAIN" ] && [ -f "/etc/letsencrypt/live/$DOMAIN/cert.pem" ]; then
  cert_end=$(date -d "$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/$DOMAIN/cert.pem" | cut -d= -f2)" +%s 2>/dev/null || echo 0)
  if [ "$cert_end" -gt 0 ]; then
    cert_days=$(( (cert_end - NOW_EPOCH) / 86400 ))
    if   [ "$cert_days" -lt 0 ]; then CERT_LINE="🔴 SSL証明書が期限切れ！ sudo certbot renew を確認"; URGENT="@everyone "
    elif [ "$cert_days" -le 7 ]; then CERT_LINE="🟠 SSL証明書 残り${cert_days}日（自動更新のはず。要確認）"
    else CERT_LINE="🔒 SSL証明書 残り${cert_days}日（自動更新）"; fi
  fi
fi

# ===== no-ip の確認リマインド =====
# 公式仕様: 無料ホスト名は30日サイクル。23日目から確認可能(メールも23日目に届く)、
# 30日で失効(名前解決が止まる)。⚠️ IP更新やDUCでは確認扱いにならない！
# 確認方法: メールのリンク or マイページの Confirm ボタン。確認したら
# /etc/portal-notify.conf の NOIP_LAST_CONFIRMED をその日付に更新すること。
NOIP_LINE="🌐 no-ip: 無料ホスト名は30日ごとに確認必須（NOIP_LAST_CONFIRMED 未設定。確認した日を記入して）"
if [ -n "$NOIP_LAST_CONFIRMED" ]; then
  noip_epoch=$(date -d "$NOIP_LAST_CONFIRMED" +%s 2>/dev/null || echo 0)
  if [ "$noip_epoch" -gt 0 ]; then
    noip_days=$(( (NOW_EPOCH - noip_epoch) / 86400 ))
    noip_left=$(( 30 - noip_days ))
    if   [ "$noip_days" -ge 27 ]; then NOIP_LINE="🔴 no-ip 失効まで残り${noip_left}日！ **今すぐ確認を**（メールのリンク or マイページのConfirm。IP更新では延長されない）"; URGENT="@everyone "
    elif [ "$noip_days" -ge 23 ]; then NOIP_LINE="🟠 no-ip 確認期間に入りました（${noip_days}日経過・残り${noip_left}日）。確認メールが来ているはず。確認したら NOIP_LAST_CONFIRMED を更新"
    else NOIP_LINE="🌐 no-ip 確認から${noip_days}日（次の確認は23日目〜30日目・最終: $NOIP_LAST_CONFIRMED）"; fi
  fi
fi

# ===== メモリ使用率（しきい値超で警告）=====
MEM_LINE=""
MEMINFO="$(cat /proc/meminfo 2>/dev/null || true)"
if [ -n "$MEMINFO" ]; then
  mt=$(echo "$MEMINFO" | awk '/^MemTotal:/{print $2}')
  ma=$(echo "$MEMINFO" | awk '/^MemAvailable:/{print $2}')
  if [ -n "$mt" ] && [ -n "$ma" ] && [ "$mt" -gt 0 ]; then
    mem_pct=$(( (mt - ma) * 100 / mt ))
    if [ "$mem_pct" -ge "$MEM_ALERT_THRESHOLD" ]; then
      MEM_LINE="🟠 メモリ使用率 ${mem_pct}%（しきい値 ${MEM_ALERT_THRESHOLD}% 超）"
      URGENT="@everyone "
    else
      MEM_LINE="🧠 メモリ ${mem_pct}%"
    fi
  fi
fi

# ===== メッセージ組み立て =====
MSG="${URGENT}**${HOST_LABEL}** 稼働中 ✅（$TODAY）
${COUNTDOWN_LINE}
${REAUTH_LINE}
${NOIP_LINE}"
[ -n "$CERT_LINE" ] && MSG="${MSG}
${CERT_LINE}"
[ -n "$MEM_LINE" ] && MSG="${MSG}
${MEM_LINE}"
MSG="${MSG}
🖥️ ${UPTIME} / 💽 ${DISK}"
[ -n "$SITE_URL" ] && MSG="${MSG}
🔗 ${SITE_URL}"

# --urgent-only モード: 緊急が無ければ何も送らずに終了
if [ "$URGENT_ONLY" -eq 1 ]; then
  if [ -z "$URGENT" ]; then
    echo "[notify] 緊急事項なし（--urgent-only のため送信スキップ）"
    exit 0
  fi
  MSG="${URGENT}🚨 **緊急リマインド**（1時間ごとに再送中。対応するまで鳴ります）
${MSG}"
fi

send_discord "$MSG"
# LINEは無料枠(月200通)節約のため、既定では緊急時($URGENT)のみ。毎回送るなら LINE_URGENT_ONLY=0
if [ "$LINE_URGENT_ONLY" != "1" ] || [ -n "$URGENT" ]; then
  send_line "$MSG"
fi

# ===== 死活監視 ping（このスクリプトが動いた＝サーバー生存）=====
# 一定時間 ping が来ないと healthchecks.io 側から「落ちた」通知が飛ぶ。
# ※ ping は毎日の通常実行時のみ（--urgent-only は期限アラート専用）
if [ "$URGENT_ONLY" -eq 0 ] && [ -n "$HEALTHCHECK_URL" ]; then
  curl -fsS -m 10 "$HEALTHCHECK_URL" >/dev/null || true
fi

echo "[notify] 送信しました（$TODAY）"
