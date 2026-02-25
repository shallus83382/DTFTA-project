<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FulfillmentService extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shop_id',
        'service_id',
        'name',
        'status',
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
