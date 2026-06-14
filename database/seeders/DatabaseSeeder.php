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
        // 称号マスタを投入
        $this->call(TitleSeeder::class);

        // 開発用の管理者アカウント
        User::factory()->create([
            'name' => '管理人',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);
    }
}
