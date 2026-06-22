<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // スト6コンボ表機能は廃止
        Schema::dropIfExists('combo_entries');
    }

    public function down(): void
    {
        // 復元しない（機能廃止のため）
    }
};
