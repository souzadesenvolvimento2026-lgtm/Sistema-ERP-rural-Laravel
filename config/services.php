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

    'nominatim' => [
        'enabled' => env('NOMINATIM_ENABLED', true),
        'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'FarmFort/1.0 (https://www.farmfort.com.br)'),
        'email' => env('NOMINATIM_EMAIL'),
        'timeout' => env('NOMINATIM_TIMEOUT', 8),
        'connect_timeout' => env('NOMINATIM_CONNECT_TIMEOUT', 3),
        'rate_limit_ms' => env('NOMINATIM_RATE_LIMIT_MS', 1100),
        'cache_days' => env('NOMINATIM_CACHE_DAYS', 180),
        'cache_precision' => env('NOMINATIM_CACHE_PRECISION', 5),
    ],

];
