#!/usr/bin/env bash
#
# サーバーのセキュリティ強化（任意・推奨）。
#   - fail2ban: SSH への総当たりを自動BAN
#   - unattended-upgrades: セキュリティ更新を自動適用
#   - nginx セキュリティヘッダを配置
#
# 使い方:  sudo bash deploy/harden-server.sh
# ※ 既存の nginx サイト設定（certbot のSSL設定含む）は変更しません。
#
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

if [ "$(id -u)" -ne 0 ]; then
  echo "root で実行してください（sudo bash deploy/harden-server.sh）"; exit 1
fi

HERE="$(cd "$(dirname "$0")" && pwd)"

echo "==> fail2ban / unattended-upgrades をインストール"
apt-get update -y
apt-get install -y fail2ban unattended-upgrades

echo "==> fail2ban: SSH ジェイルを有効化"
cat > /etc/fail2ban/jail.local <<'CONF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
CONF
systemctl enable --now fail2ban
systemctl restart fail2ban

echo "==> セキュリティ自動更新を有効化"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
CONF

echo "==> nginx セキュリティヘッダを配置"
if [ -f "${HERE}/security-headers.conf" ]; then
  cp "${HERE}/security-headers.conf" /etc/nginx/conf.d/security-headers.conf
  nginx -t && systemctl reload nginx
fi

echo ""
echo "==> 完了。状態確認:"
echo "    sudo fail2ban-client status sshd     # BAN状況"
echo "    curl -I https://localhost -k         # セキュリティヘッダ確認"
