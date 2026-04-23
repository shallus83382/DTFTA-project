<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BillingCharge;

class Order extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'shop_id',
        'shopify_order_id',
        'order_number',
        'customer_email',
        'customer_name',
        'status',
        'total_price',
        'currency',
        'fulfillment_status',
        'financial_status',
        'created_at_shopify',
        'updated_at_shopify',
        'closed_at',
        'cancelled_at',
        'raw_data',
        'payload'
    ];

    protected $casts = [
        'raw_data' => 'array',
        'payload' => 'array',
        'created_at_shopify' => 'datetime',
        'updated_at_shopify' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime'
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    public function billingCharges()
    {
        return $this->hasMany(BillingCharge::class);
    }
}
