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
         Schema::create('fulfillment_services', function (Blueprint $table) {

            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');

            $table->string('shopify_fulfillment_service_id')->nullable();
            $table->string('name');
            $table->string('shopify_location_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_services');
    }
};
