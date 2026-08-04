<?php

/*
 | APPROVED DECISION Q27 — provider-agnostic SMTP. No provider name appears in any
 | domain service; host, port, encryption and credentials come from the environment
 | (never the database). Changing the company's mail host is a configuration change.
 */
return [
    'default' => env('MAIL_MAILER', 'log'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => (int) env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
        'array' => ['transport' => 'array'],
        'failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.test'),
        'name' => env('MAIL_FROM_NAME', 'Agency OS'),
    ],
];
