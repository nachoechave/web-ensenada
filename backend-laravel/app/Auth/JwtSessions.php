<?php

namespace App\Auth;

use App\Models\Usuario;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;

final class JwtSessions
{
    public const COOKIE = 'WEB_ENSENADA_SESSION';

    public function issue(Usuario $usuario): string
    {
        $issued = now()->timestamp;
        $expires = $issued + config('auth_migration.lifetime_minutes') * 60;
        $token = JWT::encode([
            'iss' => 'web-ensenada',
            'sub' => $usuario->email,
            'uid' => $usuario->id,
            'iat' => $issued,
            'exp' => $expires,
            'jti' => bin2hex(random_bytes(32)),
            'roles' => $usuario->currentRoles(),
            'tokenVersion' => $usuario->token_version,
        ], config('auth_migration.jwt_secret'), 'HS256');

        DB::table('auth_sessions')->insert([
            'token_hash' => hash('sha256', $token),
            'usuario_id' => $usuario->id,
            'token_version' => $usuario->token_version,
            'created_at' => $issued,
            'expires_at' => $expires,
        ]);

        return $token;
    }

    public function token(Request $request): ?string
    {
        if ($request->hasHeader('Authorization')) {
            return app()->environment(['staging', 'production']) ? null : $request->bearerToken();
        }

        $cookie = $request->cookie(self::COOKIE);

        return is_string($cookie) ? $cookie : null;
    }

    public function authenticate(?string $token, bool $lockUser = false): ?Usuario
    {
        if (! is_string($token) || strlen($token) > 8192) {
            return null;
        }

        try {
            $claims = JWT::decode($token, new Key(config('auth_migration.jwt_secret'), 'HS256'));
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $exception) {
            return null;
        }

        if (($claims->iss ?? null) !== 'web-ensenada'
            || ! is_int($claims->uid ?? null)
            || ! is_int($claims->tokenVersion ?? null)
            || ! is_int($claims->exp ?? null)
            || ! is_int($claims->iat ?? null)
            || ! is_string($claims->sub ?? null)
            || ! is_string($claims->jti ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $claims->jti) !== 1) {
            return null;
        }

        // Writers take the user lock before session/role locks, as revocation does.
        $usuario = $lockUser ? Usuario::query()->lockForUpdate()->find($claims->uid) : null;
        if ($lockUser && ! $usuario) {
            return null;
        }
        $session = DB::table('auth_sessions')->where('token_hash', hash('sha256', $token))
            ->when($lockUser, fn ($query) => $query->lockForUpdate())->first();
        if (! $session || $session->expires_at <= now()->timestamp || (int) $session->usuario_id !== $claims->uid) {
            return null;
        }

        $usuario ??= Usuario::find($claims->uid);
        if (! $usuario || ! $usuario->activo || $usuario->email !== $claims->sub
            || $usuario->token_version !== $claims->tokenVersion
            || (int) $session->token_version !== $usuario->token_version
            || $usuario->currentRoles($lockUser) === []) {
            return null;
        }

        return $usuario;
    }

    public function pruneExpired(): void
    {
        DB::table('auth_sessions')->where('expires_at', '<=', now()->timestamp)->delete();
    }

    public function revoke(?string $token): void
    {
        if (is_string($token)) {
            DB::table('auth_sessions')->where('token_hash', hash('sha256', $token))->delete();
        }
    }

    public function cookie(string $token = ''): Cookie
    {
        return Cookie::create(self::COOKIE)
            ->withValue($token)
            ->withPath('/api')
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT)
            ->withExpires($token === '' ? 1 : now()->addMinutes(config('auth_migration.lifetime_minutes')));
    }
}
