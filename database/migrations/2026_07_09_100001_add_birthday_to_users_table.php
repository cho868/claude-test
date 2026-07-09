<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 誕生日(月/日)。年は集めない(年齢を晒さない身内配慮)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('birth_month')->nullable()->after('avatar_seed');
            $table->unsignedTinyInteger('birth_day')->nullable()->after('birth_month');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_month', 'birth_day']);
        });
    }
};
