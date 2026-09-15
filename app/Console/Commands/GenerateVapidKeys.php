<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Web Push 用の VAPID 鍵ペアを生成して .env に貼る内容を表示する';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->newLine();
        $this->info('以下を .env に貼り付けて `php artisan config:cache` してください。');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->warn('秘密鍵は再発行すると全端末の購読が無効になります。バックアップを取ってください。');

        return self::SUCCESS;
    }
}
