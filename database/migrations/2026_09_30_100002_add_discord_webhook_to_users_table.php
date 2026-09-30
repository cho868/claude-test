<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * タスクの毎日のまとめを送る Discord Webhook（本人ごと）。
 * URL を知っていれば誰でも投稿できてしまうので、暗号化して保存する（モデル側で encrypted キャスト）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('discord_webhook_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discord_webhook_url');
        });
    }
};
