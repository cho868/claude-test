<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 称号マスタ
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // 称号名
            $table->unsignedBigInteger('required_points'); // 必要ポイント
            $table->string('color')->default('#6b7280');   // バッジ色
            $table->string('icon')->default('🔰');          // アイコン(絵文字)
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // ポイント獲得履歴
        Schema::create('point_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');         // 増減ポイント
            $table->string('reason');          // 理由(login, streak_bonus など)
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_logs');
        Schema::dropIfExists('titles');
    }
};
