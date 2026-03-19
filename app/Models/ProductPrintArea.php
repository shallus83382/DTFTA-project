<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrintArea extends Pivot
{
    protected $table = 'product_print_areas';

    protected $fillable = [
        'product_id',
        'print_area_id',
    ];

    public $timestamps = true;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function printArea(): BelongsTo
    {
        return $this->belongsTo(PrintArea::class);
    }
}