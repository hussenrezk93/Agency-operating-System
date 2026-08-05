<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        /*
         | MySQL is required, not optional — this app targets shared hosting where MySQL
         | is the only engine on offer. The schema's invariants that used to be PostgreSQL
         | DDL (jsonb, partial unique indexes, an EXCLUDE/gist constraint, a PL/pgSQL
         | trigger) are now: plain `json` columns, MySQL 8 generated-column + regular
         | unique-index pairs, an application-layer locked overlap check
         | (TemporaryLeadershipService), and a pair of native MySQL triggers, respectively.
         */
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'agencyos'),
            'username' => env('DB_USERNAME', 'agencyos'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'agencyos_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
];
