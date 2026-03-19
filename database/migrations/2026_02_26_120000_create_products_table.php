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
            $table->engine = 'InnoDB'; // 👈 IMPORTANT

            $table->id();
            $table->string('title');
            $table->string('brand')->nullable(); // Next Level
            $table->string('model_code')->nullable(); // 6210
            $table->string('category')->nullable(); // T-Shirt
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->json('images')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
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
