<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web', 'tenant'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/dashboard',
    'prefix' => env('FORTIFY_PREFIX', 'api/v1/auth'),
    'domain' => null,
    'views' => false,
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],
    'passkeys' => [
        'relying_party_id' => parse_url((string) config('app.url'), PHP_URL_HOST),
        'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('PASSKEYS_ALLOWED_ORIGINS', config('app.url')))))),
        'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
        'timeout' => 60000,
    ],
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
        Features::passkeys([
            'confirmPassword' => true,
        ]),
    ],
];
