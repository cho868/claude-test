#!/usr/bin/env bash
#
# アプリのデプロイ / 更新。初回も2回目以降もこれ1本でOK(冪等)。
# 使い方:  sudo bash deploy/deploy-app.sh [ブランチ名]
#   例)   sudo bash deploy/deploy-app.sh main
#
set -euo pipefail

APP_DIR=/var/www/portal
REPO=https://github.com/cho868/claude-test.git
BRANCH="${1:-main}"

if [ "$(id -u)" -ne 0 ]; then
  echo "root で実行してください（sudo bash deploy/deploy-app.sh）"; exit 1
fi

echo "==> 取得 (branch: $BRANCH)"
if [ ! -d "$APP_DIR/.git" ]; then
  git clone -b "$BRANCH" "$REPO" "$APP_DIR"
else
  git -C "$APP_DIR" fetch origin "$BRANCH"
  git -C "$APP_DIR" reset --hard "origin/$BRANCH"
fi

cd "$APP_DIR"

echo "==> Composer(本番依存のみ)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> .env"
if [ ! -f .env ]; then
  cp .env.production.example .env
  echo "  .env を作成しました。APP_URL などを編集してください: $APP_DIR/.env"
fi
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

echo "==> データベース(SQLite)"
touch database/database.sqlite
php artisan migrate --force --seed

echo "==> キャッシュ最適化"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> 権限(www-data が storage / DB を書けるように)"
chown -R www-data:www-data storage database bootstrap/cache
chmod -R ug+rwX storage database bootstrap/cache

echo "==> 完了。 https://（あなたのドメイン） で確認してください。"
