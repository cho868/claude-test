<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('points')->default(0)->after('password');
            $table->unsignedInteger('login_streak')->default(0)->after('points');
            $table->unsignedInteger('total_logins')->default(0)->after('login_streak');
            $table->date('last_login_date')->nullable()->after('total_logins');
            $table->foreignId('title_id')->nullable()->after('last_login_date');
            $table->string('avatar')->nullable()->after('title_id');
            $table->string('discord_id')->nullable()->after('avatar');
            $table->string('steam_id')->nullable()->after('discord_id');
            $table->boolean('is_admin')->default(false)->after('steam_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'points', 'login_streak', 'total_logins', 'last_login_date',
                'title_id', 'avatar', 'discord_id', 'steam_id', 'is_admin',
            ]);
        });
    }
};
