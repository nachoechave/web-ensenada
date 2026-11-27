<?php

namespace App\Support;

use Dotenv\Dotenv;
use Illuminate\Foundation\Application;
use RuntimeException;

final class IwindsProduction
{
    public const ROOT = '/home/ensenada/backend-laravel-produccion';

    public const ORIGIN = 'https://www.ensenada.gov.ar';

    public const MARKER = '.iwinds-production-enabled';

    public static function fingerprint(): string
    {
        return hash_hmac('sha256', implode('|', [self::ROOT, self::ORIGIN,
            config('database.connections.mysql.database'), config('database.connections.mysql.username'),
            config('auth_migration.jwt_secret'), hash('sha256', (string) config('database.connections.mysql.password'))]), (string) config('app.key'));
    }

    public static function enabled(): bool
    {
        $path = self::ROOT.'/'.self::MARKER;
        clearstatcache(true, $path);

        return ! is_link($path) && is_file($path) && (fileperms($path) & 0777) === 0600
            && hash_equals('production-v1 '.self::fingerprint()."\n", (string) file_get_contents($path));
    }

    public static function verify(Application $app): void
    {
        $db = config('database.connections.mysql');
        $key = (string) config('app.key');
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
        $jwt = (string) config('auth_migration.jwt_secret');
        if ($app->basePath() !== self::ROOT || realpath(self::ROOT) !== self::ROOT
            || config('app.url') !== self::ORIGIN || config('app.debug') !== false
            || config('auth_migration.local_only') || config('auth_migration.disposable_mysql')
            || ! is_string($raw) || strlen($raw) !== 32 || strlen($jwt) < 64
            || in_array($jwt, [$key, $raw, substr($key, 7)], true)
            || config('database.default') !== 'mysql' || ! empty($db['url']) || ! empty($db['unix_socket'])
            || ! in_array($db['host'], ['localhost', '127.0.0.1'], true) || (int) $db['port'] !== 3306
            || $db['database'] !== 'ensenada_web' || $db['username'] !== 'ensenada_web_app'
            || empty($db['password']) || $db['collation'] !== 'utf8mb4_unicode_520_ci') {
            throw new RuntimeException('Producción deshabilitada: perfil inválido.');
        }
        SharedDatabaseLease::production(! $app->runningInConsole());
        if (! hash_equals(SharedDatabaseLease::state()['fingerprint'] ?? '', self::fingerprint())) {
            throw new RuntimeException('Credencial de producción fuera de la transferencia autorizada.');
        }
        if ($app->runningInConsole()) {
            if (basename($_SERVER['argv'][0] ?? '') === 'artisan'
                && ! in_array($_SERVER['argv'][1] ?? '', ['route:list', 'config:cache', 'config:clear', 'route:cache', 'route:clear'], true)) {
                throw new RuntimeException('Comando de producción no autorizado.');
            }

            return;
        }
        if (! defined('ENSENADA_IWINDS_PRODUCTION') || ! self::enabled() || ! self::isolationVerified() || ! self::allows($_SERVER)
            || array_key_exists('_method', $_POST)) {
            throw new RuntimeException('Producción deshabilitada.');
        }
        ProductionPermissions::verify();
    }

    public static function verifyIndependentStageSecrets(): void
    {
        $file = SharedDatabaseLease::PARKED.'/.env';
        if (is_link($file) || ! is_file($file) || ! is_readable($file)) {
            throw new RuntimeException('No se pudo comprobar el aislamiento inicial de staging.');
        }
        $stage = Dotenv::parse(file_get_contents($file));
        if (empty($stage['APP_KEY']) || empty($stage['AUTH_JWT_SECRET']) || empty($stage['DB_PASSWORD'])
            || $stage['APP_KEY'] === config('app.key') || $stage['AUTH_JWT_SECRET'] === config('auth_migration.jwt_secret')
            || $stage['DB_PASSWORD'] === config('database.connections.mysql.password')) {
            throw new RuntimeException('Las claves y credenciales deben ser independientes de staging.');
        }
    }

    public static function isolationVerified(): bool
    {
        $path = self::ROOT.'/.production-isolation';

        return ! is_link($path) && is_file($path) && (fileperms($path) & 0777) === 0600
            && hash_equals('isolation-v1 '.self::fingerprint()."\n", (string) file_get_contents($path));
    }

    public static function allows(array $server): bool
    {
        $method = $server['REQUEST_METHOD'] ?? '';
        $target = $server['REQUEST_URI'] ?? '';
        $line = $server['REDIRECT_ENSENADA_PRODUCTION_LINE'] ?? $server['ENSENADA_PRODUCTION_LINE'] ?? '';
        if (($server['HTTPS'] ?? '') !== 'on' || ($server['HTTP_HOST'] ?? '') !== 'www.ensenada.gov.ar'
            || ($server['REDIRECT_ENSENADA_PRODUCTION_ROUTE'] ?? '') !== '1'
            || preg_match('~\A([A-Z]+) ([\x21-\x7e]+) HTTP/(?:1\.[01]|[23](?:\.0)?)\z~D', $line, $parts) !== 1
            || $parts[1] !== $method || $parts[2] !== $target) {
            return false;
        }
        foreach (array_keys($server) as $name) {
            if (str_starts_with($name, 'HTTP_X_FORWARDED_') || in_array($name, [
                'HTTP_FORWARDED', 'HTTP_X_REAL_IP', 'HTTP_X_ORIGINAL_HOST', 'HTTP_X_HOST', 'HTTP_REMOTE_USER',
                'HTTP_TRANSFER_ENCODING', 'HTTP_X_HTTP_METHOD_OVERRIDE', 'HTTP_X_HTTP_METHOD', 'HTTP_X_METHOD_OVERRIDE',
                'HTTP_X_ORIGINAL_METHOD', 'HTTP_X_ORIGINAL_URL', 'HTTP_X_ORIGINAL_URI', 'HTTP_X_REWRITE_URL',
            ], true)) {
                return false;
            }
        }
        if (! IwindsPublicProbe::stripApacheAuthorization($server, true)) {
            return false;
        }
        $read = in_array($method, ['GET', 'HEAD'], true);
        if (($read && ! in_array($server['CONTENT_LENGTH'] ?? '', ['', '0'], true))
            || (isset($server['HTTP_ORIGIN']) && $server['HTTP_ORIGIN'] !== self::ORIGIN)
            || (! $read && (($server['HTTP_ORIGIN'] ?? '') !== self::ORIGIN
                || ($server['HTTP_SEC_FETCH_SITE'] ?? '') !== 'same-origin'))) {
            return false;
        }
        [$path, $query] = array_pad(explode('?', $target, 2), 2, null);
        if ($path === '/api/noticias' && $read) {
            return $query === null || preg_match('/\A(?:page=[0-9]{1,10}(?:&size=[0-9]{1,10})?|size=[0-9]{1,10}(?:&page=[0-9]{1,10})?)\z/D', $query) === 1;
        }
        if ($read && preg_match('~\A/uploads/hacienda/[a-f0-9-]{36}\.(?:pdf|jpg|png)\z~D', $path)) {
            return $query === null || preg_match('/\Adescargar=(?:[01]|true|false|yes|no|on|off)?\z/D', $query) === 1;
        }
        if ($query !== null) {
            return false;
        }
        $id = '[1-9][0-9]{0,18}';
        if ($read && (in_array($path, ['/api/site-content', '/api/hacienda', '/api/proveedores/documentos'], true)
            || preg_match("~\A/api/(?:noticias/[a-z0-9][a-z0-9-]{0,279}|hacienda/$id)\z~D", $path)
            || preg_match('~\A/uploads/(?:noticias|sitio)/[A-Za-z0-9_-][A-Za-z0-9._-]{0,245}\.(?i:jpg|jpeg|png|webp)\z~D', $path)
            || preg_match('~\A/uploads/proveedores/[a-f0-9-]{36}\.(?:pdf|doc|xls|xlsx)\z~D', $path))) {
            return true;
        }
        $pattern = match ($method) {
            'GET', 'HEAD' => "auth/me|admin/(?:usuarios|noticias(?:/auditoria|/$id)?|site-content|proveedores/documentos|hacienda(?:/$id(?:/archivos/$id/contenido)?)?)",
            'POST' => "auth/(?:login|logout)|admin/(?:usuarios|noticias|archivos/(?:noticias|sitio)|hacienda(?:/$id/archivos)?|proveedores/documentos)",
            'PUT' => "auth/me(?:/password)?|admin/(?:usuarios/$id/(?:roles|activo|password)|noticias/$id|site-content|hacienda/$id(?:/archivos/orden)?|proveedores/documentos/(?:orden|$id))",
            'DELETE' => "admin/(?:noticias/$id|hacienda/$id(?:/archivos/$id)?|proveedores/documentos/$id)",
            default => '',
        };

        return $pattern !== '' && preg_match("~\A/api/(?:$pattern)\z~D", $path) === 1;
    }
}
