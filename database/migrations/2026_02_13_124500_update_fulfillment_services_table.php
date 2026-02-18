<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_services', function (Blueprint $table) {
            if (!Schema::hasColumn('fulfillment_services', 'service_id')) {
                $table->string('service_id')->nullable()->after('shop_id')->index();
            }
            if (!Schema::hasColumn('fulfillment_services', 'tracking_support')) {
                $table->boolean('tracking_support')->default(false)->after('name');
            }
            if (!Schema::hasColumn('fulfillment_services', 'requires_shipping_method')) {
                $table->boolean('requires_shipping_method')->default(false)->after('tracking_support');
            }
            if (!Schema::hasColumn('fulfillment_services', 'inventory_management')) {
                $table->boolean('inventory_management')->default(false)->after('requires_shipping_method');
            }
            if (!Schema::hasColumn('fulfillment_services', 'handle')) {
                $table->string('handle')->nullable()->after('inventory_management');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_services', function (Blueprint $table) {
            $cols = ['handle','inventory_management','requires_shipping_method','tracking_support','service_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('fulfillment_services', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
