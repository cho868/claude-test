<?php

namespace Database\Seeders;

use App\Models\SetupTask;
use Illuminate\Database\Seeder;

class SetupTaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            // セキュリティ
            ['key' => 'ssh_key_only',   'category' => 'セキュリティ', 'title' => 'SSHを鍵認証のみに（パスワード認証を無効化）', 'description' => '/etc/ssh/sshd_config(.d) の PasswordAuthentication no'],
            ['key' => 'ssh_no_root',    'category' => 'セキュリティ', 'title' => 'root の直接SSHログインを無効化', 'description' => 'PermitRootLogin no → sudo systemctl restart ssh'],
            ['key' => 'fail2ban',       'category' => 'セキュリティ', 'title' => 'fail2ban / 自動更新 / セキュリティヘッダを導入', 'description' => 'sudo bash deploy/harden-server.sh'],
            ['key' => 'invite_code',    'category' => 'セキュリティ', 'title' => '新規登録の招待コードを設定', 'description' => '.env の REGISTRATION_INVITE_CODE。野良登録を防止'],
            // 運用
            ['key' => 'notify_setup',   'category' => '運用', 'title' => '通知・死活監視を設定（Discord/healthchecks + cron）', 'description' => 'deploy/NOTIFY.md。3つの期限を毎日通知'],
            ['key' => 'backup_cron',    'category' => '運用', 'title' => 'DBの定期バックアップ(cron)を設定', 'description' => 'SQLite を毎日コピー（DEPLOY.md）'],
            ['key' => 'trial_date',     'category' => '運用', 'title' => '体験終了日を控えて通知に設定', 'description' => '/etc/portal-notify.conf の TRIAL_END'],
            ['key' => 'noip_confirm',   'category' => '運用', 'title' => 'no-ip の30日確認を運用に組み込む', 'description' => '毎日のDiscord通知でリマインド'],
        ];

        foreach ($tasks as $i => $task) {
            SetupTask::updateOrCreate(
                ['key' => $task['key']],
                [
                    'category' => $task['category'],
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'sort_order' => $i,
                    // done は既存値を尊重（updateOrCreate の更新側に含めない）
                ],
            );
        }
    }
}
