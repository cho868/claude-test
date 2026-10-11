#!/usr/bin/env bash
#
# 「本当に侵入されていないか」を1コマンドで確認する点検スクリプト（読み取り専用・何も変更しない）。
#
#   sudo bash /var/www/portal/deploy/security-check.sh
#   sudo bash /var/www/portal/deploy/security-check.sh --ip 20.219.38.82   # 特定IPの全行動を見る
#
# 考え方:
#   ボットのスキャン(.env や wp-login への404)は公開サーバーに常時来るノイズであって被害ではない。
#   本当に見るべきは「**何かが成功したか**」の一点。この順で確認する:
#     ① 知らないユーザーが登録されていないか   ← 侵入されたら必ずここに出る
#     ② ファイルが改ざんされていないか         ← git管理なので差分でわかる
#     ③ 秘密ファイルが外から取れないか         ← 実際にcurlで叩いて確認
#     ④ 攻撃っぽいパスが2xxを返していないか
#     ⑤ ログインに成功したIPは身内だけか
#     ⑥ SSHに他人が入っていないか
set -uo pipefail

APP_DIR="${APP_DIR:-/var/www/portal}"
LOG="${ACCESS_LOG:-/var/log/nginx/access.log}"
TARGET_IP=""
[ "${1:-}" = "--ip" ] && TARGET_IP="${2:-}"

SCAN_PATTERN='wp-login|wp-admin|wp-content|\.env|phpmyadmin|\.git|xmlrpc|cgi-bin|\.asp|eval\(|/vendor/|\.sql|secrets|api_keys|credentials|id_rsa|\.aws|\.ssh'

NG=0   # 1つでも問題が見つかったら1になる

hr() { printf '\n\033[1m%s\033[0m\n' "── $* ─────────────────────────────"; }
ok() { printf '  \033[32m✅ %s\033[0m\n' "$*"; }
bad() { printf '  \033[31m🚨 %s\033[0m\n' "$*"; NG=1; }
note() { printf '  ℹ️  %s\n' "$*"; }

# アクセスログ本体（ローテーション分も含める）
all_logs() {
  cat "$LOG" 2>/dev/null
  cat "${LOG}.1" 2>/dev/null
  zcat "${LOG}".*.gz 2>/dev/null
}

# 実IPを取り出す（Funnel経由だと $remote_addr は127.0.0.1になるので xff を優先）
extract_ip() {
  sed -n 's/.*xff="\([^",]*\)[",].*/\1/p; t; s/^\([0-9a-fA-F.:]*\) .*/\1/p' | sed 's/^-$/local/'
}

printf '\033[1m🛡  身内ポータル セキュリティ点検\033[0m  %s\n' "$(date '+%Y-%m-%d %H:%M')"

# ===== --ip: 指定IPが何をしたか全部出す =====
if [ -n "$TARGET_IP" ]; then
  hr "$TARGET_IP の全行動"
  HITS="$(all_logs | grep -F "$TARGET_IP" || true)"
  if [ -z "$HITS" ]; then
    note "このIPのアクセスはログにありません"; exit 0
  fi
  echo "  総リクエスト: $(printf '%s\n' "$HITS" | grep -c .) 件"
  echo "  期間: $(printf '%s\n' "$HITS" | head -1 | awk '{print $4}' | tr -d '[') 〜 $(printf '%s\n' "$HITS" | tail -1 | awk '{print $4}' | tr -d '[')"
  echo "  User-Agent:"
  printf '%s\n' "$HITS" | awk -F'"' '{print $6}' | sort | uniq -c | sort -rn | head -3 | sed 's/^/    /'
  echo "  ステータス別:"
  printf '%s\n' "$HITS" | awk '{print $9}' | sort | uniq -c | sort -rn | sed 's/^/    /'
  echo "  ★ 2xx(成功)を返したリクエスト:"
  SUCCESS="$(printf '%s\n' "$HITS" | awk '$9 ~ /^2/ {print $9, $6, $7}' | tr -d '"' | sort | uniq -c | sort -rn)"
  if [ -z "$SUCCESS" ]; then
    ok "1件もありません（＝何も渡していない。完全に弾けている）"
  else
    printf '%s\n' "$SUCCESS" | head -30 | sed 's/^/    /'
    note "公開ページ(/login など)に200が出るのは正常です"
  fi
  exit 0
fi

# ===== ① 登録ユーザー（最重要） =====
hr "① 登録ユーザー一覧（知らない名前があれば侵入）"
DB="$(grep -E '^DB_DATABASE=' "$APP_DIR/.env" 2>/dev/null | cut -d= -f2- | tr -d "\"' " )"
[ -z "$DB" ] && DB="$APP_DIR/database/database.sqlite"
USER_SQL="SELECT id || ' | ' || username || ' | ' || name || ' | admin=' || is_admin || ' | 登録:' || substr(created_at,1,16) FROM users ORDER BY id;"
if [ ! -f "$DB" ]; then
  note "DBファイルが見つかりません（DB=$DB）"
elif command -v sqlite3 >/dev/null; then
  sqlite3 -readonly "$DB" "$USER_SQL" 2>/dev/null | sed 's/^/  /'
  note "合計 $(sqlite3 -readonly "$DB" 'SELECT COUNT(*) FROM users;' 2>/dev/null)人 / 管理者 $(sqlite3 -readonly "$DB" 'SELECT COUNT(*) FROM users WHERE is_admin=1;' 2>/dev/null)人"
  note "★ここに見覚えのない行が無ければ、誰も入れていません"
elif command -v php >/dev/null; then
  # sqlite3コマンドが無い環境向けのフォールバック（Laravelサーバーには必ずPHPがある）
  php -r '$d=new PDO("sqlite:".$argv[1]); $d->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $n=0; $a=0;
    foreach($d->query("SELECT id,username,name,is_admin,created_at FROM users ORDER BY id") as $r){
      $n++; $a += (int)$r["is_admin"];
      printf("  %s | %s | %s | admin=%s | 登録:%s\n",$r["id"],$r["username"],$r["name"],$r["is_admin"],substr((string)$r["created_at"],0,16));
    }
    printf("  ℹ️  合計 %d人 / 管理者 %d人\n",$n,$a);' "$DB" 2>/dev/null \
    || note "DBを読めませんでした（DB=$DB）"
  note "★ここに見覚えのない行が無ければ、誰も入れていません"
else
  note "sqlite3 も php も無いため確認できません（DB=$DB）"
fi

# ===== ② ファイル改ざん =====
hr "② ファイルの改ざんチェック"
if [ -d "$APP_DIR/.git" ]; then
  DIFF="$(git -C "$APP_DIR" status --porcelain 2>/dev/null | grep -vE '^\?\? (storage/|bootstrap/cache/|\.env)' || true)"
  if [ -z "$DIFF" ]; then
    ok "git管理下のファイルに変更なし（＝ソースは改ざんされていない）"
  else
    bad "git管理下のファイルに差分があります:"
    printf '%s\n' "$DIFF" | head -20 | sed 's/^/     /'
    note "自分で編集した覚えがなければ要調査: git -C $APP_DIR diff"
  fi
  note "現在のコミット: $(git -C "$APP_DIR" log -1 --format='%h %s' 2>/dev/null)"
else
  note "$APP_DIR はgitリポジトリではないのでスキップ"
fi
# 公開ディレクトリに見慣れないPHPが置かれていないか（Webshell対策）
EXTRA_PHP="$(find "$APP_DIR/public" -name '*.php' ! -name 'index.php' 2>/dev/null)"
if [ -z "$EXTRA_PHP" ]; then
  ok "public/ に index.php 以外のPHPは無し（Webshell無し）"
else
  bad "public/ に余計なPHPがあります:"; printf '%s\n' "$EXTRA_PHP" | sed 's/^/     /'
fi

# ===== ③ 秘密ファイルが外から取れないか（実際に叩いて確認） =====
hr "③ 秘密ファイルの露出テスト（実際にHTTPで取得を試す）"
for p in /.env /.env.txt /storage/logs/laravel.log /.git/config /composer.json \
         /database/database.sqlite /secrets.json /api_keys.txt /phpinfo.php; do
  code="$(curl -s -o /dev/null -w '%{http_code}' -m 5 "http://localhost${p}" 2>/dev/null || true)"
  case "$code" in
    403|404|410) printf '  ✅ %-32s → %s（弾いている）\n' "$p" "$code" ;;
    200)         bad "$p → 200 ★中身を渡してしまっています！ 即対処が必要" ;;
    000|'')      printf '  ℹ️  %-32s → 接続不可（nginxが停止している可能性）\n' "$p" ;;
    *)           printf '  ℹ️  %-32s → %s\n' "$p" "$code" ;;
  esac
done

# ===== ④ 攻撃パスが成功していないか =====
hr "④ 攻撃っぽいパスが2xxを返していないか（ここが本番）"
SCAN_OK="$(all_logs | grep -EI "$SCAN_PATTERN" | awk '$9 ~ /^2/' || true)"
SCAN_ALL=$(all_logs | grep -cEI "$SCAN_PATTERN" || true)
if [ -z "$SCAN_OK" ]; then
  ok "スキャン ${SCAN_ALL}件すべて失敗（2xxは0件）＝実害ゼロ"
else
  bad "スキャンが成功しています:"; printf '%s\n' "$SCAN_OK" | head -20 | sed 's/^/     /'
fi

hr "④-2 サーバーが実際に200/300系を返したパス（全期間）"
note "ここに身内ポータルのページ以外が出ていなければクリーンです"
all_logs | awk '$9 ~ /^[23]/ {print $7}' | sed 's/?.*//' | sort | uniq -c | sort -rn | head -25 | sed 's/^/  /'

# ===== ⑤ ログイン成功したIP =====
hr "⑤ ログインの試行と成功"
LOGIN_TRY=$(all_logs | grep -c '"POST /login' || true)
LOGIN_OK="$(all_logs | grep '"POST /login' | awk '$9 == 302' | extract_ip | sort | uniq -c | sort -rn || true)"
note "ログイン試行 合計 ${LOGIN_TRY}件（302=成功 / 200か422=失敗して戻された）"
if [ -z "$LOGIN_OK" ]; then
  note "成功したログインはありません"
else
  echo "  成功したIP:"; printf '%s\n' "$LOGIN_OK" | sed 's/^/    /'
  note "★ここに身内以外のIPが無いか確認してください"
fi
REG=$(all_logs | grep '"POST /register' | awk '$9 == 302' | grep -c . || true)
note "新規登録の成功: ${REG}件（①のユーザー一覧と一致するか）"

# ===== ⑥ SSH =====
hr "⑥ SSHログイン履歴"
if command -v last >/dev/null; then
  echo "  直近の成功ログイン:"; last -n 8 2>/dev/null | head -8 | sed 's/^/    /'
  FAILED=$(lastb 2>/dev/null | grep -c . || true)   # grep -c は常に数を出すので || true でよい（|| echo 0 だと2行になる）
  if [ "$FAILED" -eq 0 ]; then ok "失敗ログインの記録なし"; else note "失敗ログイン ${FAILED}件（公開していないので通常は0）"; fi
fi
note "SSHはtailnet/LANのみ公開。インターネットには出ていません"

# ===== ⑦ Laravelのエラー =====
hr "⑦ アプリのエラーログ（直近）"
LLOG="$APP_DIR/storage/logs/laravel.log"
if [ -f "$LLOG" ]; then
  ERRS=$(grep -c 'ERROR' "$LLOG" 2>/dev/null || true)
  note "ERROR行: ${ERRS}件 / サイズ $(du -h "$LLOG" | cut -f1)"
  [ "$ERRS" -gt 0 ] && grep 'ERROR' "$LLOG" | tail -3 | cut -c1-160 | sed 's/^/    /'
else
  ok "エラーログなし"
fi

# ===== ⑧ 上位アクセス元 =====
hr "⑧ アクセス元IP TOP10（全期間）"
all_logs | extract_ip | sort | uniq -c | sort -rn | head -10 | sed 's/^/  /'
note "気になるIPは: sudo bash $0 --ip <IPアドレス>"

# ===== 総合判定 =====
echo
if [ "$NG" -eq 0 ]; then
  printf '\033[42;30m 総合判定: 問題は見つかりませんでした \033[0m\n'
  echo "  スキャンは来ていますが、すべて弾いています。被害はありません。"
else
  printf '\033[41;37m 総合判定: 要確認の項目があります（上の🚨を見てください） \033[0m\n'
fi
