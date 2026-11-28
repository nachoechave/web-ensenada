<?php

namespace Tests\Http;

use PDO;
use RuntimeException;

final class DisposableDatabase
{
    public static function connect(bool $runtime = false): PDO
    {
        if (getenv('DB_CONNECTION') === 'mysql') {
            if (getenv('DB_HOST') !== 'mysql-auth' || getenv('DB_DATABASE') !== 'ensenada_auth_test') {
                throw new RuntimeException('Las pruebas HTTP solo usan el MySQL descartable autorizado.');
            }

            return new PDO('mysql:host=mysql-auth;port=3306;dbname=ensenada_auth_test;charset=utf8mb4',
                $runtime ? 'auth_runtime' : 'auth_setup',
                $runtime ? 'synthetic-runtime-only-1234' : 'synthetic-setup-only-1234',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        }
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== '/tmp/ensenada-auth.sqlite') {
            throw new RuntimeException('Las pruebas HTTP solo usan el SQLite descartable autorizado.');
        }
        $connection = new PDO('sqlite:/tmp/ensenada-auth.sqlite', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $connection->exec('PRAGMA foreign_keys = ON');
        $connection->exec('PRAGMA busy_timeout = 5000');

        return $connection;
    }
}
