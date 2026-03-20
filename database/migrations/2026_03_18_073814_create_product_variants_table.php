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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // 👈 IMPORTANT

            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique(); // DTFTA-APP-TEE-NL-6210-BLK-XL
            $table->string('color');
            $table->string('size');
            $table->boolean('is_active')->default(true);
        
            $table->unique(['product_id', 'color', 'size']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
