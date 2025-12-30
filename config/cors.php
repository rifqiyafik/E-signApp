<?php

return [
    'paths' => [
        'api/*',
        '*/api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS', 'http://127.0.0.1:8000,http://localhost:8000')
    ))),

    'allowed_origins_patterns' => array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS_PATTERNS', '')
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
