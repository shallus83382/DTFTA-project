<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPaymentCard extends Model
{
    protected $fillable = [
        'shop_id',
        'square_customer_id',
        'square_card_id',
        'card_brand',
        'last4',
        'exp_month',
        'exp_year',
        'is_default',
        'status',
        'meta',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'meta' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function toWalletArray(): array
    {
        return [
            'id' => (string) $this->id,
            'brand' => $this->card_brand ?: 'Card',
            'last4' => $this->last4,
            'expMonth' => (int) $this->exp_month,
            'expYear' => (int) $this->exp_year,
            'isDefault' => (bool) $this->is_default,
        ];
    }
}
