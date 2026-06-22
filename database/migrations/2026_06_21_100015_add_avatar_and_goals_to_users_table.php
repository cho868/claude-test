<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // アバター（アップロード不要・著作権フリー）
            $table->string('avatar_style')->default('emoji')->after('avatar');   // emoji | dicebear
            $table->string('avatar_emoji')->nullable()->after('avatar_style');
            $table->string('avatar_color')->default('#6366f1')->after('avatar_emoji');
            $table->string('avatar_variant')->default('fun-emoji')->after('avatar_color'); // DiceBear スタイル
            $table->string('avatar_seed')->nullable()->after('avatar_variant');

            // ソロ用フィットネス目標
            $table->decimal('target_weight_kg', 5, 2)->nullable()->after('avatar_seed');
            $table->unsignedInteger('weekly_exercise_goal')->nullable()->after('target_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_style', 'avatar_emoji', 'avatar_color', 'avatar_variant', 'avatar_seed',
                'target_weight_kg', 'weekly_exercise_goal',
            ]);
        });
    }
};
