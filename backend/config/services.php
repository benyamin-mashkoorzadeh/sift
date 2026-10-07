<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'cohere' => [
        'api_key' => env('COHERE_API_KEY'),
        'base_url' => env('COHERE_BASE_URL', 'https://api.cohere.com'),
        'embedding_model' => env('COHERE_EMBEDDING_MODEL', 'embed-v4.0'),
        'connect_timeout' => (int) env('COHERE_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('COHERE_TIMEOUT', 30),
        'max_attempts' => (int) env('COHERE_MAX_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('COHERE_RETRY_DELAY_MS', 200),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        'connect_timeout' => (int) env('GROQ_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('GROQ_TIMEOUT', 30),
        'max_attempts' => (int) env('GROQ_MAX_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('GROQ_RETRY_DELAY_MS', 200),
        'reasoning_effort' => env('GROQ_REASONING_EFFORT', 'low'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
