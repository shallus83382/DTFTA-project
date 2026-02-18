<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedWebhook extends Model
{
    protected $fillable = [
        'webhook_id',
        'shop_id',
        'event_type',
        'topic',
        'payload',
        'error_message',
        'retry_count',
        'max_retries',
        'last_attempted_at',
        'next_retry_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'last_attempted_at' => 'datetime',
        'next_retry_at' => 'datetime'
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
