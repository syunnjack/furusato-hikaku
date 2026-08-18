<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * その自治体に住む人がふるさと納税をした側の数字（総務省の課税状況調べ）。
     * 受け入れた額だけでは、地域にとっての出入りが分からないため合わせて持つ。
     */
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->unsignedInteger('deduction_people')->nullable();      // 控除を受けた住民の数
            $table->unsignedBigInteger('deduction_donation')->nullable(); // 住民が寄附した額
            $table->unsignedBigInteger('deduction_amount')->nullable();   // 住民税から控除された額
            $table->unsignedInteger('onestop_people')->nullable();
            $table->unsignedBigInteger('onestop_donation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropColumn([
                'deduction_people', 'deduction_donation', 'deduction_amount',
                'onestop_people', 'onestop_donation',
            ]);
        });
    }
};
