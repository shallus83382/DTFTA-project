<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'app_jobs';
    protected $fillable = [
        'shop_id',
        'order_id',
        'job_type',
        'status',
        'payload',
        'result',
        'error_message',
        'started_at',
        'completed_at',
        'failed_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime'
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
