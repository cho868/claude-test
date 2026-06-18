<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 資料 / ナレッジ（Markdown 記事）
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('一般');  // カテゴリ（サーバー/開発 など）
            $table->string('title');
            $table->longText('body');                       // Markdown 本文
            $table->boolean('is_public')->default(true);    // 身内に公開
            $table->unsignedInteger('views')->default(0);   // 閲覧数
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
