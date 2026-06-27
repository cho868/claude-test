# Discord Bot 管理API 仕様（ポータル連携）

ポータルの **管理 → 🤖 Bot設定**（`/admin/bot`、管理者のみ）から、Bot の設定を**再起動なしで**変更できます。
画面の裏側では、ポータル(PHP)が**サーバー側で** Bot の管理API（同一VPSの `http://localhost:3000`）を中継しています。
**認証キー（ADMIN_KEY）はサーバーの `.env` にのみ保持し、ブラウザには一切出しません。**

## ポータル側の設定
`.env`（サーバー）に以下を設定し `php artisan config:cache`:
```
BOT_ADMIN_URL=http://localhost:3000
BOT_ADMIN_KEY=（Bot側の ADMIN_KEY と同じ値）
```

## Bot 側API（参考）
- `GET /admin/settings` … 現在の設定を取得（ヘッダ `x-admin-key`）
- `POST /admin/settings` … 部分更新（ディープマージ）。送ったキーだけ上書き
- 認証: ヘッダ `x-admin-key: <ADMIN_KEY>`。未設定だと `/admin/*` は 403
- `:3000` は **localhost からのみ**（外部公開しない）

## 編集できる項目（フェーズ1）
| パス | 種類 | 反映 |
| --- | --- | --- |
| `activity.name` | 文字列（ステータス文言） | 即時 |
| `activity.type` | `PLAYING`/`STREAMING`/`LISTENING`/`WATCHING`/`COMPETING` | 即時 |
| `features.*` | 真偽（homecoming / youtubeReaction / attachmentReaction / games / tabelog / onsen / coin / voiceNotify / gas） | 次イベント |
| `messages.*` | 文字列（wakeup / vcOpen / unknownCommand） | 次回送信時 |

> ID（チャンネル等）の編集は影響が大きいため**フェーズ2**（スキーマに `ids` を追加予定）。

## 権限
ポータルの**管理者**のみアクセス可（`/admin/*` は admin ミドルウェアで保護）。
「Discord管理権限を持つメンバー」を管理者にしておくことで、GitHubアカウントが無くてもブラウザから編集できます。
（管理者の付与: 管理 → ユーザー管理、または `php artisan portal:make-admin <email>`）
