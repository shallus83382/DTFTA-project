<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'order_id',
        'line_item_id',
        'shopify_line_item_id',
        'sku',
        'title',
        'variant_title',
        'dtfta_type',
        'quantity',
        'price',
        'fulfillment_status',
        'properties',
        'payload',
        'status'
    ];

    protected $casts = [
        'properties' => 'array',
        'payload' => 'array'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
