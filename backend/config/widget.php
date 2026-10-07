<?php

return [
    'frontend_url' => env('SIFT_FRONTEND_URL'),
    'conversation_lifetime_minutes' => (int) env('WIDGET_CONVERSATION_LIFETIME_MINUTES', 1440),
    'limits' => [
        'bootstrap_requests_per_minute' => (int) env('WIDGET_BOOTSTRAP_REQUESTS_PER_MINUTE', 60),
        'session_creations_per_hour' => (int) env('WIDGET_SESSION_CREATIONS_PER_HOUR', 10),
        'messages_per_minute_per_conversation' => (int) env('WIDGET_MESSAGES_PER_MINUTE_PER_CONVERSATION', 5),
        'messages_per_hour_per_ip_widget' => (int) env('WIDGET_MESSAGES_PER_HOUR_PER_IP_WIDGET', 30),
        'messages_per_day_per_widget' => (int) env('WIDGET_MESSAGES_PER_DAY_PER_WIDGET', 500),
        'maximum_question_length' => (int) env('WIDGET_MAXIMUM_QUESTION_LENGTH', 1000),
        'maximum_request_bytes' => (int) env('WIDGET_MAXIMUM_REQUEST_BYTES', 8192),
    ],
];
