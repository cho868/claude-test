/**
 * XServer VPS の期限メール＆no-ip の確認メールを Gmail で検知して
 * Discord / LINE に強アラートを送る。
 * Google Apps Script (script.google.com) に貼り付けて使う。
 *
 * なぜ Gmail 側でやるか:
 *   VPS 上の監視は「VPSが死ぬと監視も死ぬ」が、これは Google のサーバーで動くので
 *   VPS の生死と無関係に動き続ける。メール本文も読めるので期限日の抽出もできる。
 *
 * 検知対象:
 *   1. XServer「【XServer VPS】■重要■無料サーバーのご利用期限と更新に関するご案内」
 *      → このメールの届いた日 ＝ 更新期限日（当日中に更新しないと消える）
 *   2. no-ip の確認メール（confirm/expire）
 *      → 23日目に届く。リンクをクリックするだけで30日延長（IP更新では延長されない！）
 *
 * ※ 既に旧版を設定済みの場合: エディタで中身をこのファイルで置き換えて保存するだけ。
 *   関数名が同じなので登録済みトリガーはそのまま動く（setup の再実行は不要）。
 *
 * 動き:
 *   - 30分ごとにGmailを検索し、該当メールがあれば毎回アラートを送る（＝しつこく鳴る）
 *   - 止めたい時は Gmail でそのメールに「VPS対応済み」ラベルを付ける（スマホからでも可）
 *   - 5日より古いメールは対象外（無限に鳴り続けない保険）
 *
 * ■ セットアップ（5分）
 *   1. https://script.google.com → 「新しいプロジェクト」→ このファイルの中身を全部貼り付け
 *   2. 左メニュー「プロジェクトの設定」→「スクリプト プロパティ」で以下を追加:
 *        DISCORD_WEBHOOK = https://discord.com/api/webhooks/...   （必須。portal-notify と同じでOK）
 *        LINE_TOKEN      = （任意。LINE Messaging API のチャネルアクセストークン）
 *        RENEW_URL       = （任意。XServerの更新ページURL。省略時はログインページ）
 *   3. エディタ上部の関数選択で「setup」を選んで ▶実行（初回はGmail権限の承認が出る→許可）
 *      → 30分ごとのトリガーが登録される
 *   4. 動作確認: 関数「sendTest」を▶実行 → Discordにテスト通知が届けばOK
 */

const DONE_LABEL = 'VPS対応済み';

/**
 * 監視ルール。search に合致するメールが「VPS対応済み」ラベルなしで存在する限り、
 * トリガーのたびに（=30分ごとに）アラートを送り続ける。
 */
function rules_(props) {
  const renewUrl = props.getProperty('RENEW_URL') || 'https://secure.xserver.ne.jp/xapanel/login/xvps/';
  return [
    {
      // XServer VPS の期限メール（到着日＝更新期限日）
      search: 'subject:(XServer VPS ご利用期限) newer_than:5d',
      title: 'XServer VPS の更新期限メールを検知！',
      todayNote: '（**今日が期限日！今すぐ更新を！**）',
      action: `🔗 更新はこちら → ${renewUrl}`,
    },
    {
      // no-ip の確認メール（23日目に届く。クリックだけで30日延長。IP更新では延長されない）
      search: 'from:(noip.com) subject:(confirm OR expire OR expiration) newer_than:8d',
      title: 'no-ip の確認メールを検知！ホスト名の失効が近い！',
      todayNote: '',
      action: '🔗 メール内の確認リンクをクリックするだけで30日延長。完了したら /etc/portal-notify.conf の NOIP_LAST_CONFIRMED を今日の日付に更新',
    },
  ];
}

function checkXserverExpiryMail() {
  const props = PropertiesService.getScriptProperties();
  const webhook = props.getProperty('DISCORD_WEBHOOK');
  const lineToken = props.getProperty('LINE_TOKEN');
  if (!webhook && !lineToken) return;

  for (const rule of rules_(props)) {
    const threads = GmailApp.search(rule.search);
    for (const thread of threads) {
      // 「VPS対応済み」ラベルが付いていたらもう鳴らさない
      if (thread.getLabels().some((l) => l.getName() === DONE_LABEL)) continue;

      const msg = thread.getMessages()[thread.getMessageCount() - 1];
      const received = msg.getDate();
      const receivedStr = Utilities.formatDate(received, 'Asia/Tokyo', 'M/d (E) HH:mm');
      const todayStr = Utilities.formatDate(new Date(), 'Asia/Tokyo', 'yyyy-MM-dd');
      const receivedDay = Utilities.formatDate(received, 'Asia/Tokyo', 'yyyy-MM-dd');

      // 本文から期限らしき日付を拾う（例: 2026年07月10日 / 2026/07/10）
      const body = msg.getPlainBody() || '';
      const m = body.match(/(\d{4})[年\/\-](\d{1,2})[月\/\-](\d{1,2})/);
      const deadline = m ? `${m[1]}/${m[2]}/${m[3]}` : '(本文から抽出できず)';

      const excerpt = body.replace(/\s+/g, ' ').trim().slice(0, 400);
      const isToday = receivedDay === todayStr;

      const alert = [
        `@everyone 🚨🚨 **${rule.title}** 🚨🚨`,
        `📬 受信: ${receivedStr}${isToday ? rule.todayNote : ''}`,
        `⏰ 本文中の日付: ${deadline}`,
        rule.action,
        ``,
        `> ${excerpt}`,
        ``,
        `✅ 対応が終わったら、Gmailでこのメールに「${DONE_LABEL}」ラベルを付けるとこの通知は止まります（30分ごとに鳴り続けます）`,
      ].join('\n');

      sendDiscord_(webhook, alert);
      sendLine_(lineToken, alert.replace('@everyone ', ''));
    }
  }
}

/** 30分ごとのトリガーを登録（初回に1度だけ実行） */
function setup() {
  // 二重登録を防ぐため既存の同名トリガーを消してから作る
  ScriptApp.getProjectTriggers()
    .filter((t) => t.getHandlerFunction() === 'checkXserverExpiryMail')
    .forEach((t) => ScriptApp.deleteTrigger(t));
  ScriptApp.newTrigger('checkXserverExpiryMail').timeBased().everyMinutes(30).create();
  // 「対応済み」ラベルが無ければ作っておく（Gmail側で付けやすいように）
  getOrCreateLabel_(DONE_LABEL);
}

/** テスト送信（Discord/LINE設定の確認用） */
function sendTest() {
  const props = PropertiesService.getScriptProperties();
  const text = '🧪 [テスト] XServer期限メール監視は正常にセットアップされています（Gmail側で30分ごとに監視中）';
  sendDiscord_(props.getProperty('DISCORD_WEBHOOK'), text);
  sendLine_(props.getProperty('LINE_TOKEN'), text);
}

function sendDiscord_(webhook, text) {
  if (!webhook) return;
  UrlFetchApp.fetch(webhook, {
    method: 'post',
    contentType: 'application/json',
    payload: JSON.stringify({ content: text }),
    muteHttpExceptions: true,
  });
}

function sendLine_(token, text) {
  if (!token) return;
  UrlFetchApp.fetch('https://api.line.me/v2/bot/message/broadcast', {
    method: 'post',
    contentType: 'application/json',
    headers: { Authorization: 'Bearer ' + token },
    payload: JSON.stringify({ messages: [{ type: 'text', text: text }] }),
    muteHttpExceptions: true,
  });
}

function getOrCreateLabel_(name) {
  return GmailApp.getUserLabelByName(name) || GmailApp.createLabel(name);
}
