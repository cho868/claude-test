#!/usr/bin/env bash
#
# VPSの更新(再認証)をした直後に実行して、更新時刻を記録する。
#   sudo /var/www/portal/deploy/renewed.sh
#
# 記録すると notify.sh が「残り◯時間」の正確なカウントダウンになり、
# 残り3時間を切ると毎時 @everyone で鳴る。--reauth-remind も更新直後は黙る。
set -euo pipefail

RENEWAL_STAMP="/var/lib/portal-renewal"
DISCORD_WEBHOOK=""
LINE_TOKEN=""
REAUTH_INTERVAL_HOURS=""
HOST_LABEL="身内ポータル"
[ -f /etc/portal-notify.conf ] && . /etc/portal-notify.conf

if [ "$(id -u)" -ne 0 ]; then
  echo "root で実行してください:  sudo $0"; exit 1
fi

date +%s > "$RENEWAL_STAMP"
NOW="$(date '+%m/%d %H:%M')"
NEXT=""
if [ -n "$REAUTH_INTERVAL_HOURS" ]; then
  NEXT="$(date -d "+${REAUTH_INTERVAL_HOURS} hours" '+%m/%d %H:%M')"
fi

echo "[renewed] 更新時刻を記録しました: $NOW${NEXT:+（次の期限目安: $NEXT）}"

# Discordにも記録を残す（誰がいつ更新したか身内で共有できる）
if [ -n "$DISCORD_WEBHOOK" ]; then
  MSG="✅ [$HOST_LABEL] VPSを更新しました（$NOW）${NEXT:+ / 次の期限目安: **$NEXT**}"
  curl -fsS -m 15 -H "Content-Type: application/json" \
    -d "$(jq -nc --arg c "$MSG" '{content:$c}')" "$DISCORD_WEBHOOK" >/dev/null || true
fi
