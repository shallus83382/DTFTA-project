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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain')->unique();
            $table->text('shopify_access_token')->nullable();
            $table->string('shopify_api_version')->default('2025-10');
            $table->text('shopify_scopes')->nullable();

            $table->string('fulfillment_service_id')->nullable();
            $table->string('location_id')->nullable();

            $table->enum('status', ['active', 'suspended', 'uninstalled'])->default('active');

            // Optional but recommended
            $table->string('oauth_state')->nullable();
            $table->timestamp('last_webhook_received_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamp('installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
