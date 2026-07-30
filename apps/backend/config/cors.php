<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'passkeys/*', 'user/passkeys*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:8080')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-ID'],
    'max_age' => 0,
    'supports_credentials' => true,
];
