<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('shopify_product_id')->nullable()->index();
            $table->string('title');
            $table->string('sku')->nullable()->index();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->integer('stock_quantity')->default(0);
            $table->string('status')->default('active');
            $table->json('options')->nullable();
            $table->json('images')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'shopify_product_id']);
            $table->unique(['shop_id', 'sku']);
            $table->index(['shop_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
