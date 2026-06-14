#!/usr/bin/env bash
#
# XServer VPS など Ubuntu サーバーの初期セットアップ。
# nginx + PHP 8.3(php-fpm) + Composer + certbot + ufw を入れる。
# 使い方:  sudo bash deploy/setup-server.sh
#
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

if [ "$(id -u)" -ne 0 ]; then
  echo "root で実行してください（sudo bash deploy/setup-server.sh）"; exit 1
fi

echo "==> パッケージ更新 & 基本ツール"
apt-get update -y
apt-get install -y software-properties-common ca-certificates curl unzip git jq

echo "==> PHP 8.3 リポジトリ(ondrej)"
add-apt-repository -y ppa:ondrej/php
apt-get update -y

echo "==> nginx / PHP 8.3 / 拡張をインストール"
apt-get install -y nginx \
  php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-sqlite3 php8.3-bcmath php8.3-zip php8.3-intl php8.3-gd php8.3-mysql

echo "==> Composer"
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

echo "==> certbot(無料SSL)"
apt-get install -y certbot python3-certbot-nginx

echo "==> ファイアウォール(ufw)"
ufw allow OpenSSH || true
ufw allow 'Nginx Full' || true
yes | ufw enable || true

systemctl enable --now php8.3-fpm nginx

echo "==> 完了。次は deploy/deploy-app.sh を実行してください。"
php8.3 -v | head -1
