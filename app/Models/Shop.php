<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PartnerProfile;
use App\Models\Order;
use App\Models\Job;
use App\Models\Shipment;
use App\Models\Product;
use App\Models\Artwork;

class Shop extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'email',
        'domain',
        'shop_owner',
        'shop_domain',
        'shopify_access_token',
        'shopify_api_version',
        'shopify_webhook_api_version',
        'shopify_scopes',
        'fulfillment_service_id',
        'location_id',
        'carrier_service_id',
        'shipping_profile_id',
        'delivery_location_group_id',
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

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function artworks()
    {
        return $this->hasMany(Artwork::class);
    }
}
