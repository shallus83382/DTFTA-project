<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BillingCharge;

class Shipment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'job_id',
        'order_id',
        'shop_id',
        'fulfillment_service_id',
        'shipment_id',
        'carrier',
        'tracking_number',
        'tracking_company',
        'tracking_url',
        'status',
        'line_items',
        'shipped_at',
        'created_at_shopify',
        'updated_at_shopify',
        'payload'
    ];

    protected $casts = [
        'line_items' => 'array',
        'payload' => 'array',
        'shipped_at' => 'datetime',
        'created_at_shopify' => 'datetime',
        'updated_at_shopify' => 'datetime'
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function fulfillmentService()
    {
        return $this->belongsTo(FulfillmentService::class);
    }

    public function billingCharges()
    {
        return $this->hasMany(BillingCharge::class);
    }
}
