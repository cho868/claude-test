<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ゲームプレイ時間(Discord / Steam 連携 or 手動入力)
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game_name');
            $table->unsignedInteger('minutes');          // プレイ時間(分)
            $table->date('played_on');
            $table->string('source')->default('manual');  // manual / steam / discord
            $table->string('external_id')->nullable();    // Steam appid など
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
