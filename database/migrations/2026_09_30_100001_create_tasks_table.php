<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * タスク（ソシャゲ以外のToDo）。
 * 既定は「自分のみ」。共有したいものだけ visibility=members にする。
 * 完了は done_at を入れるだけ（履歴テーブルは作らない＝書き込み最小）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('note')->nullable();                     // Markdown のメモ
            $table->date('due_date')->nullable();                 // 期限（無くてもいい）
            $table->string('list', 40)->nullable();               // まとめ用のリスト名（「年末調整」など）
            $table->string('visibility', 20)->default('private'); // private / members
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'done_at', 'due_date']);
            $table->index(['visibility', 'done_at']);
        });

        // 期限の朝に鳴らす時刻（null=通知しない）。日課と同じ cron に相乗りする
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('task_notify_hour')->nullable()->default(8);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('task_notify_hour');
        });
        Schema::dropIfExists('tasks');
    }
};
