<?php

declare(strict_types=1);

$allowedOrigins = env('CORS_ALLOWED_ORIGINS')
    ? array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS')))
    : ['*'];

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];