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
        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description')->nullable()->after('description');
            $table->string('category')->nullable()->after('short_description');
            $table->string('sub_category')->nullable()->after('category');
            $table->string('brand')->nullable()->after('sub_category');
            $table->string('product_type')->nullable()->after('brand');
            $table->json('tags')->nullable()->after('product_type');

            $table->decimal('regular_price', 10, 2)->nullable()->after('tags');
            $table->decimal('sale_price', 10, 2)->nullable()->after('regular_price');
            $table->string('tax_class')->nullable()->after('currency');

            $table->string('stock_status')->default('in_stock')->after('stock_quantity');
            $table->boolean('track_inventory')->default(true)->after('stock_status');

            $table->string('featured_image')->nullable()->after('track_inventory');
            $table->json('gallery_images')->nullable()->after('featured_image');

            $table->decimal('weight', 10, 3)->nullable()->after('gallery_images');
            $table->decimal('length', 10, 2)->nullable()->after('weight');
            $table->decimal('width', 10, 2)->nullable()->after('length');
            $table->decimal('height', 10, 2)->nullable()->after('width');
            $table->string('shipping_class')->nullable()->after('height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'short_description',
                'category',
                'sub_category',
                'brand',
                'product_type',
                'tags',
                'regular_price',
                'sale_price',
                'tax_class',
                'stock_status',
                'track_inventory',
                'featured_image',
                'gallery_images',
                'weight',
                'length',
                'width',
                'height',
                'shipping_class',
            ]);
        });
    }
};
