<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'brand',
        'model_code',
        'category',
        'description',
        'status',
        'price',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'decimal:2',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function printAreas(): BelongsToMany
    {
        return $this->belongsToMany(
            PrintArea::class,
            'product_print_areas',
            'product_id',
            'print_area_id'
        )
        ->using(ProductPrintArea::class)
        ->withTimestamps();
    }
}