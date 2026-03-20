<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('custom_product_artworks', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // 👈 IMPORTANT
            $table->id();
            $table->foreignId('custom_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_product_variant_id')->nullable()->constrained('custom_product_variants')->nullOnDelete();

            $table->string('placement')->nullable();
            $table->string('title')->nullable();
            $table->text('artwork_url')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_product_artworks');
    }
};