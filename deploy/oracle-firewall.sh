#!/usr/bin/env bash
#
# Oracle Cloud の Ubuntu イメージ用: OS内 iptables に 80/443 の許可を入れる。
#
# ■ なぜ必要か（Oracleの有名な罠）
#   Oracleの公式Ubuntuイメージには最初から iptables ルールが入っていて、
#   SSH(22)以外の受信を REJECT している。OCIコンソールの「セキュリティリスト」で
#   80/443 を開けても、OS内のこのルールで弾かれて繋がらない。
#   → ここに ACCEPT ルールを追加し、netfilter-persistent で永続化する。
#
# ■ 前提
#   先に OCIコンソール → VCN → セキュリティリスト の Ingress で
#   0.0.0.0/0 の TCP 80 と 443 を許可しておくこと（そちらが外側の壁）。
#
# 使い方:  sudo bash deploy/oracle-firewall.sh
#
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "root で実行してください（sudo bash deploy/oracle-firewall.sh）"; exit 1
fi

# ufw が有効だと二重管理で競合するので、Oracleでは iptables 直管理に寄せる
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
  echo "==> ufw が有効です。Oracleでは iptables 直管理を推奨するため無効化します"
  ufw disable || true
fi

apt-get install -y iptables-persistent netfilter-persistent >/dev/null 2>&1 || true

add_rule() {
  local port="$1"
  # 既に同じ許可があれば追加しない（冪等）
  if iptables -C INPUT -p tcp --dport "$port" -m state --state NEW,ESTABLISHED -j ACCEPT 2>/dev/null; then
    echo "    ${port}/tcp は既に許可済み"
    return
  fi
  # REJECT ルールより前に挿入する必要がある。REJECTの行番号を探して、その直前に入れる。
  local reject_line
  reject_line="$(iptables -L INPUT --line-numbers -n | awk '/REJECT/{print $1; exit}')"
  if [ -n "$reject_line" ]; then
    iptables -I INPUT "$reject_line" -p tcp --dport "$port" -m state --state NEW,ESTABLISHED -j ACCEPT
  else
    iptables -A INPUT -p tcp --dport "$port" -m state --state NEW,ESTABLISHED -j ACCEPT
  fi
  echo "    ${port}/tcp を許可しました"
}

echo "==> iptables に 80 / 443 を許可"
add_rule 80
add_rule 443

echo "==> ルールを永続化（再起動後も維持）"
netfilter-persistent save

echo ""
echo "==> 完了。現在のINPUTルール:"
iptables -L INPUT -n --line-numbers | head -20
echo ""
echo "  確認: 別端末から  curl -I http://<このサーバーのIP>/  で反応があればOK"
echo "  まだ繋がらない場合は OCIコンソールのセキュリティリスト(Ingress 80/443)を確認。"
