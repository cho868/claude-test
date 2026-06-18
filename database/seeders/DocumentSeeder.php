<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        // 管理者（いなければ最初のユーザー）を著者にする
        $author = User::where('is_admin', true)->first() ?? User::first();
        if (! $author) {
            return; // ユーザーがいなければスキップ
        }

        $sshGuide = <<<'MD'
身内ポータルを動かしている **XServer VPS** に SSH 接続する方法と、鍵認証・ユーザー作成の手順をまとめます。
新メンバーはまずこれを読んでください。

## 1. VS Code から SSH 接続する

1. 拡張機能で **「Remote - SSH」**（Microsoft / `ms-vscode-remote.remote-ssh`）をインストール
2. コマンドパレット（`Ctrl/Cmd + Shift + P`）→ **Remote-SSH: Connect to Host...**
3. `ユーザー名@サーバーのIP` を入力（例 `taro@203.0.113.45`）
4. プラットフォームは **Linux** を選択

毎回ラクにするなら `~/.ssh/config` に登録します。

```sshconfig
Host xserver-portal
    HostName 203.0.113.45
    User taro
    IdentityFile ~/.ssh/id_ed25519
```

## 2. SSH 鍵を作る

```bash
ssh-keygen -t ed25519
# 保存先は Enter（~/.ssh/id_ed25519）、パスフレーズは任意
```

公開鍵（`~/.ssh/id_ed25519.pub`）の中身を管理者に渡すと、サーバーに登録してもらえます。

```bash
cat ~/.ssh/id_ed25519.pub      # この1行を渡す
```

## 3. パスワード接続から鍵接続へ切り替える

パスワードでログインできる状態なら、サーバーを作り直さずに鍵を追加できます。

```bash
# 手元のPCから（Mac/Linux/Git Bash）
ssh-copy-id taro@203.0.113.45
```

`ssh-copy-id` が無ければ、サーバー側で手動登録：

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "ssh-ed25519 AAAA... 公開鍵 ..." >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

## 4. 新しいユーザーを作る（管理者作業）

root で実行します。

```bash
adduser taro                 # パスワードを設定
usermod -aG sudo taro        # sudo 権限を付与

# 各自の公開鍵を登録
mkdir -p /home/taro/.ssh
echo "ssh-ed25519 AAAA... 本人の公開鍵 ..." > /home/taro/.ssh/authorized_keys
chown -R taro:taro /home/taro/.ssh
chmod 700 /home/taro/.ssh && chmod 600 /home/taro/.ssh/authorized_keys
```

## 5. （任意）セキュリティ強化

鍵ログインを確認してから、root 直ログインとパスワード認証を無効化：

```bash
sudo grep -ri 'permitrootlogin\|passwordauthentication' /etc/ssh/sshd_config /etc/ssh/sshd_config.d/
# PermitRootLogin no / PasswordAuthentication no に変更後
sudo systemctl restart ssh
```

> 🛟 締め出されても XServer VPS の管理画面コンソール（VNC）から復旧できます。

---

サーバー構築そのものの手順はリポジトリの `deploy/DEPLOY.md`、通知設定は `deploy/NOTIFY.md` を参照してください。
MD;

        Document::updateOrCreate(
            ['title' => 'VPSへのSSH接続・鍵認証・ユーザー作成'],
            [
                'user_id' => $author->id,
                'category' => 'サーバー',
                'body' => $sshGuide,
                'is_public' => true,
            ],
        );
    }
}
