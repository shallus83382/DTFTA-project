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
        Schema::create('partner_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete()
                ->unique();

            $table->string('brand_name')->nullable();
            $table->string('return_address_street')->nullable();
            $table->string('return_address_city')->nullable();
            $table->string('return_address_state')->nullable();
            $table->string('return_address_zip')->nullable();
            $table->string('return_address_country')->default('US');

            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();

            // Optional
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
