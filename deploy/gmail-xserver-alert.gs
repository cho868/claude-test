/**
 * XServer VPS の期限メールを Gmail で検知して Discord / LINE に強アラートを送る。
 * Google Apps Script (script.google.com) に貼り付けて使う。
 *
 * なぜ Gmail 側でやるか:
 *   VPS 上の監視は「VPSが死ぬと監視も死ぬ」が、これは Google のサーバーで動くので
 *   VPS の生死と無関係に動き続ける。メール本文も読めるので期限日の抽出もできる。
 *
 * 検知対象: 件名「【XServer VPS】■重要■無料サーバーのご利用期限と更新に関するご案内 (サーバー名)」
 *   → このメールの届いた日 ＝ 更新期限日（当日中に更新しないと消える）
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
const SEARCH = 'subject:(XServer VPS ご利用期限) newer_than:5d';

function checkXserverExpiryMail() {
  const props = PropertiesService.getScriptProperties();
  const webhook = props.getProperty('DISCORD_WEBHOOK');
  const lineToken = props.getProperty('LINE_TOKEN');
  const renewUrl = props.getProperty('RENEW_URL') || 'https://secure.xserver.ne.jp/xapanel/login/xvps/';
  if (!webhook && !lineToken) return;

  const threads = GmailApp.search(SEARCH);
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
    const deadline = m ? `${m[1]}/${m[2]}/${m[3]}` : '(本文から抽出できず。メール到着日=期限日の想定)';

    const excerpt = body.replace(/\s+/g, ' ').trim().slice(0, 400);
    const isToday = receivedDay === todayStr;

    const alert = [
      `@everyone 🚨🚨 **XServer VPS の更新期限メールを検知！** 🚨🚨`,
      `📬 受信: ${receivedStr}${isToday ? '（**今日が期限日！今すぐ更新を！**）' : ''}`,
      `⏰ 本文中の日付: ${deadline}`,
      `🔗 更新はこちら → ${renewUrl}`,
      ``,
      `> ${excerpt}`,
      ``,
      `✅ 更新が終わったら、Gmailでこのメールに「${DONE_LABEL}」ラベルを付けるとこの通知は止まります（30分ごとに鳴り続けます）`,
    ].join('\n');

    sendDiscord_(webhook, alert);
    sendLine_(lineToken, alert.replace('@everyone ', ''));
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
