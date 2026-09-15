<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ソシャゲはリセット時刻が 0:00 とは限らない（朝5時が多い）ので
        // ゲームごとにリセットの基準を持たせる。
        Schema::table('game_routines', function (Blueprint $table) {
            $table->string('icon', 8)->nullable()->after('name');       // 見分け用の絵文字
            $table->unsignedTinyInteger('reset_hour')->default(5)->after('icon');   // 日課リセット時刻(0-23)
            $table->unsignedTinyInteger('reset_dow')->default(1)->after('reset_hour'); // 週課リセット曜日(0=日..6=土)
            $table->unsignedTinyInteger('reset_day')->default(1)->after('reset_dow');  // 月課リセット日(1-28)
            $table->boolean('notify')->default(true)->after('reset_day');              // 通知対象にするか
        });

        // 通知の好み。ユーザーごとに「リセットの何時間前に鳴らすか」。
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('routine_notify')->default(true)->after('weekly_exercise_goal');
            $table->unsignedTinyInteger('routine_notify_before')->default(2)->after('routine_notify');
        });

        // Web Push の購読先（1端末=1行）。ほぼ書き込みが発生しないのでSDに優しい。
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();  // endpoint は長いのでハッシュで一意化
            $table->string('p256dh');
            $table->string('auth');
            $table->string('label')->nullable();            // 端末の見分け用
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['routine_notify', 'routine_notify_before']);
        });

        Schema::table('game_routines', function (Blueprint $table) {
            $table->dropColumn(['icon', 'reset_hour', 'reset_dow', 'reset_day', 'notify']);
        });
    }
};
