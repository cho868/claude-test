#!/usr/bin/env bash
#
# アクセスログの異常検知 & 毎朝ダイジェスト（身内ポータル用）。
# 「身内しか使わないのに大量アクセスが来たら異常」という前提のシンプルな検知。
#
# 使い方（cron）:
#   */10 * * * * /var/www/portal/deploy/access-monitor.sh          # 10分ごとの異常検知
#   5 9 * * *    /var/www/portal/deploy/access-monitor.sh --daily  # 毎朝の前日ダイジェスト
#
# 事前に1回: sudo bash deploy/setup-access-log.sh （実IPをログに出す設定）
#
# 検知条件（/etc/portal-notify.conf で調整可）:
#   - 10分間のリクエスト数が ACCESS_REQS_ALERT 超
#   - 10分間のユニークIP数が ACCESS_IPS_ALERT 超
#   - 10分間の404が ACCESS_404_ALERT 超
#   - 攻撃スキャンっぽいパス(wp-login/.env/phpmyadmin等)が ACCESS_SCAN_ALERT 超
set -euo pipefail

LOG="${ACCESS_LOG:-/var/log/nginx/access.log}"
STATE="${ACCESS_STATE:-/var/lib/portal-access-offset}"

# ===== デフォルト（/etc/portal-notify.conf で上書き）=====
DISCORD_WEBHOOK=""
HOST_LABEL="身内ポータル"
ACCESS_REQS_ALERT="300"     # 10分でこの件数を超えたら異常（身内なら通常は数十）
ACCESS_IPS_ALERT="10"       # 10分でこのユニークIP数を超えたら異常（身内は数人のはず）
ACCESS_404_ALERT="40"       # 10分でこの404数を超えたら異常（探索行為の兆候）
ACCESS_SCAN_ALERT="10"      # 攻撃っぽいパスへのアクセスがこの回数を超えたら異常

[ -f /etc/portal-notify.conf ] && . /etc/portal-notify.conf

send_discord() {
  [ -z "$DISCORD_WEBHOOK" ] && { echo "[access] (webhook未設定) $1" ; return 0; }
  curl -fsS -m 15 -H "Content-Type: application/json" \
    -d "$(jq -nc --arg c "$1" '{content:$c}')" "$DISCORD_WEBHOOK" >/dev/null || echo "[access] Discord送信失敗"
}

# 実IPを取り出す: xff="..." があればそれ、無ければ行頭の$remote_addr
extract_ip() {
  sed -n 's/.*xff="\([^",]*\)[",].*/\1/p; t; s/^\([0-9a-fA-F.:]*\) .*/\1/p' \
    | sed 's/^-$/local/'
}

SCAN_PATTERN='wp-login|wp-admin|wp-content|\.env|phpmyadmin|\.git|xmlrpc|cgi-bin|\.asp|eval\(|/vendor/|\.sql'

# ===== --daily: 前日ダイジェスト =====
if [ "${1:-}" = "--daily" ]; then
  Y="$(date -d yesterday '+%d/%b/%Y')"
  DATA="$( (cat "${LOG}.1" 2>/dev/null; cat "$LOG" 2>/dev/null) | grep -F "[$Y" || true)"
  if [ -z "$DATA" ]; then
    send_discord "📈 [$HOST_LABEL] 昨日のアクセス: 0件"
    exit 0
  fi
  total=$(printf '%s\n' "$DATA" | grep -c .)
  uniq_ips=$(printf '%s\n' "$DATA" | extract_ip | sort -u | grep -c .)
  n404=$(printf '%s\n' "$DATA" | awk '$9 == 404' | grep -c . || true)
  scans=$(printf '%s\n' "$DATA" | grep -cE "$SCAN_PATTERN" || true)
  top_paths=$(printf '%s\n' "$DATA" | awk -F'"' '{print $2}' | awk '{print $2}' \
    | grep -v "^/health$" | sort | uniq -c | sort -rn | head -3 | awk '{printf "  %s (%s回)\n", $2, $1}')
  MSG="📈 **[$HOST_LABEL] 昨日のアクセス**
リクエスト: ${total}件 / 訪問IP: ${uniq_ips} / 404: ${n404} / 怪しいパス: ${scans}
よく見られたページ:
${top_paths}"
  send_discord "$MSG"
  echo "[access] dailyダイジェスト送信"
  exit 0
fi

# ===== 通常モード: 前回実行以降の新規ログだけを見る（10分ごと想定）=====
size=$(stat -c%s "$LOG" 2>/dev/null || echo 0)
last=$(cat "$STATE" 2>/dev/null || echo "")

if [ -z "$last" ]; then
  # 初回はベースラインだけ記録（過去ログ全部で誤発報しない）
  echo "$size" > "$STATE"
  echo "[access] 初回: オフセットを記録 ($size)"
  exit 0
fi
[ "$size" -lt "$last" ] && last=0   # ログローテーションで小さくなったら先頭から

NEW="$(tail -c +$((last + 1)) "$LOG" 2>/dev/null || true)"
echo "$size" > "$STATE"

if [ -z "$NEW" ]; then
  echo "[access] 新規アクセスなし"
  exit 0
fi

# /health(外形監視のping)はノイズなので除外
NEW="$(printf '%s\n' "$NEW" | grep -v '"GET /health ' || true)"
[ -z "$NEW" ] && { echo "[access] 新規は/healthのみ"; exit 0; }

reqs=$(printf '%s\n' "$NEW" | grep -c .)
uniq_ips=$(printf '%s\n' "$NEW" | extract_ip | sort -u | grep -c .)
n404=$(printf '%s\n' "$NEW" | awk '$9 == 404' | grep -c . || true)
scans=$(printf '%s\n' "$NEW" | grep -cE "$SCAN_PATTERN" || true)
# 🔥 本当にヤバいのは「スキャンが成功(2xx)した」場合だけ。403/404で弾いていれば無害。
scan_hits=$(printf '%s\n' "$NEW" | grep -E "$SCAN_PATTERN" | awk '$9 ~ /^2/' | grep -c . || true)
logins=$(printf '%s\n' "$NEW" | grep -c '"POST /login' || true)

# ===== 重大度を2段階に分ける =====
# CRITICAL(@everyone): スキャン成功 or ログイン試行が異常に多い（総当たりの疑い）
# NOTICE(鳴らすが@everyoneなし): 公開サーバーに日常的に来るボットのスキャン・404の山
CRIT=""
NOTE=""
[ "$scan_hits" -gt 0 ] && CRIT="${CRIT}・⚠️ **攻撃系パスが2xxを返した (${scan_hits}件)** ← 要確認！\n"
[ "$logins" -gt "${ACCESS_LOGIN_ALERT:-30}" ] && CRIT="${CRIT}・🔐 ログイン試行 ${logins}件（総当たりの疑い）\n"
[ "$reqs" -gt "$ACCESS_REQS_ALERT" ] && NOTE="${NOTE}・リクエスト数 ${reqs}件（しきい値${ACCESS_REQS_ALERT}）\n"
[ "$uniq_ips" -gt "$ACCESS_IPS_ALERT" ] && NOTE="${NOTE}・ユニークIP ${uniq_ips}（しきい値${ACCESS_IPS_ALERT}）\n"
[ "$n404" -gt "$ACCESS_404_ALERT" ] && NOTE="${NOTE}・404が ${n404}件（探索行為。弾けているので実害なし）\n"
[ "$scans" -gt "$ACCESS_SCAN_ALERT" ] && NOTE="${NOTE}・攻撃スキャン風パス ${scans}件（うち成功 ${scan_hits}件）\n"

if [ -z "$CRIT" ] && [ -z "$NOTE" ]; then
  echo "[access] 正常 (req=$reqs ip=$uniq_ips 404=$n404 scan=$scans hit=$scan_hits)"
  exit 0
fi

top_ips=$(printf '%s\n' "$NEW" | extract_ip | sort | uniq -c | sort -rn | head -5 | awk '{printf "  %s: %s回\n", $2, $1}')
top_paths=$(printf '%s\n' "$NEW" | awk -F'"' '{print $2}' | awk '{print $2}' | sort | uniq -c | sort -rn | head -5 | awk '{printf "  %s (%s回)\n", $2, $1}')

if [ -n "$CRIT" ]; then
  HEAD="@everyone 🚨 **[$HOST_LABEL] 危険なアクセスを検知**（直近10分）
$(printf "$CRIT")$(printf "$NOTE")"
else
  HEAD="🔍 **[$HOST_LABEL] アクセス増加を検知**（直近10分・**弾けているので実害なし**）
$(printf "$NOTE")
※ 公開サーバーには常時ボットのスキャンが来ます。2xxを返していなければ通常のノイズです。"
fi

MSG="${HEAD}
アクセス元IP TOP:
${top_ips}
アクセス先 TOP:
${top_paths}
🔍 詳細: sudo tail -100 /var/log/nginx/access.log"

send_discord "$MSG"
echo "[access] 通知 (crit=${CRIT:+yes} req=$reqs ip=$uniq_ips 404=$n404 scan=$scans hit=$scan_hits login=$logins)"
