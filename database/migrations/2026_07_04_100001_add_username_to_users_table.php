<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * メール認証なし運用のため、ログインをメールアドレス→ログインID(username)に変更。
 * email は既存データ保持のため残すが任意項目にする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // 既存ユーザーは email のローカル部を初期IDにする（重複時は連番付与）
        $used = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $u) {
            $base = preg_replace('/[^A-Za-z0-9_\-]/', '', explode('@', (string) $u->email)[0]) ?: 'user' . $u->id;
            $name = $base;
            $i = 1;
            while (in_array(strtolower($name), $used, true)) {
                $name = $base . '_' . (++$i);
            }
            $used[] = strtolower($name);
            DB::table('users')->where('id', $u->id)->update(['username' => $name]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
