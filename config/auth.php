<?php

use App\Models\User;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
     | Single users table for all four roles (BRD §5) — there is deliberately no
     | separate authentication system per role. Credentials are username + password;
     | App\Models\User::getAuthPassword() maps to the `password_hash` column.
     */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    /*
     | The password broker stays at the framework default and is NOT wired to any
     | route: BRD §18 assigns password reset to the Manager/Admin. Approved decision
     | Q27 fixes the mail design as provider-agnostic SMTP delivered through a queue;
     | the mail module itself lands in Phase 1B. Extension point only.
     */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
