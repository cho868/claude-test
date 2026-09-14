<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 日記。本文は Markdown で保存し、表示時に安全な HTML へ変換する。
 * 既定は「自分のみ」。共有したいものだけ visibility=members にする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');                          // その日の日付（過去日も書ける）
            $table->string('title', 120)->nullable();
            $table->text('body');                                // Markdown
            $table->string('mood', 20)->nullable();              // 気分（絵文字キー）
            $table->string('weather', 20)->nullable();           // 天気（絵文字キー）
            $table->string('visibility', 20)->default('private'); // private / members
            $table->timestamps();

            $table->index(['user_id', 'entry_date']);
            $table->index(['visibility', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diaries');
    }
};
