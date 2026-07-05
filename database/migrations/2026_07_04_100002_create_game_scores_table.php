<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ミニゲームのスコア記録。score はミリ秒（小さいほど良い）で統一。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 32);              // reaction / numbers など
            $table->unsignedInteger('score');        // ミリ秒
            $table->timestamps();
            $table->index(['game', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_scores');
    }
};
