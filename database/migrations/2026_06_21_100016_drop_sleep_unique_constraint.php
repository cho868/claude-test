<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1日に複数回(分割睡眠)を記録できるよう、日付のユニーク制約を外す
        Schema::table('sleep_records', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'sleep_date']);
        });
    }

    public function down(): void
    {
        Schema::table('sleep_records', function (Blueprint $table) {
            $table->unique(['user_id', 'sleep_date']);
        });
    }
};
