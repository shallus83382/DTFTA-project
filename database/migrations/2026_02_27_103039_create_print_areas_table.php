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
        Schema::create('print_areas', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->decimal('area_width', 10, 2)->nullable();
            $table->decimal('area_height', 10, 2)->nullable();
            $table->enum('unit', ['mm', 'cm', 'in', 'px'])->nullable();
            $table->decimal('position_x', 10, 2)->nullable();
            $table->decimal('position_y', 10, 2)->nullable();
            $table->string('tshirt_size', 50)->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('images')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_areas');
    }
};
