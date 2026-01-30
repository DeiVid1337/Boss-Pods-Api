<?php

declare(strict_types=1);

return [
    'cache' => [
        'ttl' => [
            'list' => (int) env('CACHE_TTL_LIST', 120),
            'show' => (int) env('CACHE_TTL_SHOW', 300),
            'sales' => (int) env('CACHE_TTL_SALES', 30),
        ],
    ],
    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@boss-pods.test'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'admin_name' => env('SEED_ADMIN_NAME', 'Admin'),
        'demo_password' => env('SEED_DEMO_PASSWORD', 'password'),
        'demo' => env('SEED_DEMO', env('APP_ENV') !== 'production'),
        'production' => env('SEED_PRODUCTION', env('APP_ENV') === 'production'),
    ],
];
