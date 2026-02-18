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
        Schema::create('order_items', function (Blueprint $table) {

            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            $table->string('shopify_line_item_id')->nullable();
            $table->string('sku')->nullable();

            $table->string('dtfta_type')->nullable(); // APPAREL_POD

            $table->integer('quantity')->default(1);

            $table->json('properties')->nullable(); // artwork, print plan etc

            $table->string('status')->default('pending'); 
            // pending, printing, shipped, cancelled

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
