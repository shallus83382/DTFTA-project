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
       Schema::create('orders', function (Blueprint $table) {

            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');

            $table->string('shopify_order_id')->index();
            $table->string('order_number')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();

            $table->string('status')->default('pending'); 
            // pending, in_production, shipped, cancelled, exception

            $table->decimal('total_price', 10, 2)->nullable();

            $table->json('raw_data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
