<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 家計簿。お金の情報は繊細なので既定は「自分だけ」に見える。
 * is_shared を立てたものだけ身内に共有される（共同購入・割り勘の把握用）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('spent_on');
            $table->unsignedInteger('amount');           // 円（整数）
            $table->string('kind', 10)->default('expense'); // expense / income
            $table->string('category', 20);
            $table->string('memo', 100)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'spent_on']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('monthly_budget')->nullable()->after('weekly_exercise_goal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monthly_budget');
        });
    }
};
