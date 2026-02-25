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
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'store_id')) {
                $table->string('store_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('shops', 'name')) {
                $table->string('name')->nullable()->after('store_id');
            }
            if (!Schema::hasColumn('shops', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
            if (!Schema::hasColumn('shops', 'domain')) {
                $table->string('domain')->nullable()->after('email');
            }
            if (!Schema::hasColumn('shops', 'shop_owner')) {
                $table->string('shop_owner')->nullable()->after('domain');
            }
            if (!Schema::hasColumn('shops', 'shopify_webhook_api_version')) {
                $table->string('shopify_webhook_api_version')->default('2026-04')->after('shopify_api_version');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'shopify_webhook_api_version')) {
                $table->dropColumn('shopify_webhook_api_version');
            }
            if (Schema::hasColumn('shops', 'shop_owner')) {
                $table->dropColumn('shop_owner');
            }
            if (Schema::hasColumn('shops', 'domain')) {
                $table->dropColumn('domain');
            }
            if (Schema::hasColumn('shops', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('shops', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('shops', 'store_id')) {
                $table->dropUnique('shops_store_id_unique');
                $table->dropColumn('store_id');
            }
        });
    }
};
