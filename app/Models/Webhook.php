<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = [
        'shop_id',
        'event_type',
        'webhook_id',
        'shopify_webhook_id',
        'topic',
        'created_at_shopify',
        'payload',
        'processed',
        'processed_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
        'created_at_shopify' => 'datetime',
        'processed_at' => 'datetime'
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
