<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active Environment
    |--------------------------------------------------------------------------
    |
    | Determines which environment configuration is used. Supported values:
    | "staging", "production"
    |
    */

    'environment' => env('CASHIPAY_ENV', 'staging'),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | Connection details for each supported environment. The active environment
    | is selected via the 'environment' key above.
    |
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
    |
    | Configuration for the inbound webhook listener that CashiPay calls when
    | a payment status changes.
    |
    */

    'webhook' => [

        /*
         * The HMAC-SHA256 secret used to verify webhook signatures.
         * Leave null to skip signature verification (not recommended for production).
         */
        'secret' => env('CASHIPAY_WEBHOOK_SECRET', null),

        /*
         * The URI path on which the webhook endpoint will be registered.
         * This path will be registered under the application's root.
         */
        'path' => env('CASHIPAY_WEBHOOK_PATH', 'cashipay/webhook'),

        /*
         * Additional middleware applied to the webhook route.
         * 'cashipay.webhook' (signature verifier) is always applied automatically.
         */
        'middleware' => [],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The ISO 4217 currency code used when none is explicitly provided on a
    | payment request.
    |
    */

    'currency' => env('CASHIPAY_CURRENCY', 'SDG'),

    /*
    |--------------------------------------------------------------------------
    | Payment Expiry
    |--------------------------------------------------------------------------
    |
    | Number of hours before a payment request is considered expired.
    |
    */

    'expiry_hours' => (int) env('CASHIPAY_EXPIRY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds to wait for a response from the CashiPay API.
    |
    */

    'timeout' => (int) env('CASHIPAY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Merchant Order Key
    |--------------------------------------------------------------------------
    |
    | The key used to extract the merchant order ID from the payload when it
    | is not explicitly provided. Useful for mapping your internal order IDs.
    |
    */

    'merchant_order_key' => env('CASHIPAY_MERCHANT_ORDER_KEY', 'merchantOrderId'),

];
