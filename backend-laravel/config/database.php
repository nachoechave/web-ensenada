<?php

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', '/tmp/ensenada-auth.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'transaction_mode' => 'IMMEDIATE',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'mysql-auth'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => env('DB_COLLATION', 'utf8mb4_0900_ai_ci'),
            'timezone' => '+00:00',
            'isolation_level' => 'REPEATABLE READ',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ],
        ...(env('DB_CONNECTION', 'sqlite') === 'sqlite' ? ['news' => [
            'driver' => 'sqlite',
            'database' => '/tmp/ensenada-news.sqlite',
            'options' => [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY],
        ]] : []),
    ],
    'migrations' => ['table' => 'auth_migration_history', 'update_date_on_publish' => true],
];
