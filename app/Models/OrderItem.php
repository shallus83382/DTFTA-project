<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
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
        'payload' => 'array'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
