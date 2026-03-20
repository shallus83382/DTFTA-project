<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_products', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // 👈 IMPORTANT
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_key')->nullable()->index();
            $table->string('title');
            $table->string('vendor')->nullable();
            $table->string('product_type')->nullable();
            $table->string('status')->default('DRAFT');

            $table->string('shopify_product_id')->nullable()->index();
            $table->json('tags')->nullable();
            $table->longText('description_html')->nullable();
            $table->json('options')->nullable();
            $table->json('print_areas')->nullable();
            $table->json('print_plan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_products');
    }
};