<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('failed_webhooks', function (Blueprint $table) {
            if (!Schema::hasColumn('failed_webhooks', 'webhook_id')) {
                $table->unsignedBigInteger('webhook_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('failed_webhooks', 'event_type')) {
                $table->string('event_type')->nullable()->after('shop_id');
            }
            if (!Schema::hasColumn('failed_webhooks', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('error_message');
            }
            if (!Schema::hasColumn('failed_webhooks', 'max_retries')) {
                $table->unsignedInteger('max_retries')->default(5)->after('retry_count');
            }
            if (!Schema::hasColumn('failed_webhooks', 'last_attempted_at')) {
                $table->timestamp('last_attempted_at')->nullable()->after('max_retries');
            }
            if (!Schema::hasColumn('failed_webhooks', 'next_retry_at')) {
                $table->timestamp('next_retry_at')->nullable()->after('last_attempted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('failed_webhooks', function (Blueprint $table) {
            $columns = ['next_retry_at', 'last_attempted_at', 'max_retries', 'retry_count', 'event_type', 'webhook_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('failed_webhooks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
