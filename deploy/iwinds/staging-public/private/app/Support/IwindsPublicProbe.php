<?php

namespace App\Support;

final class IwindsPublicProbe
{
    public const MARKER = '.iwinds-api-public-probe';

    public const FULL_MARKER = '.iwinds-api-enabled';

    public const ADMIN_MARKER = '.iwinds-api-admin-probe';

    public const WEB_MARKER = '.iwinds-staging-public-web';

    public const ADMIN_PERIOD_LOCK = '.iwinds-staging-admin-period.lock';

    public const ADMIN_PERIOD_MAX_DAYS = 7;

    public const ROTATION = '.iwinds-staging-admin-rotation';

    public const IMAGE_PATH = '/uploads/noticias/00000000-0000-4000-8000-000000000001.png';

    // One fixed 1 × 1 PNG. The probe never reads upload storage.
    public const IMAGE_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMQiWoCAAF2APEh5xLIAAAAAElFTkSuQmCC';

    public static function mode(string $backend): ?string
    {
        $present = self::presentMarkers($backend);
        if (count($present) !== 1) {
            return null;
        }
        $mode = array_key_first($present);
        $path = $present[$mode];
        if (is_link($path) || ! is_file($path)) {
            return null;
        }
        $marker = @file_get_contents($path, length: 128);
        if ($mode === 'admin' && is_string($marker) && str_starts_with($marker, 'extended-v1 ')) {
            return self::extendedExpiry($backend, $path, $marker) === null ? null : 'admin';
        }
        $format = $mode === 'public' ? '/\A([0-9]{10})( synthetic-image)?\n\z/' : '/\A([0-9]{10}) ([a-f0-9]{64})\n\z/';
        if ($marker === false || preg_match($format, $marker, $parts) !== 1) {
            return null;
        }
        $secondsLeft = (int) $parts[1] - time();
        if ($secondsLeft <= 0 || $secondsLeft > 3600) {
            return null;
        }
        if ($mode !== 'public') {
            $receipt = self::rotation($backend);

            return $receipt !== null && hash_equals(self::privilegedMarker($mode, (int) $parts[1], $receipt['proof']), $marker)
                ? $mode : null;
        }

        return isset($parts[2]) ? 'public-image' : 'public';
    }

    public static function privilegedMarker(string $mode, int $expiresAt, string $proof): string
    {
        return $expiresAt.' '.hash_hmac('sha256', $mode.':'.$expiresAt, $proof)."\n";
    }

    public static function extendedAdminMarker(int $startsAt, int $expiresAt, string $proof): string
    {
        $period = "extended-v1 synthetic-admin $startsAt $expiresAt";

        return $period.' '.hash_hmac('sha256', $period, $proof)."\n";
    }

    public static function activeAdminPeriodExpiry(string $backend): ?int
    {
        $present = self::presentMarkers($backend);
        $path = $present['admin'] ?? null;
        if (count($present) !== 1 || $path === null || is_link($path) || ! is_file($path)) {
            return null;
        }
        $marker = @file_get_contents($path, length: 128);

        return is_string($marker) ? self::extendedExpiry($backend, $path, $marker) : null;
    }

    private static function extendedExpiry(string $backend, string $path, string $marker): ?int
    {
        $permissions = @fileperms($path);
        if ($permissions === false || ($permissions & 0777) !== 0600
            || preg_match('/\Aextended-v1 synthetic-admin ([0-9]{10}) ([0-9]{10}) [a-f0-9]{64}\n\z/', $marker, $parts) !== 1) {
            return null;
        }
        $startsAt = (int) $parts[1];
        $expiresAt = (int) $parts[2];
        $now = time();
        if ($startsAt > $now || $expiresAt <= $now || $expiresAt - $startsAt > self::ADMIN_PERIOD_MAX_DAYS * 86400) {
            return null;
        }
        $receipt = self::rotation($backend);

        return $receipt !== null && hash_equals(self::extendedAdminMarker($startsAt, $expiresAt, $receipt['proof']), $marker)
            ? $expiresAt : null;
    }

    public static function presentMarkers(string $backend): array
    {
        clearstatcache();
        $paths = [
            'public' => $backend.'/'.self::MARKER,
            'admin' => $backend.'/'.self::ADMIN_MARKER,
            'full' => $backend.'/'.self::FULL_MARKER,
        ];

        return array_filter($paths, static fn (string $path): bool => file_exists($path) || is_link($path));
    }

    public static function webMarker(string $proof): string
    {
        return 'synthetic-web-v1 '.hash_hmac('sha256', 'staging-public-web:v1', $proof)."\n";
    }

    public static function webMarkerPresent(string $backend): bool
    {
        clearstatcache();
        $path = $backend.'/'.self::WEB_MARKER;

        return file_exists($path) || is_link($path);
    }

    public static function webEnabled(string $backend): bool
    {
        clearstatcache();
        $path = $backend.'/'.self::WEB_MARKER;
        $permissions = @fileperms($path);
        if (is_link($path) || ! is_file($path) || $permissions === false || ($permissions & 0777) !== 0600
            || array_diff_key(self::presentMarkers($backend), ['admin' => true]) !== []) {
            return false;
        }
        $receipt = self::rotation($backend);
        $marker = @file_get_contents($path, length: 128);

        return $receipt !== null && is_string($marker) && hash_equals(self::webMarker($receipt['proof']), $marker);
    }

    public static function requestMode(string $backend, array $server): ?string
    {
        if (! self::webMarkerPresent($backend)) {
            return self::mode($backend);
        }
        if (! self::webEnabled($backend)) {
            return null;
        }
        $path = explode('?', $server['REQUEST_URI'] ?? '', 2)[0];
        if (preg_match('~\A/api/(?:auth|admin)/~D', $path) === 1) {
            return self::activeAdminPeriodExpiry($backend) === null ? 'web' : 'web-admin';
        }

        return 'web';
    }

    public static function validWebOrigin(array $server): bool
    {
        $origin = $server['HTTP_ORIGIN'] ?? null;
        $expected = 'https://prueba.ensenada.gov.ar';

        return ($origin === null || $origin === $expected)
            && (in_array($server['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)
                || ($origin === $expected && ($server['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin'));
    }

    public static function rotation(string $backend): ?array
    {
        $file = $backend.'/'.self::ROTATION;
        if (is_link($file) || ! is_file($file)) {
            return null;
        }
        $record = @file_get_contents($file, length: 120);
        if ($record === false || preg_match('/\A([1-9][0-9]{0,18}) ([1-9][0-9]{0,18}) ([a-f0-9]{64})\n\z/', $record, $parts) !== 1) {
            return null;
        }

        return ['id' => $parts[1], 'version' => $parts[2], 'proof' => $parts[3]];
    }

    public static function stripApacheAuthorization(array &$server, bool $publicWeb = false): bool
    {
        $authorization = ['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'AUTHORIZATION', 'REDIRECT_AUTHORIZATION'];
        foreach ($authorization as $key) {
            if (isset($server[$key]) && $server[$key] !== ''
                && preg_match('/\ABasic [A-Za-z0-9+\/]+={0,2}\z/i', $server[$key]) !== 1) {
                return false;
            }
        }
        // Basic is stripped for compatibility while Directory Privacy is kept during the transition.
        if ($publicWeb && isset($server['PHP_AUTH_DIGEST'])) {
            return false;
        }
        foreach ([...$authorization, 'PHP_AUTH_USER', 'PHP_AUTH_PW', 'PHP_AUTH_DIGEST'] as $key) {
            unset($server[$key]);
        }

        return true;
    }

    public static function allows(array $server, string $mode): bool
    {
        $method = $server['REQUEST_METHOD'] ?? '';
        $target = $server['REQUEST_URI'] ?? '';
        $line = $server['REDIRECT_ENSENADA_REQUEST_LINE'] ?? $server['ENSENADA_REQUEST_LINE'] ?? '';
        $user = $server['REMOTE_USER'] ?? $server['REDIRECT_REMOTE_USER'] ?? '';
        $publicWeb = in_array($mode, ['web', 'web-admin'], true);
        $administrative = in_array($mode, ['admin', 'full'], true);
        $methods = $administrative || $mode === 'web-admin' ? ['GET', 'HEAD', 'POST', 'PUT', 'DELETE'] : ['GET', 'HEAD'];
        $hosts = $publicWeb ? ['prueba.ensenada.gov.ar'] : ['prueba.ensenada.gov.ar', 'prueba.ensenada.gov.ar:443'];
        if (($server['HTTPS'] ?? '') !== 'on' || (! $publicWeb && $user === '')
            || ! in_array($server['HTTP_HOST'] ?? '', $hosts, true)
            || ! in_array($method, $methods, true)
            || preg_match('~\A([A-Z]+) ([\x21-\x7e]+) HTTP/(?:1\.[01]|[23](?:\.0)?)\z~', $line, $parts) !== 1
            || $parts[1] !== $method || $parts[2] !== $target
            || ((! $administrative && $mode !== 'web-admin' || in_array($method, ['GET', 'HEAD'], true))
                && ! in_array($server['CONTENT_LENGTH'] ?? '', ['', '0'], true))) {
            return false;
        }
        foreach ([
            'HTTP_TRANSFER_ENCODING', 'HTTP_X_HTTP_METHOD_OVERRIDE', 'HTTP_X_HTTP_METHOD', 'HTTP_X_METHOD_OVERRIDE',
            'HTTP_X_ORIGINAL_METHOD', 'HTTP_X_ORIGINAL_URL', 'HTTP_X_ORIGINAL_URI', 'HTTP_X_REWRITE_URL',
            'HTTP_X_FORWARDED_PREFIX', 'HTTP_X_FORWARDED_URI',
        ] as $header) {
            if (isset($server[$header])) {
                return false;
            }
        }

        [$path, $query] = array_pad(explode('?', $target, 2), 2, null);
        if ($publicWeb) {
            if (($server['REDIRECT_ENSENADA_STAGING_ROUTE'] ?? '') !== '1'
                || self::hasForwardedHeaders($server)) {
                return false;
            }

            return $mode === 'web-admin' ? $query === null && self::allowsWebAdmin($path, $method)
                : self::allowsWebRead($path, $query);
        }
        if ($path === '/api/noticias') {
            return $query === null || preg_match('/\A(?:page=[0-9]{1,10}(?:&size=[0-9]{1,10})?|size=[0-9]{1,10}(?:&page=[0-9]{1,10})?)\z/', $query) === 1;
        }
        if ($query !== null) {
            return false;
        }

        if ($administrative) {
            return preg_match('~\A/api(?:/[a-z0-9][a-z0-9-]{0,279})+\z~', $path) === 1
                || (in_array($method, ['GET', 'HEAD'], true)
                    && (preg_match('~\A/uploads/(?:noticias|sitio|hacienda|proveedores)/[A-Za-z0-9_-][A-Za-z0-9._-]{0,245}\.(?i:jpg|jpeg|png|pdf|doc|xls|xlsx)\z~', $path) === 1
                        || preg_match('~\A/uploads/(?:noticias|sitio)/[A-Za-z0-9_-][A-Za-z0-9._-]{0,245}\.(?i:webp)\z~', $path) === 1));
        }

        return in_array($path, ['/api/site-content', '/api/hacienda', '/api/proveedores/documentos'], true)
            || preg_match('~\A/api/noticias/[a-z0-9][a-z0-9-]{0,279}\z~', $path) === 1
            || preg_match('~\A/api/hacienda/[1-9][0-9]{0,18}\z~', $path) === 1
            || ($mode === 'public-image' && $path === self::IMAGE_PATH);
    }

    private static function hasForwardedHeaders(array $server): bool
    {
        foreach (array_keys($server) as $name) {
            if (str_starts_with($name, 'HTTP_X_FORWARDED_') || in_array($name,
                ['HTTP_FORWARDED', 'HTTP_X_REAL_IP', 'HTTP_X_ORIGINAL_HOST', 'HTTP_X_HOST', 'HTTP_REMOTE_USER'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function allowsWebRead(string $path, ?string $query): bool
    {
        if ($path === '/api/noticias') {
            return $query === null || preg_match('/\A(?:page=[0-9]{1,10}(?:&size=[0-9]{1,10})?|size=[0-9]{1,10}(?:&page=[0-9]{1,10})?)\z/', $query) === 1;
        }
        if (preg_match('~\A/uploads/hacienda/[a-f0-9-]{36}\.(?:pdf|jpg|png)\z~D', $path)) {
            return $query === null || preg_match('/\Adescargar=(?:[01]|true|false|yes|no|on|off)?\z/D', $query) === 1;
        }

        return $query === null && (
            in_array($path, ['/api/site-content', '/api/hacienda', '/api/proveedores/documentos'], true)
            || preg_match('~\A/api/(?:noticias/[a-z0-9][a-z0-9-]{0,279}|hacienda/[1-9][0-9]{0,18})\z~D', $path)
            || preg_match('~\A/uploads/(?:noticias|sitio)/[A-Za-z0-9_-][A-Za-z0-9._-]{0,245}\.(?i:jpg|jpeg|png|webp)\z~D', $path)
            || preg_match('~\A/uploads/proveedores/[a-f0-9-]{36}\.(?:pdf|doc|xls|xlsx)\z~D', $path)
        );
    }

    private static function allowsWebAdmin(string $path, string $method): bool
    {
        $id = '[1-9][0-9]{0,18}';
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
