<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('app_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('app_jobs', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        Schema::table('fulfillment_services', function (Blueprint $table) {
            if (!Schema::hasColumn('fulfillment_services', 'status')) {
                $table->string('status')->default('active')->after('name');
            }
            if (!Schema::hasColumn('fulfillment_services', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        try {
            DB::statement("ALTER TABLE shops MODIFY status ENUM('active','inactive','suspended','uninstalled') NOT NULL DEFAULT 'active'");
        } catch (\Throwable $e) {
            // Ignore if database engine/version does not support this statement.
        }

        try {
            DB::statement('ALTER TABLE shops DROP INDEX shops_shop_domain_unique');
        } catch (\Throwable $e) {
            // Ignore when index does not exist.
        }

        try {
            DB::statement('CREATE UNIQUE INDEX shops_shop_domain_deleted_at_unique ON shops (shop_domain, deleted_at)');
        } catch (\Throwable $e) {
            // Ignore when index already exists.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP INDEX shops_shop_domain_deleted_at_unique ON shops');
        } catch (\Throwable $e) {
            // Ignore when index does not exist.
        }

        try {
            DB::statement("ALTER TABLE shops MODIFY status ENUM('active','suspended','uninstalled') NOT NULL DEFAULT 'active'");
        } catch (\Throwable $e) {
            // Ignore if database engine/version does not support this statement.
        }

        try {
            DB::statement('CREATE UNIQUE INDEX shops_shop_domain_unique ON shops (shop_domain)');
        } catch (\Throwable $e) {
            // Ignore when index already exists.
        }

        Schema::table('fulfillment_services', function (Blueprint $table) {
            if (Schema::hasColumn('fulfillment_services', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('fulfillment_services', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('app_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('app_jobs', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
