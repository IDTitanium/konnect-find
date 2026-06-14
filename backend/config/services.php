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

    'search' => [
        'text_provider' => env('SEARCH_TEXT_PROVIDER', 'local'),
        'text_dimensions' => (int) env('SEARCH_TEXT_DIMENSIONS', 128),
        'openai_key' => env('OPENAI_API_KEY'),
        'openai_base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'openai_embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'image_provider' => env('SEARCH_IMAGE_PROVIDER', 'local'),
        'image_dimensions' => (int) env('SEARCH_IMAGE_DIMENSIONS', 64),
        'image_service_url' => env('IMAGE_EMBEDDING_SERVICE_URL', 'http://127.0.0.1:8001'),
        'image_service_token' => env('IMAGE_EMBEDDING_SERVICE_TOKEN'),
    ],

];
