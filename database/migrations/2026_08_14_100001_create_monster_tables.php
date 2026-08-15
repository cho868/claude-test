<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * モンスター機能。SDカードへの書き込みを最小化する設計:
 *  - 個人モンスターの能力値は保存せず、既存の points / total_logins / login_streak から都度計算
 *  - みんなのボスの進捗も保存せず、既存の point_logs を集計して算出
 *  - バトルは「シード値と勝敗」のみ保存し、戦闘ログはシードから再生成（保存しない）
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1ユーザー1体。作成時に1回書くだけ。
        Schema::create('monsters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('species', 20);
            $table->string('name', 40);
            $table->timestamps();
        });

        // 1バトル1行（数十バイト）。戦闘ログは持たない。
        Schema::create('monster_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('seed');
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('turns')->default(0);
            $table->timestamps();
            $table->index(['challenger_id', 'created_at']);
        });

        // 週替わりの共有ボス。週に1行だけ。
        Schema::create('raid_bosses', function (Blueprint $table) {
            $table->id();
            $table->string('week', 10)->unique();     // 例: 2026-W33
            $table->string('name', 40);
            $table->string('species', 20);
            $table->unsignedInteger('total_hp');
            $table->timestamp('defeated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monster_battles');
        Schema::dropIfExists('raid_bosses');
        Schema::dropIfExists('monsters');
    }
};
