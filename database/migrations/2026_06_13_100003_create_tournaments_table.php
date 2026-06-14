<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('format')->default('single'); // single / double
            $table->text('description')->nullable();
            // 参加者・対戦表(ブラケット)を JSON で保持
            $table->json('participants')->nullable();
            $table->json('bracket')->nullable();
            $table->string('status')->default('draft');   // draft / ongoing / finished
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
