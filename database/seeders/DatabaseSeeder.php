<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 称号マスタ・セットアップチェックリストを投入（常に）
        $this->call(TitleSeeder::class);
        $this->call(SetupTaskSeeder::class);

        // 開発用の管理者アカウントはローカルのみ（本番では最初の登録者が管理者になる）
        if (app()->environment('local')) {
            User::firstOrCreate(
                ['username' => 'admin'],
                ['name' => '管理人', 'password' => 'password', 'is_admin' => true],
            );
        }

        // 初期資料（ユーザーがいれば投入。本番は登録後に
        //   php artisan db:seed --class=Database\\Seeders\\DocumentSeeder で追加可）
        $this->call(DocumentSeeder::class);
    }
}
