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

    'shopify' => [
        'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET', ''),
        'api_key' => env('SHOPIFY_API_KEY', ''),
        'api_password' => env('SHOPIFY_API_PASSWORD', ''),
        'api_secret' => env('SHOPIFY_API_SECRET', env('SHOPIFY_API_PASSWORD', '')),
        'api_version' => env('SHOPIFY_API_VERSION', '2025-10'),
        'webhook_api_version' => env('SHOPIFY_WEBHOOK_API_VERSION', '2026-04'),
        'scopes' => env('SHOPIFY_SCOPES', 'read_orders,write_fulfillments,read_assigned_fulfillment_orders,write_assigned_fulfillment_orders,write_third_party_fulfillment_orders'),
    ],

];
