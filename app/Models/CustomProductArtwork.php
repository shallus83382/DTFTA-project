<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomProductArtwork extends Model
{
    protected $fillable = [
        'custom_product_id',
        'custom_product_variant_id',
        'placement',
        'title',
        'artwork_url',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function customProduct()
    {
        return $this->belongsTo(CustomProduct::class);
    }

    public function variant()
    {
        return $this->belongsTo(CustomProductVariant::class, 'custom_product_variant_id');
    }
}