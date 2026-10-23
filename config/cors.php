<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // The browser remembers this permission so a plans fetch is not an extra OPTIONS every time.
    'max_age' => 7200,

    'supports_credentials' => false,

];
