<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canvas region for the Shopify product customizer (DesignCanvas.tsx).
 *
 * Values live in the customizer's logical 500 x 500 editor space (see
 * design-region-pixel-rect.ts, DESIGN_LOGICAL_CANVAS_SIZE). When all four are
 * set, the app draws the dashed print-area boundary and clips artwork exactly
 * here. When they are NULL the app keeps deriving the region from
 * area_width / area_height / position_x / position_y like before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_areas', function (Blueprint $table) {
            if (!Schema::hasColumn('print_areas', 'design_position_x')) {
                $table->decimal('design_position_x', 10, 2)->nullable()->after('position_y');
            }
            if (!Schema::hasColumn('print_areas', 'design_position_y')) {
                $table->decimal('design_position_y', 10, 2)->nullable()->after('design_position_x');
            }
            if (!Schema::hasColumn('print_areas', 'design_area_width')) {
                $table->decimal('design_area_width', 10, 2)->nullable()->after('design_position_y');
            }
            if (!Schema::hasColumn('print_areas', 'design_area_height')) {
                $table->decimal('design_area_height', 10, 2)->nullable()->after('design_area_width');
            }
        });
    }

    public function down(): void
    {
        Schema::table('print_areas', function (Blueprint $table) {
            foreach (['design_position_x', 'design_position_y', 'design_area_width', 'design_area_height'] as $column) {
                if (Schema::hasColumn('print_areas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
