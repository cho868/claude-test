<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'portal:make-admin {email} {--revoke : 管理者権限を剥奪する}';

    protected $description = 'ユーザーを管理者に任命する（--revoke で剥奪）';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("ユーザーが見つかりません: {$this->argument('email')}");

            return self::FAILURE;
        }

        $makeAdmin = ! $this->option('revoke');
        $user->update(['is_admin' => $makeAdmin]);

        $this->info("{$user->name} <{$user->email}> を" . ($makeAdmin ? '管理者にしました。' : '一般ユーザーにしました。'));

        return self::SUCCESS;
    }
}
