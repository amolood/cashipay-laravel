<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active Environment
    |--------------------------------------------------------------------------
    |
    | Determines which environment configuration is used.
    | Supported: "staging", "production"
    |
    */

    'environment' => env('CASHIPAY_ENV', 'staging'),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    */

    'environments' => [

        'staging' => [
            'base_url' => env('CASHIPAY_STAGING_URL', 'https://stg-cashi-services.alsoug.com/cashipay'),
            'api_key'  => env('CASHIPAY_STAGING_KEY', ''),
        ],

        'production' => [
            'base_url' => env('CASHIPAY_PRODUCTION_URL', 'https://cashi-services.alsoug.com/cashipay'),
            'api_key'  => env('CASHIPAY_PRODUCTION_KEY', ''),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */

    'webhook' => [

        // URI path on which the webhook endpoint is registered.
        'path' => env('CASHIPAY_WEBHOOK_PATH', 'cashipay/webhook'),

        // Additional middleware applied to the webhook route.
        'middleware' => [],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */

    'currency' => env('CASHIPAY_CURRENCY', 'SDG'),

    /*
    |--------------------------------------------------------------------------
    | Payment Expiry
    |--------------------------------------------------------------------------
    */

    'expiry_hours' => (int) env('CASHIPAY_EXPIRY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('CASHIPAY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Merchant Order Key
    |--------------------------------------------------------------------------
    */

    'merchant_order_key' => env('CASHIPAY_MERCHANT_ORDER_KEY', 'merchantOrderId'),

];
