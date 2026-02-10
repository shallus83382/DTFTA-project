<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shop;
class PartnerProfile extends Model
{
    protected $fillable = [
        'shop_id',
        'brand_name',
        'return_address_street',
        'return_address_city',
        'return_address_state',
        'return_address_zip',
        'return_address_country',
        'support_email',
        'support_phone'
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}

