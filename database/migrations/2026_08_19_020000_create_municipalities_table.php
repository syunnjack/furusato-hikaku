<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 自治体ごとのふるさと納税の実績（総務省「ふるさと納税に関する現況調査」の公表値）。
     *
     * 返礼品カタログは楽天APIから日々入れ替わるが、こちらは国が年1回公表する確定値で、
     * 受入額・使い道・募集費用といった「どこへ寄附するか」を決める材料になる。
     * 値はすべて公表値のままで、当サイト側の推計は入れない。
     */
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();          // 総務省の団体コード
            $table->string('prefecture', 10)->index();
            $table->string('city', 40)->nullable();       // null は都道府県そのものへの寄附
            $table->unsignedBigInteger('amount')->nullable()->index();
            $table->unsignedBigInteger('count')->nullable();
            $table->unsignedBigInteger('outside_amount')->nullable();
            $table->unsignedBigInteger('outside_count')->nullable();
            $table->unsignedBigInteger('cost_total')->nullable();
            $table->decimal('cost_ratio', 4, 1)->nullable();   // 受入額に占める募集費用の割合（％）
            $table->boolean('reward_provided')->nullable();
            $table->boolean('use_selectable')->default(false); // 寄附の使い道を選べるか
            $table->boolean('use_by_project')->default(false);
            $table->boolean('use_by_field')->default(false);
            $table->unsignedInteger('cf_projects')->nullable();
            $table->unsignedBigInteger('cf_amount')->nullable();
            $table->json('projects')->nullable();          // 特徴的な事業（最大3件）
            $table->json('field_breakdown')->nullable();   // 使い道の分野別内訳
            $table->json('series')->nullable();            // 平成20年度〜令和7年度の推移
            $table->boolean('publish_amount')->default(false);
            $table->boolean('publish_usage')->default(false);
            $table->boolean('publish_progress')->default(false);
            $table->text('donor_relation')->nullable();
            $table->string('onestop_online', 40)->nullable();
            $table->unsignedInteger('national_rank')->nullable()->index();
            $table->unsignedInteger('prefecture_rank')->nullable();
            $table->timestamps();

            $table->index(['prefecture', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
