<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_user_id')->constrained()->onDelete('cascade');
            $table->string('keyword');
            // 前回チェック時に検索結果に出ていたitemCodeの一覧。
            // 新しく出現したitemCodeを「新着・再登場」として通知する。
            $table->json('seen_item_codes')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['line_user_id', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watches');
    }
};
