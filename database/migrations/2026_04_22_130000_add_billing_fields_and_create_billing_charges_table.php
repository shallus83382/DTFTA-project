<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('shops', 'billing_status')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('billing_status', 30)->default('inactive')->after('status');
            });
        }
        if (!Schema::hasColumn('shops', 'billing_plan_code')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('billing_plan_code')->nullable()->after('billing_status');
            });
        }
        if (!Schema::hasColumn('shops', 'shopify_billing_subscription_gid')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('shopify_billing_subscription_gid')->nullable()->after('billing_plan_code');
            });
        }
        if (!Schema::hasColumn('shops', 'shopify_billing_line_item_gid')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('shopify_billing_line_item_gid')->nullable()->after('shopify_billing_subscription_gid');
            });
        }
        if (!Schema::hasColumn('shops', 'billing_approved_at')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->timestamp('billing_approved_at')->nullable()->after('shopify_billing_line_item_gid');
            });
        }
        if (!Schema::hasColumn('shops', 'billing_blocked_reason')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('billing_blocked_reason')->nullable()->after('billing_approved_at');
            });
        }

        if (!Schema::hasTable('billing_charges')) {
            Schema::create('billing_charges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                $table->string('charge_type', 50)->default('pre_fulfillment');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 10)->default('USD');
                $table->string('status', 30)->default('pending');
                $table->string('shopify_usage_record_gid')->nullable();
                $table->string('idempotency_key', 191)->unique();
                $table->text('error_message')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'status']);
                $table->index(['order_id', 'charge_type']);
                $table->index(['shipment_id', 'charge_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_charges');

        if (Schema::hasTable('shops')) {
            $dropColumns = array_values(array_filter([
                Schema::hasColumn('shops', 'billing_status') ? 'billing_status' : null,
                Schema::hasColumn('shops', 'billing_plan_code') ? 'billing_plan_code' : null,
                Schema::hasColumn('shops', 'shopify_billing_subscription_gid') ? 'shopify_billing_subscription_gid' : null,
                Schema::hasColumn('shops', 'shopify_billing_line_item_gid') ? 'shopify_billing_line_item_gid' : null,
                Schema::hasColumn('shops', 'billing_approved_at') ? 'billing_approved_at' : null,
                Schema::hasColumn('shops', 'billing_blocked_reason') ? 'billing_blocked_reason' : null,
            ]));

            if (!empty($dropColumns)) {
                Schema::table('shops', function (Blueprint $table) use ($dropColumns) {
                    $table->dropColumn($dropColumns);
                });
            }
        }
    }
};

