<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'square' => [
        'access_token' => env('SQUARE_ACCESS_TOKEN', ''),
        'location_id' => env('SQUARE_LOCATION_ID', ''),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
        'wallet_billing_enabled' => env('SQUARE_WALLET_BILLING_ENABLED', true),
    ],

    'shopify' => [
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET', ''),
        'external_secret' => env('EXTERNAL_API_SECRET', ''),
        'api_key' => env('SHOPIFY_API_KEY', ''),
        'api_password' => env('SHOPIFY_API_PASSWORD', ''),
        'api_secret' => env('SHOPIFY_API_SECRET', env('SHOPIFY_API_PASSWORD', '')),
        'api_version' => env('SHOPIFY_API_VERSION', '2025-10'),
        'webhook_api_version' => env('SHOPIFY_WEBHOOK_API_VERSION', '2026-04'),
        'scopes' => env('SHOPIFY_SCOPES', 'read_orders,write_fulfillments,read_assigned_fulfillment_orders,write_assigned_fulfillment_orders,write_third_party_fulfillment_orders'),
        'billing' => [
            'enabled' => env('BILLING_ENFORCEMENT_ENABLED', false),
            'test_mode' => env('SHOPIFY_BILLING_TEST_MODE', true),
            'plan_name' => env('SHOPIFY_BILLING_PLAN_NAME', 'DTFTA Merchant Usage'),
            'return_url' => env('SHOPIFY_BILLING_RETURN_URL', env('APP_URL') . '/billing/return'),
            'base_price_amount' => (float) env('SHOPIFY_BILLING_BASE_PRICE_AMOUNT', 0),
            'per_order_amount' => (float) env('SHOPIFY_BILLING_PER_ORDER_AMOUNT', 1),
            'usage_cap_amount' => (float) env('SHOPIFY_BILLING_USAGE_CAP_AMOUNT', 1000),
            'currency_code' => env('SHOPIFY_BILLING_CURRENCY_CODE', 'USD'),
        ],
    ],

];
