<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintArea extends Model
{

    protected $fillable = [
        'title',
        'area_width',
        'area_height',
        'unit',
        'position_x',
        'position_y',
        'tshirt_size',
        'display_order',
        'is_active',
        'price',
        'images'
    ];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];


    // PrintArea → Products (M:N)
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_print_areas',
            'print_area_id',
            'product_id'
        )
        ->using(ProductPrintArea::class)
        ->withTimestamps();
    }
}

