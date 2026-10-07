<?php

return [
    'enabled' => env('DEMO_MODE_ENABLED', false),
    'user_id' => env('DEMO_USER_ID'),
    'workspace_id' => env('DEMO_WORKSPACE_ID'),

    'assistant' => [
        'enabled' => env('DEMO_ASSISTANT_ENABLED', false),
        'requests_per_minute' => env('DEMO_ASSISTANT_PER_MINUTE', 3),
        'requests_per_hour_per_ip' => env('DEMO_ASSISTANT_PER_HOUR_PER_IP', 15),
        'requests_per_day' => env('DEMO_ASSISTANT_PER_DAY', 100),
        'max_question_length' => env('DEMO_ASSISTANT_MAX_QUESTION_LENGTH', 500),
    ],

    'entry' => [
        'requests_per_hour_per_ip' => env('DEMO_ENTRY_PER_HOUR_PER_IP', 10),
    ],

    'widget' => [
        'enabled' => env('DEMO_WIDGET_ENABLED', false),
        'requests_per_minute_per_ip' => env('DEMO_WIDGET_PER_MINUTE_PER_IP', 3),
        'requests_per_hour_per_ip' => env('DEMO_WIDGET_PER_HOUR_PER_IP', 15),
        'requests_per_day' => env('DEMO_WIDGET_PER_DAY', 100),
        'max_question_length' => env('DEMO_WIDGET_MAX_QUESTION_LENGTH', 500),
        'maximum_request_bytes' => env('DEMO_WIDGET_MAXIMUM_REQUEST_BYTES', 8192),
    ],
];
