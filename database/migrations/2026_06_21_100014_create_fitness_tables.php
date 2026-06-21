<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 体重記録（1日1件）
        Schema::create('weight_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->decimal('weight_kg', 5, 2);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'recorded_on']);
        });

        // 運動記録
        Schema::create('exercise_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->string('activity');                 // ランニング / 筋トレ など
            $table->unsignedInteger('minutes');
            $table->unsignedInteger('calories')->nullable();
            $table->timestamps();
        });

        // チャレンジ（期間を決めて競う）
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('metric')->default('weight_loss'); // weight_loss | exercise_minutes
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        // 参加者
        Schema::create('challenge_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['challenge_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_user');
        Schema::dropIfExists('challenges');
        Schema::dropIfExists('exercise_records');
        Schema::dropIfExists('weight_records');
    }
};
