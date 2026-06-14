#!/usr/bin/env bash
#
# 毎日の死活通知 + 体験終了カウントダウンを Discord に送る。
# XServer VPS には API が無いため、「自前で毎日リマインド + 死活監視」で取りこぼしを防ぐ。
#
# 使い方:
#   1) 設定ファイルを作成:  sudo cp deploy/portal-notify.conf.example /etc/portal-notify.conf
#                          sudo nano /etc/portal-notify.conf   （Webhook等を記入）
#   2) cron に登録（毎朝9時の例）:
#        sudo crontab -e
#        0 9 * * * /var/www/portal/deploy/notify.sh
#
set -euo pipefail

# ===== デフォルト値（/etc/portal-notify.conf で上書き）=====
DISCORD_WEBHOOK=""                 # Discord Webhook URL（必須）
TRIAL_END=""                       # 体験終了日 YYYY-MM-DD（分かれば。空なら未設定表示）
REAUTH_INTERVAL_DAYS="4"           # 認証間隔: 2GB=4 / 4GB=2
HEALTHCHECK_URL=""                 # healthchecks.io の ping URL（任意・死活監視）
HOST_LABEL="身内ポータル"           # 通知に出す名前
SITE_URL=""                        # 公開URL（任意・通知に添える）

[ -f /etc/portal-notify.conf ] && . /etc/portal-notify.conf

# ===== Discord 送信 =====
send_discord() {
  local msg="$1"
  if [ -z "$DISCORD_WEBHOOK" ]; then
    echo "[notify] DISCORD_WEBHOOK が未設定です"; return 0
  fi
  curl -fsS -m 15 -H "Content-Type: application/json" \
    -d "$(jq -nc --arg c "$msg" '{content:$c}')" \
    "$DISCORD_WEBHOOK" >/dev/null
}

# ===== サーバーの状態 =====
UPTIME="$(uptime -p 2>/dev/null || echo '不明')"
DISK="$(df -h / | awk 'NR==2{print $5" 使用 ("$4" 空き)"}')"
TODAY="$(date +%F)"

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

# ===== メッセージ組み立て =====
MSG="${URGENT}**${HOST_LABEL}** 稼働中 ✅（$TODAY）
${COUNTDOWN_LINE}
🔐 認証リマインド: ${REAUTH_INTERVAL_DAYS}日ごとに XServer の管理画面で認証を（忘れると停止）
🖥️ ${UPTIME} / 💽 ${DISK}"
[ -n "$SITE_URL" ] && MSG="${MSG}
🔗 ${SITE_URL}"

send_discord "$MSG"

# ===== 死活監視 ping（このスクリプトが動いた＝サーバー生存）=====
# 一定時間 ping が来ないと healthchecks.io 側から「落ちた」通知が飛ぶ。
if [ -n "$HEALTHCHECK_URL" ]; then
  curl -fsS -m 10 "$HEALTHCHECK_URL" >/dev/null || true
fi

echo "[notify] 送信しました（$TODAY）"
