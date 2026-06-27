#!/usr/bin/env bash
#
# .env に「不足しているキーだけ」を .env.example から安全に追記する。
# 既存のキー・値は一切変更しない（APP_KEY や各種シークレットは保持）。
# 実行前に .env をバックアップする。
#
# 使い方:  sudo bash deploy/sync-env.sh
#   追記後に  sudo vi .env  で値を埋め、 php artisan config:cache を実行。
#
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo ".env が見つかりません（このディレクトリで実行してください）"; exit 1; }
[ -f .env.example ] || { echo ".env.example が見つかりません"; exit 1; }

backup=".env.bak.$(date +%F_%H%M%S)"
cp .env "$backup"
echo "バックアップ: $backup"

# .env.example にあって .env に無いキー名を抽出
missing="$(comm -23 \
  <(grep -oE '^[A-Za-z_][A-Za-z0-9_]*' .env.example | sort -u) \
  <(grep -oE '^[A-Za-z_][A-Za-z0-9_]*' .env | sort -u) || true)"

if [ -z "$missing" ]; then
  echo "不足キーはありません。.env は最新です。"
  rm -f "$backup"
  exit 0
fi

echo "以下のキーを .env に追記します（値は空/既定。あとで埋めてください）:"
echo "$missing" | sed 's/^/  - /'

{
  echo ""
  echo "# --- $(date +%F) sync-env.sh で追記（値を確認・記入してください） ---"
  while IFS= read -r key; do
    [ -n "$key" ] && grep -m1 -E "^${key}=" .env.example
  done <<< "$missing"
} >> .env

echo ""
echo "完了。次に:"
echo "  sudo vi .env                        # 追記分の値を記入"
echo "  sudo -u www-data php artisan config:cache"
echo "問題があれば $backup から戻せます。"
