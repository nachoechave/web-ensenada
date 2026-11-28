<?php

namespace App\Http\Middleware;

use App\Auth\AuthTransactions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LoginRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $retry = AuthTransactions::run(function () use ($request): int {
            if (! DB::table('auth_mutexes')->where('name', 'login')->lockForUpdate()->first()) {
                throw new \RuntimeException('Falta preparar el bloqueo transaccional de autenticación.');
            }
            $now = now()->timestamp;
            $key = hash('sha256', (string) $request->server('REMOTE_ADDR'));
            DB::table('auth_login_windows')->where('expires_at', '<=', $now)->delete();
            if (! DB::table('auth_login_windows')->where('ip_hash', $key)->exists()
                && DB::table('auth_login_windows')->count() >= config('auth_migration.max_ips')) {
                return config('auth_migration.window_seconds');
            }

            DB::table('auth_login_windows')->insertOrIgnore([
                'ip_hash' => $key, 'attempts' => 0,
                'expires_at' => $now + config('auth_migration.window_seconds'),
            ]);
            $window = DB::table('auth_login_windows')->where('ip_hash', $key)->first();
            if ($window->attempts >= config('auth_migration.attempts')) {
                return max(1, $window->expires_at - $now);
            }
            DB::table('auth_login_windows')->where('ip_hash', $key)->increment('attempts');

            return 0;
        });

        if ($retry > 0) {
            return response()->json(['mensaje' => 'Demasiados intentos. Esperá antes de volver a iniciar sesión.'], 429)
                ->header('Retry-After', (string) $retry);
        }

        return $next($request);
    }
}
