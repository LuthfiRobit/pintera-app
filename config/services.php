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

    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
    ],

    'bri' => [
        'gateway' => env('BRI_PAYMENT_GATEWAY', 'mock'), // mock | snap | hybrid
        'client_id' => env('BRI_SNAP_CLIENT_ID'),
        'client_secret' => env('BRI_SNAP_CLIENT_SECRET'),
        'base_url' => env('BRI_SNAP_BASE_URL'),
        'private_key_path' => env('BRI_SNAP_PRIVATE_KEY_PATH'),
        'partner_id' => env('BRI_SNAP_PARTNER_ID'),
        'channel_id' => env('BRI_SNAP_CHANNEL_ID'),
        'merchant_id' => env('BRI_SNAP_MERCHANT_ID'),
        'terminal_id' => env('BRI_SNAP_TERMINAL_ID'),
    ],

];
