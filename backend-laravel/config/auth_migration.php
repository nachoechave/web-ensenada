<?php

return [
    'local_only' => env('AUTH_LOCAL_ONLY', false),
    'disposable_mysql' => env('AUTH_DISPOSABLE_MYSQL', false),
    'jwt_secret' => env('AUTH_JWT_SECRET', ''),
    'lifetime_minutes' => 120,
    'attempts' => 10,
    'window_seconds' => 300,
    'max_ips' => 10000,
];
