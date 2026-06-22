<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // スト6 コンボ表（始動 × ヒット状況）
        Schema::create('combo_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('character');
            $table->string('starter');                  // 始動技/状況（例: 屈中P, DI）
            $table->string('hit_type')->default('normal'); // normal | counter | punish
            $table->text('combo');                       // コンボ表記
            $table->string('damage')->nullable();
            $table->string('note')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_entries');
    }
};
