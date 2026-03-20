<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_product_variants', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // 👈 IMPORTANT
            $table->id();
            $table->foreignId('custom_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('title')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable();

            $table->string('shopify_variant_id')->nullable()->index();
            $table->json('option_values')->nullable();
            $table->json('source_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_product_variants');
    }
};