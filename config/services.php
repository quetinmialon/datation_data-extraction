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
    'france_travail' => [
        'client_id'     => env('FRANCE_TRAVAIL_CLIENT_ID'),
        'client_secret' => env('FRANCE_TRAVAIL_CLIENT_SECRET'),
        'scope'         => env('FRANCE_TRAVAIL_SCOPE', 'api_offresdemploiv2 o2dsoffre'),
        'token_url'     => env('FRANCE_TRAVAIL_TOKEN_URL'),
        'api_base_url'  => env('FRANCE_TRAVAIL_API_BASE_URL', 'https://api.francetravail.io'),
        // endpoint "métiers" ROME 4.0 – à adapter selon ta doc
        'rome_metiers_endpoint' => env('FRANCE_TRAVAIL_ROME_METIERS_ENDPOINT'),
    ],

];
