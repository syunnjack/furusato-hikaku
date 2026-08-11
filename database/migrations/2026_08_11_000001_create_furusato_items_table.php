<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('furusato_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 120)->unique();
            $table->text('item_name');
            $table->unsignedInteger('item_price')->default(0)->index();
            $table->text('item_url');
            $table->text('affiliate_url')->nullable();
            $table->text('image_url')->nullable();
            $table->string('shop_name')->nullable();
            $table->string('shop_code')->nullable();
            $table->string('category', 40)->nullable()->index();
            $table->string('prefecture', 10)->nullable()->index();
            $table->string('municipality', 40)->nullable()->index();
            $table->text('catchcopy')->nullable();
            $table->unsignedInteger('review_count')->default(0)->index();
            $table->decimal('review_average', 3, 2)->default(0);
            $table->unsignedSmallInteger('catalog_page')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('furusato_items');
    }
};
