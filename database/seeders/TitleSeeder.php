<?php

namespace Database\Seeders;

use App\Models\Title;
use Illuminate\Database\Seeder;

class TitleSeeder extends Seeder
{
    public function run(): void
    {
        $titles = [
            ['name' => '新参者',   'required_points' => 0,    'color' => '#9ca3af', 'icon' => '🔰', 'description' => 'ようこそポータルへ！'],
            ['name' => '見習い',   'required_points' => 50,   'color' => '#22c55e', 'icon' => '🌱', 'description' => '少しずつ慣れてきた'],
            ['name' => '常連',     'required_points' => 150,  'color' => '#3b82f6', 'icon' => '☕', 'description' => '毎日来てるね'],
            ['name' => '古参',     'required_points' => 400,  'color' => '#8b5cf6', 'icon' => '🛡️', 'description' => 'ベテランの風格'],
            ['name' => '主(ぬし)', 'required_points' => 800,  'color' => '#ec4899', 'icon' => '👑', 'description' => 'この場の主'],
            ['name' => '伝説',     'required_points' => 1500, 'color' => '#f59e0b', 'icon' => '🔥', 'description' => '語り継がれる存在'],
            ['name' => '神',       'required_points' => 3000, 'color' => '#ef4444', 'icon' => '⚡', 'description' => '崇めよ'],
        ];

        foreach ($titles as $title) {
            Title::updateOrCreate(['name' => $title['name']], $title);
        }
    }
}
