<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('print_area_id')->nullable()->after('id');

            $table->foreign('print_area_id')
                  ->references('id')
                  ->on('print_areas')
                  ->onDelete('set null'); // agar print area delete ho toh null ho jaye
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['print_area_id']);
            $table->dropColumn('print_area_id');
        });
    }
};

