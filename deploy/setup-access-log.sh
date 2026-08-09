#!/usr/bin/env bash
#
# nginxのアクセスログに「本当の訪問者IP」を記録できるようにする（1回だけ実行）。
#   sudo bash deploy/setup-access-log.sh
#
# なぜ必要か:
#   Tailscale Funnel(や他のトンネル/プロキシ)経由だと nginx から見た接続元は
#   127.0.0.1 になり、誰が来たか分からない。実IPは X-Forwarded-For ヘッダに
#   入っているので、ログ書式の末尾に xff="..." として追記する。
#   （標準combined形式の並びは維持するので既存のログ解析ツールも壊れない）
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "root で実行してください（sudo bash deploy/setup-access-log.sh）"; exit 1
fi

SITE=/etc/nginx/sites-available/portal

echo "==> ログ書式(portal)を定義"
cat > /etc/nginx/conf.d/portal-logformat.conf <<'EOF'
log_format portal '$remote_addr - $remote_user [$time_local] "$request" '
                  '$status $body_bytes_sent "$http_referer" "$http_user_agent" '
                  'xff="$http_x_forwarded_for"';
EOF

echo "==> site設定の server ブロックに access_log を設定"
if grep -q "access_log /var/log/nginx/access.log portal;" "$SITE"; then
  echo "    既に設定済み"
else
  sed -i '/server_name/a\    access_log /var/log/nginx/access.log portal;' "$SITE"
  echo "    追記しました"
fi

nginx -t && systemctl reload nginx
echo "==> 完了。以後のアクセスは xff=\"実IP\" 付きで /var/log/nginx/access.log に記録されます"
