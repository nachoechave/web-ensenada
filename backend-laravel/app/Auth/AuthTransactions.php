<?php

namespace App\Auth;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AuthTransactions
{
    public static function run(Closure $callback): mixed
    {
        return DB::transaction(function () use ($callback): mixed {
            // On PHP 8.3 Laravel uses PDO's deferred SQLite transaction, even
            // when transaction_mode is IMMEDIATE. Acquire the writer before
            // reading to avoid an impossible read-to-write lock upgrade.
            if (DB::getDriverName() === 'sqlite'
                && DB::table('auth_mutexes')->where('name', 'login')->update(['name' => 'login']) !== 1) {
                throw new RuntimeException('Falta preparar el bloqueo transaccional de autenticación.');
            }

            return $callback();
        }, attempts: 3);
    }
}
