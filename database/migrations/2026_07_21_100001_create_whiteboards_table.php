<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 手書きホワイトボード。画像はPNGのdataURLをDBに保存する
 * （オフサイトDBバックアップに含めてVPS全損でも復元できるように）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whiteboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100)->default('無題');
            $table->longText('image_data');          // data:image/png;base64,...
            $table->boolean('is_public')->default(true); // 身内で共有
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whiteboards');
    }
};
