<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PartnerProfile;
use App\Models\Order;
use App\Models\Job;
use App\Models\Shipment;

class Shop extends Model
{

    protected $fillable = [
        'shop_domain',
        'shopify_access_token',
        'shopify_api_version',
        'shopify_scopes',
        'fulfillment_service_id',
        'location_id',
        'status',
        'installed_at',
        'uninstalled_at'
    ];

    public function partnerProfile()
    {
        return $this->hasOne(PartnerProfile::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}
