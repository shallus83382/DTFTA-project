<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomProductVariant extends Model
{
    protected $fillable = [
        'custom_product_id',
        'product_variant_id',
        'title',
        'sku',
        'barcode',
        'price',
        'compare_at_price',
        'shopify_variant_id',
        'option_values',
        'source_payload',
    ];

    protected $casts = [
        'option_values' => 'array',
        'source_payload' => 'array',
    ];

    public function customProduct()
    {
        return $this->belongsTo(CustomProduct::class);
    }
}