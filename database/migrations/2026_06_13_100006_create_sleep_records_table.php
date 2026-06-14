<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 睡眠時間チェック
        Schema::create('sleep_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('sleep_date');                 // 起床した日
            $table->dateTime('bed_at');                 // 就寝
            $table->dateTime('wake_at');                // 起床
            $table->unsignedInteger('duration_minutes'); // 睡眠分数(自動計算)
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sleep_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sleep_records');
    }
};
