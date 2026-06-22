<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 対戦ゲームの戦績（手動入力）
        Schema::create('match_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game');                       // スマブラ / ストVI など
            $table->string('result');                     // win | loss | draw
            $table->string('opponent')->nullable();       // 相手(自由入力)
            $table->string('score')->nullable();          // スコア(例 2-1)
            $table->date('played_on');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_records');
    }
};
