<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomProduct extends Model
{
    protected $fillable = [
        'shop_id',
        'product_id',
        'product_key',
        'title',
        'vendor',
        'product_type',
        'status',
        'shopify_product_id',
        'tags',
        'description_html',
        'options',
        'print_areas',
        'print_plan',
    ];

    protected $casts = [
        'tags' => 'array',
        'options' => 'array',
        'print_areas' => 'array',
        // Column is JSON. The plan is a pipe string such as FRONT:50x90|BACK:50x90,
        // so it must be encoded as a JSON string instead of inserted as raw text.
        'print_plan' => 'json',
    ];

    public function variants()
    {
        return $this->hasMany(CustomProductVariant::class);
    }

    public function artworks()
    {
        return $this->hasMany(CustomProductArtwork::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
