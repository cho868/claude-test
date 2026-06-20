#!/usr/bin/env bash
#
# Ubuntu サーバーの初期セットアップ（Ubuntu 26.04 LTS で動作確認）。
# ディストリ標準の PHP（26.04 なら 8.5 系。Laravel 13 は 8.3+ でOK）を使うので
# third-party リポジトリ不要。nginx の site 設定まで自動生成する。
#
# 使い方:  sudo bash deploy/setup-server.sh
#
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

if [ "$(id -u)" -ne 0 ]; then
  echo "root で実行してください（sudo bash deploy/setup-server.sh）"; exit 1
fi

APP_DIR=/var/www/portal

echo "==> パッケージ更新 & 基本ツール"
apt-get update -y
apt-get install -y ca-certificates curl unzip git jq

echo "==> nginx / PHP（ディストリ標準）/ 拡張をインストール"
# バージョン無しのメタパッケージにすることで、Ubuntu のバージョンに依らず標準PHPが入る
apt-get install -y nginx \
  php-fpm php-cli php-mbstring php-xml php-curl \
  php-sqlite3 php-bcmath php-zip php-intl php-gd php-mysql

# 実際に入った PHP バージョンと FPM ソケットを検出
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"
echo "==> 検出した PHP: ${PHP_VER}  (socket: ${PHP_SOCK})"

echo "==> Composer"
if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

echo "==> certbot（無料SSL）"
apt-get install -y certbot python3-certbot-nginx

echo "==> nginx の site 設定"
if [ -f /etc/nginx/sites-available/portal ]; then
  echo "    既存設定を検出したため上書きしません（certbot のSSL設定などを保持）。"
else
  echo "    新規に site 設定を生成します。"
cat > /etc/nginx/sites-available/portal <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 20M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX

  ln -sf /etc/nginx/sites-available/portal /etc/nginx/sites-enabled/portal
  rm -f /etc/nginx/sites-enabled/default
fi

echo "==> ファイアウォール(ufw): SSH を先に許可してから有効化"
ufw allow 22/tcp || true
ufw allow 80/tcp || true
ufw allow 443/tcp || true
yes | ufw enable || true

systemctl enable --now "php${PHP_VER}-fpm" nginx
nginx -t && systemctl reload nginx

echo ""
echo "==> 完了。PHP ${PHP_VER} / nginx 準備OK。"
echo "    次は: sudo bash deploy/deploy-app.sh main"
echo "    ※ XServer VPS の『パケットフィルター(Web管理画面)』でも 22/80/443 を開けてください。"
