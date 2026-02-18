<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentService extends Model
{
    protected $fillable = [
        'shop_id',
        'service_id',
        'name',
        'tracking_support',
        'requires_shipping_method',
        'inventory_management',
        'handle'
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}
