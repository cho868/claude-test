<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 管理するソーシャルゲーム
        Schema::create('game_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 日課・週課・月課のタスク
        Schema::create('routine_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_routine_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('cadence')->default('daily'); // daily | weekly | monthly
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 期間ごとの完了記録（存在＝その期間に完了）
        Schema::create('routine_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_key');                // 例 2026-06-21 / 2026-W25 / 2026-06
            $table->timestamps();
            $table->unique(['routine_task_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_completions');
        Schema::dropIfExists('routine_tasks');
        Schema::dropIfExists('game_routines');
    }
};
