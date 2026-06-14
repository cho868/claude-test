#!/usr/bin/env bash
# 身内ポータル — 開発環境セットアップ
# 新しいクローン / Claude Code on the web セッションで依存と DB を整える(冪等)。
set -euo pipefail
cd "$(dirname "$0")/.."

# .env が無ければ作る
if [ ! -f .env ]; then
    cp .env.example .env
    echo "[setup] .env を作成しました"
fi

# Composer 依存(vendor が無いときだけ)
if [ ! -d vendor ]; then
    echo "[setup] composer install ..."
    composer install --no-interaction --prefer-dist --no-progress
fi

# アプリkeyが空なら生成
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --ansi
fi

# SQLite ファイル
touch database/database.sqlite

# マイグレーション + 称号シード
php artisan migrate --graceful --no-interaction --seed

echo "[setup] 完了。'php artisan serve' で起動できます。"
