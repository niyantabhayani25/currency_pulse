<?php

/**
 * Third-party service configuration.
 *
 * SECURITY: All sensitive values (API keys, secrets) are read from .env via
 * env(). They are never hard-coded here or logged anywhere in the application.
 */

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CurrencyLayer API
    |--------------------------------------------------------------------------
    |
    | Register at https://currencylayer.com/quickstart to obtain a free key.
    | Store the key in .env as CURRENCY_LAYER_API_KEY — never commit it.
    |
    */
    'currency_layer' => [
        'key'      => env('CURRENCY_LAYER_API_KEY'),
        'base_url' => env('CURRENCY_LAYER_BASE_URL', 'https://api.currencylayer.com'),
    ],

];
