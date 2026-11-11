<?php

$origins = array_values(array_unique(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        (string) env('FRONTEND_URL', 'http://localhost:3000'),
    )),
))));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins === [] ? ['http://localhost:3000'] : $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // The browser remembers this permission so a plans fetch is not an extra OPTIONS every time.
    'max_age' => 7200,

    'supports_credentials' => false,

];
