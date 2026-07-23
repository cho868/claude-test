#!/usr/bin/env bash
#
# no-ip の無料ホスト名を確認(Confirm)した直後に実行して、確認日を記録する。
#   sudo /var/www/portal/deploy/noip-confirmed.sh              # 今日を記録
#   sudo /var/www/portal/deploy/noip-confirmed.sh 2026-07-21   # 過去日を指定
#
# /etc/portal-notify.conf の NOIP_LAST_CONFIRMED を指定日(既定は今日)に書き換える。
# 以降 notify.sh が「確認から◯日／残り◯日」を計算し、23日目に🟠・27日目に
# 🔴@everyone を出す（30日で失効する前に必ず鳴る）。
set -euo pipefail

CONF="/etc/portal-notify.conf"
TODAY="${1:-$(date +%F)}"

if [ "$(id -u)" -ne 0 ]; then
  echo "root で実行してください:  sudo $0 [YYYY-MM-DD]"; exit 1
fi
# 日付の妥当性チェック（不正な引数で設定を壊さない）
if ! date -d "$TODAY" +%F >/dev/null 2>&1; then
  echo "日付の形式が不正です: $TODAY（例: 2026-07-21）"; exit 1
fi
TODAY="$(date -d "$TODAY" +%F)"
if [ ! -f "$CONF" ]; then
  echo "設定ファイルがありません: $CONF（先に portal-notify.conf.example からコピーしてください）"; exit 1
fi

# NOIP_LAST_CONFIRMED 行があれば置換、無ければ追記（重複させない）
if grep -q '^NOIP_LAST_CONFIRMED=' "$CONF"; then
  sed -i "s|^NOIP_LAST_CONFIRMED=.*|NOIP_LAST_CONFIRMED=\"$TODAY\"|" "$CONF"
else
  printf '\nNOIP_LAST_CONFIRMED="%s"\n' "$TODAY" >> "$CONF"
fi

NEXT="$(date -d "$TODAY +30 days" +%F)"
echo "[noip] 確認日を記録しました: $TODAY（次の失効目安: $NEXT / 23日目から通知開始）"

# Discordにも記録（誰がいつ確認したか身内で共有）
DISCORD_WEBHOOK=""; HOST_LABEL="身内ポータル"
. "$CONF" 2>/dev/null || true
if [ -n "$DISCORD_WEBHOOK" ] && command -v jq >/dev/null 2>&1; then
  MSG="🌐 [$HOST_LABEL] no-ip を確認しました（$TODAY） / 次の期限目安: **$NEXT**"
  curl -fsS -m 15 -H "Content-Type: application/json" \
    -d "$(jq -nc --arg c "$MSG" '{content:$c}')" "$DISCORD_WEBHOOK" >/dev/null || true
fi
