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
         Schema::create('webhooks', function (Blueprint $table) {

            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained()->onDelete('cascade');

            $table->string('topic');
            $table->string('shopify_webhook_id')->nullable();

            $table->json('payload');
            $table->boolean('processed')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
