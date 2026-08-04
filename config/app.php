<?php

return [
    'name' => env('APP_NAME', 'Agency OS'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),

    /*
     | BRD requirement: the system runs on Cairo time. Every step deadline is
     | 23:59 Africa/Cairo — never change this without a formal change request.
     */
    'timezone' => env('APP_TIMEZONE', 'Africa/Cairo'),

    /*
     | The UI is fully bilingual. Locale is persisted in session + cookie and
     | every Blade layout derives its direction from the active locale.
     */
    'locale' => env('APP_LOCALE', 'ar'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'ar_SA'),
    'supported_locales' => ['ar', 'en'],

    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
