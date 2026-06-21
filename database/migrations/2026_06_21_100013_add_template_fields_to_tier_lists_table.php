<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tier_lists', function (Blueprint $table) {
            // テンプレート（項目のみ定義したリスト）かどうか
            $table->boolean('is_template')->default(false)->after('tiers');
            // どのテンプレートから作ったランキングか
            $table->foreignId('template_id')->nullable()->after('is_template')
                ->constrained('tier_lists')->nullOnDelete();
            // 未分類（どのTierにも置いていない）項目を保存する
            $table->json('pool')->nullable()->after('template_id');
        });
    }

    public function down(): void
    {
        Schema::table('tier_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn(['is_template', 'pool']);
        });
    }
};
