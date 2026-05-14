<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookVerifier
{
    public function verify(Request $request): bool
    {
        $hmacHeader = trim((string) $request->header('X-Shopify-Hmac-Sha256'));

        if ($hmacHeader === '') {
            Log::warning('Shopify webhook verification failed: missing X-Shopify-Hmac-Sha256 header');
            return false;
        }

        $secret = (string) (
            config('services.shopify.webhook_secret')
            ?: config('services.shopify.api_secret')
            ?: ''
        );

        if ($secret === '') {
            Log::error('Shopify webhook verification failed: missing webhook secret');
            return false;
        }

        $rawBody = (string) $request->getContent();

        $calculated = base64_encode(
            hash_hmac('sha256', $rawBody, $secret, true)
        );


        return hash_equals($calculated, $hmacHeader);
    }
}