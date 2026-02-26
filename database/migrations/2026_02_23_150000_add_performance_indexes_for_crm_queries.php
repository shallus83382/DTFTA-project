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
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['created_at', 'fulfillment_status'], 'orders_created_status_idx');
            $table->index(['shop_id', 'created_at'], 'orders_shop_created_idx');
        });

        Schema::table('app_jobs', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'app_jobs_status_created_idx');
            $table->index(['order_id', 'created_at'], 'app_jobs_order_created_idx');
            $table->index(['shop_id', 'created_at'], 'app_jobs_shop_created_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'shipments_status_created_idx');
            $table->index(['shop_id', 'created_at'], 'shipments_shop_created_idx');
        });

        Schema::table('admin_activity_logs', function (Blueprint $table) {
            $table->index(['model_type', 'model_id', 'created_at'], 'admin_activity_model_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_created_status_idx');
            $table->dropIndex('orders_shop_created_idx');
        });

        Schema::table('app_jobs', function (Blueprint $table) {
            $table->dropIndex('app_jobs_status_created_idx');
            $table->dropIndex('app_jobs_order_created_idx');
            $table->dropIndex('app_jobs_shop_created_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_status_created_idx');
            $table->dropIndex('shipments_shop_created_idx');
        });

        Schema::table('admin_activity_logs', function (Blueprint $table) {
            $table->dropIndex('admin_activity_model_created_idx');
        });
    }
};
