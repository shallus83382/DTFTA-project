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
        'images'
    ];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean'
    ];

    public function products()
{
    return $this->hasMany(Product::class);
}
}

