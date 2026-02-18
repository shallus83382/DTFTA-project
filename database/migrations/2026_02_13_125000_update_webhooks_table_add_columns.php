<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            if (!Schema::hasColumn('webhooks', 'event_type')) {
                $table->string('event_type')->nullable()->after('shop_id');
            }
            if (!Schema::hasColumn('webhooks', 'webhook_id')) {
                $table->string('webhook_id')->nullable()->after('topic');
            }
            if (!Schema::hasColumn('webhooks', 'created_at_shopify')) {
                $table->timestamp('created_at_shopify')->nullable()->after('webhook_id');
            }
            if (!Schema::hasColumn('webhooks', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('processed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $cols = ['processed_at','created_at_shopify','webhook_id','event_type'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('webhooks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
