<?php

$origins = array_values(array_filter(array_map('trim', explode(',', (string) env('PASSKEYS_ALLOWED_ORIGINS', config('app.url'))))));

return [
    'relying_party_id' => parse_url((string) config('app.url'), PHP_URL_HOST),
    'allowed_origins' => $origins,
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
    'timeout' => 60000,
    'guard' => 'web',
    'middleware' => ['web', 'tenant'],
    'management_middleware' => ['auth', 'password.confirm'],
    'throttle' => 'throttle:6,1',
    'redirect' => '/dashboard',
];
