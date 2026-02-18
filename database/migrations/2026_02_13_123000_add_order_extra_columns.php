<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderExtraColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add columns only if they don't already exist
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
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payload')) {
                $table->dropColumn('payload');
            }
            if (Schema::hasColumn('orders', 'financial_status')) {
                $table->dropColumn('financial_status');
            }
            if (Schema::hasColumn('orders', 'fulfillment_status')) {
                $table->dropColumn('fulfillment_status');
            }
            if (Schema::hasColumn('orders', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
}
