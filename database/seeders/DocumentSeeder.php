<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        // 管理者（いなければ最初のユーザー）を著者にする
        $author = User::where('is_admin', true)->first() ?? User::first();
        if (! $author) {
            return; // ユーザーがいなければスキップ
        }

        // インラインの SSH ガイド（サーバー機密 → 管理者のみ）
        Document::updateOrCreate(
            ['title' => 'VPSへのSSH接続・鍵認証・ユーザー作成'],
            [
                'user_id' => $author->id,
                'category' => 'サーバー',
                'body' => $this->sshGuide(),
                'is_public' => false,
                'visibility' => 'admin',
            ],
        );

        // deploy/*.md をそのまま資料として取り込む（いずれも機密性が高いので管理者のみ）
        $docs = [
            'deploy/DEPLOY.md'   => ['身内ポータル 構築・再構築 手順書', 'サーバー'],
            'deploy/NOTIFY.md'   => ['通知と死活監視（no-ip / SSL / XServer）の設定', '運用'],
            'deploy/SECURITY.md' => ['セキュリティ：懸念点・対策・監視', 'セキュリティ'],
            'deploy/TROUBLESHOOTING.md' => ['トラブル対応ナレッジ（サイトが落ちた時の直し方）', '運用'],
            'docs/ADMIN_API.md' => ['Discord Bot 管理API 仕様（ポータル連携）', '運用'],
        ];

        foreach ($docs as $path => [$title, $category]) {
            $full = base_path($path);
            if (! File::exists($full)) {
                continue;
            }

            Document::updateOrCreate(
                ['title' => $title],
                [
                    'user_id' => $author->id,
                    'category' => $category,
                    'body' => File::get($full),
                    'is_public' => false,
                    'visibility' => 'admin',
                ],
            );
        }
    }

    private function sshGuide(): string
    {
        return <<<'MD'
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
# 保存先は Enter（~/.ssh/id_ed25519）、パスフレーズは任意（付けると安全）
cat ~/.ssh/id_ed25519.pub      # この1行を管理者に渡す（公開鍵）
```

## 3. パスワード接続から鍵接続へ切り替える

```bash
ssh-copy-id taro@203.0.113.45        # Mac/Linux/Git Bash
```
手動なら、サーバー側で：

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "ssh-ed25519 AAAA... 公開鍵 ..." >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

## 4. 新しいユーザーを作る（管理者作業 / root で）

```bash
adduser taro
usermod -aG sudo taro
mkdir -p /home/taro/.ssh
echo "ssh-ed25519 AAAA... 本人の公開鍵 ..." > /home/taro/.ssh/authorized_keys
chown -R taro:taro /home/taro/.ssh
chmod 700 /home/taro/.ssh && chmod 600 /home/taro/.ssh/authorized_keys
```

## 5. （重要）セキュリティ強化

鍵ログインを確認してから、root 直ログインとパスワード認証を無効化：

```bash
sudo grep -ri 'permitrootlogin\|passwordauthentication' /etc/ssh/sshd_config /etc/ssh/sshd_config.d/
# PermitRootLogin no / PasswordAuthentication no に変更後
sudo systemctl restart ssh
```

> 🛟 締め出されても XServer VPS の管理画面コンソール（VNC）から復旧できます。
> 詳しい構築は資料「身内ポータル 構築・再構築 手順書」、守りは「セキュリティ：懸念点・対策・監視」を参照。
MD;
    }
}
