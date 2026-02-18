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
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'currency')) {
                $table->string('currency')->nullable()->after('total_price');
            }

            if (!Schema::hasColumn('orders', 'fulfillment_status')) {
                $table->string('fulfillment_status')->nullable()->after('currency');
            }

            if (!Schema::hasColumn('orders', 'financial_status')) {
                $table->string('financial_status')->nullable()->after('fulfillment_status');
            }

            if (!Schema::hasColumn('orders', 'payload')) {
                $table->json('payload')->nullable()->after('financial_status');
            }

            if (!Schema::hasColumn('orders', 'created_at_shopify')) {
                $table->timestamp('created_at_shopify')->nullable()->after('payload');
            }

            if (!Schema::hasColumn('orders', 'updated_at_shopify')) {
                $table->timestamp('updated_at_shopify')->nullable()->after('created_at_shopify');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'updated_at_shopify')) {
                $table->dropColumn('updated_at_shopify');
            }

            if (Schema::hasColumn('orders', 'created_at_shopify')) {
                $table->dropColumn('created_at_shopify');
            }
        });
    }
};
