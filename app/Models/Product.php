<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shop_id',
        'shopify_product_id',
        'title',
        'sku',
        'description',
        'short_description',
        'category',
        'sub_category',
        'brand',
        'product_type',
        'tags',
        'price',
        'regular_price',
        'sale_price',
        'currency',
        'tax_class',
        'stock_quantity',
        'stock_status',
        'track_inventory',
        'status',
        'featured_image',
        'gallery_images',
        'weight',
        'length',
        'width',
        'height',
        'shipping_class',
        'options',
        'images',
        'payload',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'regular_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'track_inventory' => 'boolean',
        'weight' => 'decimal:3',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'tags' => 'array',
        'gallery_images' => 'array',
        'options' => 'array',
        'images' => 'array',
        'payload' => 'array',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
