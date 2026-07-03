#!/usr/bin/env bash
#
# SQLite DB を「VPSの外」へ退避するオフサイト・バックアップ。
# VPSが丸ごと消えても復元できるよう、毎日 Discord のチャンネルに
# DBスナップショット(gz / 任意でAES暗号化)をアップロードする。
#
# なぜ必要か:
#   /root などVPS上へのコピーは、VPSごと消える(体験終了/認証漏れ/障害)と一緒に失われる。
#   守るべきDBは "VPSの外" に置いて初めて全損から守れる。
#
# 使い方:
#   1) 設定は notify と共用: /etc/portal-notify.conf の DISCORD_WEBHOOK を利用。
#      （できれば専用チャンネルのWebhookを作り、BACKUP_WEBHOOK に設定するのを推奨）
#      暗号化するなら BACKUP_PASSPHRASE を設定（ユーザーデータをDiscordに置くため強く推奨）。
#   2) cron に登録（毎日3:10の例）:
#        sudo crontab -e
#        10 3 * * * /var/www/portal/deploy/backup-to-discord.sh
#
# 復元:
#   Discordから最新の portal-YYYY-MM-DD.sqlite.gz(.enc) を落として、
#   (暗号化していれば) openssl で復号 → gunzip → database/database.sqlite へ配置。
#   → DEPLOY.md「ゼロからの再構築」参照。
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/portal}"
DB="${DB:-$APP_DIR/database/database.sqlite}"
LOCAL_KEEP_DAYS="${LOCAL_KEEP_DAYS:-7}"
LOCAL_DIR="${LOCAL_DIR:-/root/portal-backups}"
DISCORD_MAX_MB="${DISCORD_MAX_MB:-9}"   # Discord無料枠の上限に対する安全側の目安

# ===== デフォルト（/etc/portal-notify.conf で上書き）=====
DISCORD_WEBHOOK=""      # 通知と共用のWebhook
BACKUP_WEBHOOK=""       # バックアップ専用Webhook（あれば優先）
BACKUP_PASSPHRASE=""    # 設定するとAES-256-CBCで暗号化して送る（推奨）
HOST_LABEL="身内ポータル"

[ -f /etc/portal-notify.conf ] && . /etc/portal-notify.conf

WEBHOOK="${BACKUP_WEBHOOK:-$DISCORD_WEBHOOK}"

log() { echo "[backup] $*"; }
notify() {
  # 失敗時などのテキスト通知（Webhookが無ければ黙る）
  [ -z "$WEBHOOK" ] && return 0
  curl -fsS -m 15 -H "Content-Type: application/json" \
    -d "$(jq -nc --arg c "$1" '{content:$c}')" "$WEBHOOK" >/dev/null 2>&1 || true
}

if [ ! -f "$DB" ]; then
  log "DBが見つかりません: $DB"
  notify "⚠️ [$HOST_LABEL] バックアップ失敗: DBファイルが見つかりません（$DB）"
  exit 1
fi

DATE="$(date +%F)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

SNAP="$TMP/portal-$DATE.sqlite"
# 整合性のあるスナップショットを作る（書き込み中でも壊れないよう .backup を優先）
if command -v sqlite3 >/dev/null 2>&1; then
  sqlite3 "$DB" ".backup '$SNAP'"
else
  cp "$DB" "$SNAP"
fi

gzip -f "$SNAP"
OUT="$SNAP.gz"

# 任意: 暗号化（ユーザーデータをDiscordに置くため推奨）
if [ -n "$BACKUP_PASSPHRASE" ]; then
  openssl enc -aes-256-cbc -pbkdf2 -salt \
    -in "$OUT" -out "$OUT.enc" -pass pass:"$BACKUP_PASSPHRASE"
  rm -f "$OUT"
  OUT="$OUT.enc"
fi

FNAME="$(basename "$OUT")"

# ローカルにも二次コピー（世代管理）。※一次防衛はあくまでオフサイト
mkdir -p "$LOCAL_DIR"
cp "$OUT" "$LOCAL_DIR/$FNAME"
find "$LOCAL_DIR" -name 'portal-*.sqlite.gz*' -mtime +"$LOCAL_KEEP_DAYS" -delete 2>/dev/null || true

SIZE_BYTES="$(stat -c%s "$OUT" 2>/dev/null || wc -c <"$OUT")"
SIZE_MB=$(( SIZE_BYTES / 1024 / 1024 ))

if [ -z "$WEBHOOK" ]; then
  log "Webhook未設定のためオフサイト送信をスキップ（ローカルのみ: $LOCAL_DIR/$FNAME）"
  exit 0
fi

if [ "$SIZE_MB" -ge "$DISCORD_MAX_MB" ]; then
  log "サイズが大きすぎます（${SIZE_MB}MB）。Discord送信をスキップ。"
  notify "⚠️ [$HOST_LABEL] DBバックアップが${SIZE_MB}MBでDiscord上限超過。別のオフサイト手段(rclone等)へ切替を検討してください。ローカルには保存済み。"
  exit 0
fi

ENC_NOTE=""
[ -n "$BACKUP_PASSPHRASE" ] && ENC_NOTE="（暗号化済み・復号にパスフレーズ必要）"

if curl -fsS -m 60 \
    -F "payload_json=$(jq -nc --arg c "🗄️ [$HOST_LABEL] DBバックアップ $DATE ${ENC_NOTE}" '{content:$c}')" \
    -F "file1=@$OUT;filename=$FNAME" \
    "$WEBHOOK" >/dev/null; then
  log "オフサイト送信OK: $FNAME (${SIZE_MB}MB)"
else
  log "Discord送信失敗"
  notify "⚠️ [$HOST_LABEL] DBバックアップのDiscord送信に失敗しました。ローカルには保存済み。"
  exit 1
fi
