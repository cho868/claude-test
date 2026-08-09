#!/usr/bin/env bash
#
# SDカードの書き込み量・健康状態を週1でDiscordに報告する（ラズパイ用）。
#   cron: 30 9 * * 0  /var/www/portal/deploy/sd-health.sh   # 毎週日曜9:30
#
# SDカードはSSDと違いSMART(正確な摩耗値)を読めないため、
# 「カーネルが数えた書き込みセクタ数」から書き込みペースを実測し、
# ざっくりの寿命目安を算出する。目安の前提(総書込許容量)は
# /etc/portal-notify.conf の SD_TBW_EST_TB で調整可（既定10TB＝
# 32GB級の一般的なカードの控えめな見積もり。メーカー非公開のため超概算）。
set -euo pipefail

DEV="${SD_DEVICE:-mmcblk0}"                    # SDは通常 mmcblk0。USB-SSD起動なら sda
SYS_STAT="${SD_SYS_STAT:-/sys/block/$DEV/stat}"
STATE="${SD_STATE:-/var/lib/portal-sd-stat}"

DISCORD_WEBHOOK=""
HOST_LABEL="身内ポータル"
SD_TBW_EST_TB="10"
[ -f /etc/portal-notify.conf ] && . /etc/portal-notify.conf

send_discord() {
  [ -z "$DISCORD_WEBHOOK" ] && { echo "[sd] (webhook未設定) $1"; return 0; }
  curl -fsS -m 15 -H "Content-Type: application/json" \
    -d "$(jq -nc --arg c "$1" '{content:$c}')" "$DISCORD_WEBHOOK" >/dev/null || echo "[sd] Discord送信失敗"
}

if [ ! -f "$SYS_STAT" ]; then
  echo "[sd] デバイスが見つかりません: $SYS_STAT (SD_DEVICEを確認)"; exit 1
fi

# /sys/block/*/stat の第7フィールド = 書き込みセクタ数(×512バイト)。起動からの累計。
sectors="$(awk '{print $7}' "$SYS_STAT")"
bytes=$(( sectors * 512 ))
now="$(date +%s)"

if [ ! -f "$STATE" ]; then
  echo "$bytes $now" > "$STATE"
  echo "[sd] 初回: 基準値を記録しました。次回実行から差分を報告します"
  exit 0
fi

read -r last_bytes last_epoch < "$STATE"
echo "$bytes $now" > "$STATE"

REBOOT_NOTE=""
if [ "$bytes" -lt "$last_bytes" ]; then
  # 再起動でカウンタがリセットされた → 今回起動以降の分のみで概算
  delta=$bytes
  REBOOT_NOTE="（期間中に再起動あり・数値は目安）"
else
  delta=$(( bytes - last_bytes ))
fi
period_days_x10=$(( (now - last_epoch) * 10 / 86400 ))   # 0.1日単位
[ "$period_days_x10" -lt 1 ] && period_days_x10=1

# 計算はawkで（小数を扱うため）
read -r delta_mb per_day_mb years <<EOF2
$(awk -v d="$delta" -v p10="$period_days_x10" -v tbw="$SD_TBW_EST_TB" 'BEGIN {
  dmb = d / 1048576
  per = dmb / (p10 / 10)
  if (per < 0.01) per = 0.01
  yrs = (tbw * 1048576) / per / 365
  printf "%.0f %.0f %.0f", dmb, per, yrs
}')
EOF2

# 併せて健康チェック
DISK_USE="$(df -h / | awk 'NR==2{print $5" 使用 ("$4" 空き)"}')"
MMC_ERRS="$(dmesg 2>/dev/null | grep -ciE 'mmc[0-9]*: .*(error|timeout)|I/O error.*mmcblk' || true)"
TEMP="$(vcgencmd measure_temp 2>/dev/null | tr -d "temp='C" || echo '不明')"
THROTTLE="$(vcgencmd get_throttled 2>/dev/null | cut -d= -f2 || echo '不明')"

HEALTH="🟢 良好"
NOTE=""
if [ "$MMC_ERRS" -gt 0 ]; then
  HEALTH="🟠 注意"; NOTE="⚠️ SD関連のエラーが ${MMC_ERRS}件 検出。増え続けるようならSD交換を検討\n"
fi
if [ "$THROTTLE" != "0x0" ] && [ "$THROTTLE" != "不明" ]; then
  HEALTH="🟠 注意"; NOTE="${NOTE}⚠️ 電圧/温度の問題履歴あり (get_throttled=$THROTTLE)\n"
fi
if [ "$years" -lt 3 ] 2>/dev/null; then
  HEALTH="🟠 注意"; NOTE="${NOTE}⚠️ 書き込みペースが速め。log2ramの動作確認か高耐久SD/SSD化を検討\n"
fi

MSG="🩺 **[$HOST_LABEL] SDカード週次レポート** ${HEALTH}
📝 今期間の書き込み: ${delta_mb}MB（約${per_day_mb}MB/日）${REBOOT_NOTE}
⏳ 寿命の超ざっくり目安: このペースなら**あと約${years}年**（総書込${SD_TBW_EST_TB}TB想定の概算）
💽 ディスク: ${DISK_USE} / 🌡️ ${TEMP}℃
$(printf "$NOTE")※ SDはSMART非対応のため書き込み量からの推定です。信頼できる保険は毎日のDiscordバックアップ"

MSG="$(printf '%s\n' "$MSG" | sed '/^$/d')"
send_discord "$MSG"
echo "[sd] レポート送信 (delta=${delta_mb}MB per_day=${per_day_mb}MB years=${years})"
