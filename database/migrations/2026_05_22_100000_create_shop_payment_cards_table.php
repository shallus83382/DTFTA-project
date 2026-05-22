<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('shops', 'square_customer_id')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('square_customer_id')->nullable()->after('billing_blocked_reason');
            });
        }

        if (!Schema::hasTable('shop_payment_cards')) {
            Schema::create('shop_payment_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->string('square_customer_id');
                $table->string('square_card_id')->unique();
                $table->string('card_brand', 30)->nullable();
                $table->string('last4', 4)->default('0000');
                $table->unsignedTinyInteger('exp_month')->nullable();
                $table->unsignedSmallInteger('exp_year')->nullable();
                $table->boolean('is_default')->default(false);
                $table->string('status', 20)->default('active');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'status']);
                $table->index(['shop_id', 'is_default']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_payment_cards');

        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'square_customer_id')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('square_customer_id');
            });
        }
    }
};
