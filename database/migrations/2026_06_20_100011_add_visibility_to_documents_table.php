<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // members = ログイン済みの身内全員 / admin = 管理者のみ / private = 著者(と管理者)
            $table->string('visibility')->default('members')->after('is_public');
        });

        // 既存データ: is_public=false は private、true は members に寄せる
        DB::table('documents')->where('is_public', false)->update(['visibility' => 'private']);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
