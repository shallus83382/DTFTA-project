<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('id')->constrained('orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('shipments', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->after('order_id')->constrained('shops')->nullOnDelete();
            }
            if (!Schema::hasColumn('shipments', 'fulfillment_service_id')) {
                $table->foreignId('fulfillment_service_id')->nullable()->after('shop_id')->constrained('fulfillment_services')->nullOnDelete();
            }
            if (!Schema::hasColumn('shipments', 'shipment_id')) {
                $table->string('shipment_id')->nullable()->after('fulfillment_service_id');
            }
            if (!Schema::hasColumn('shipments', 'tracking_company')) {
                $table->string('tracking_company')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('shipments', 'status')) {
                $table->string('status')->default('processing')->after('tracking_company');
            }
            if (!Schema::hasColumn('shipments', 'line_items')) {
                $table->json('line_items')->nullable()->after('status');
            }
            if (!Schema::hasColumn('shipments', 'created_at_shopify')) {
                $table->timestamp('created_at_shopify')->nullable()->after('line_items');
            }
            if (!Schema::hasColumn('shipments', 'updated_at_shopify')) {
                $table->timestamp('updated_at_shopify')->nullable()->after('created_at_shopify');
            }
            if (!Schema::hasColumn('shipments', 'payload')) {
                $table->json('payload')->nullable()->after('updated_at_shopify');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $cols = ['payload','updated_at_shopify','created_at_shopify','line_items','status','tracking_company','shipment_id','fulfillment_service_id','shop_id','order_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('shipments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
