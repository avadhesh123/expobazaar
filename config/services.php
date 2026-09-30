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
    // config/services.php — add:
    'amazon' => [
        'client_id'      => env('AMAZON_CLIENT_ID'),
        'client_secret'  => env('AMAZON_CLIENT_SECRET'),
        'refresh_token'  => env('AMAZON_REFRESH_TOKEN'),
        'aws_access_key' => env('AMAZON_AWS_ACCESS_KEY'),
        'aws_secret_key' => env('AMAZON_AWS_SECRET_KEY'),
        'role_arn'       => env('AMAZON_ROLE_ARN'),
        'marketplace_id' => env('AMAZON_MARKETPLACE_ID', 'ATVPDKIKX0DER'),
    ],

];
