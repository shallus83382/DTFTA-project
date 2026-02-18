<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'line_item_id')) {
                $table->string('line_item_id')->nullable()->after('order_id')->index();
            }
            if (!Schema::hasColumn('order_items', 'title')) {
                $table->string('title')->nullable()->after('sku');
            }
            if (!Schema::hasColumn('order_items', 'variant_title')) {
                $table->string('variant_title')->nullable()->after('title');
            }
            if (!Schema::hasColumn('order_items', 'price')) {
                $table->decimal('price', 10, 2)->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('order_items', 'fulfillment_status')) {
                $table->string('fulfillment_status')->nullable()->after('price');
            }
            if (!Schema::hasColumn('order_items', 'payload')) {
                $table->json('payload')->nullable()->after('properties');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'payload')) {
                $table->dropColumn('payload');
            }
            if (Schema::hasColumn('order_items', 'fulfillment_status')) {
                $table->dropColumn('fulfillment_status');
            }
            if (Schema::hasColumn('order_items', 'price')) {
                $table->dropColumn('price');
            }
            if (Schema::hasColumn('order_items', 'variant_title')) {
                $table->dropColumn('variant_title');
            }
            if (Schema::hasColumn('order_items', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('order_items', 'line_item_id')) {
                $table->dropColumn('line_item_id');
            }
        });
    }
};
