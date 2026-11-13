<?php

namespace App\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class IwindsStaging
{
    public const ROOT = '/home/ensenada/backend-laravel';

    public const ORIGIN = 'https://prueba.ensenada.gov.ar';

    public const MYSQL_COLLATIONS = ['utf8mb4_0900_ai_ci', 'utf8mb4_unicode_520_ci'];

    public static function verify(Application $app): void
    {
        $mysql = config('database.connections.mysql');
        $key = (string) config('app.key');
        $rawKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
        $jwt = (string) config('auth_migration.jwt_secret');
        if ($app->basePath() !== self::ROOT || realpath(self::ROOT) !== self::ROOT || config('app.url') !== self::ORIGIN
            || config('app.debug') !== false || config('auth_migration.local_only') || config('auth_migration.disposable_mysql')
            || ! is_string($rawKey) || strlen($rawKey) !== 32 || strlen($jwt) < 32 || in_array($jwt, [$key, $rawKey, substr($key, 7)], true)
            || config('database.default') !== 'mysql' || ! empty($mysql['url'])
            || ! in_array($mysql['collation'], self::MYSQL_COLLATIONS, true)
            || ! in_array($mysql['host'], ['localhost', '127.0.0.1'], true) || (int) $mysql['port'] !== 3306
            || $mysql['database'] !== 'ensenada_web' || $mysql['username'] !== 'ensenada_web_app' || empty($mysql['password'])) {
            throw new RuntimeException('Configuración iWinds de staging incompleta o fuera del alcance permitido.');
        }
        if ($app->runningInConsole()) {
            if (basename($_SERVER['argv'][0] ?? '') === 'artisan'
                && ! in_array($_SERVER['argv'][1] ?? '', ['route:list', 'config:cache', 'config:clear', 'route:cache', 'route:clear'], true)) {
                throw new RuntimeException('Comando Artisan fuera del perfil iWinds de staging.');
            }

            return;
        }
        $mode = IwindsPublicProbe::requestMode(self::ROOT, $_SERVER);
        $publicWeb = in_array($mode, ['web', 'web-admin'], true);
        if ($mode !== null && defined('ENSENADA_IWINDS_STAGING_MODE') && $mode === ENSENADA_IWINDS_STAGING_MODE
            && IwindsPublicProbe::allows($_SERVER, $mode) && ! array_key_exists('_method', $_POST)
            && (! $publicWeb || IwindsPublicProbe::validWebOrigin($_SERVER))
            && IwindsPublicProbe::stripApacheAuthorization($_SERVER, $publicWeb)) {
            if ($publicWeb) {
                config(['cors.paths' => []]);
            }

            return;
        }

        throw new RuntimeException('API de staging deshabilitada.');
    }

    public static function directory(string $local): string
    {
        return app()->bound('config') && config('app.env') === 'staging'
            ? storage_path('app/private/'.basename($local))
            : $local;
    }
}
