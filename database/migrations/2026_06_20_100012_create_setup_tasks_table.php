<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 構築・セキュリティの ToDo チェックリスト（管理画面で進捗を見える化）
        Schema::create('setup_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // 識別子（シードの再投入で重複しないように）
            $table->string('category')->default('一般'); // セキュリティ / 運用 など
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('done')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_tasks');
    }
};
