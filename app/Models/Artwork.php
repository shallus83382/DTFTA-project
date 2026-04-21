<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artwork extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shop_id',
        'product_key',
        'placement',
        'color_code',
        'name',
        'source',
        'mime_type',
        'extension',
        'disk',
        'path',
        'url',
        'file_size',
        'width',
        'height',
        'meta',
        'uploaded_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'uploaded_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
