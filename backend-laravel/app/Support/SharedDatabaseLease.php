<?php

namespace App\Support;

use RuntimeException;

/** Filesystem lease plus a rotated database credential: exactly one backend owns ensenada_web. */
final class SharedDatabaseLease
{
    public const ROOT = '/home/ensenada/web-db-transfer';

    public const PARKED = self::ROOT.'/staging-backend';

    public const STAGE = '/home/ensenada/backend-laravel';

    public const PROD = '/home/ensenada/backend-laravel-produccion';

    private static mixed $lock = null;

    public static function managed(): bool
    {
        return is_file(self::ROOT.'/state.json');
    }

    public static function lock(bool $exclusive = false): mixed
    {
        if (is_resource(self::$lock)) {
            return self::$lock;
        }
        if (realpath(self::ROOT) !== self::ROOT || (fileperms(self::ROOT) & 0077) !== 0
            || is_link(self::ROOT.'/owner.lock')) {
            throw new RuntimeException('Control de propiedad privado no disponible.');
        }
        $handle = fopen(self::ROOT.'/owner.lock', 'c');
        if ($handle === false || ! flock($handle, $exclusive ? LOCK_EX : LOCK_SH)) {
            throw new RuntimeException('No se pudo adquirir la exclusión de la base.');
        }
        self::$lock = $handle;

        return $handle;
    }

    public static function state(): array
    {
        $path = self::ROOT.'/state.json';
        $key = self::key();
        if (is_link($path) || ! is_file($path) || (fileperms($path) & 0777) !== 0600) {
            throw new RuntimeException('Estado de transferencia inválido.');
        }
        $envelope = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $state = $envelope['state'] ?? null;
        if (! is_array($state) || ! hash_equals(hash_hmac('sha256', json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $key), $envelope['mac'] ?? '')) {
            throw new RuntimeException('Comprobante de propiedad inválido.');
        }

        return $state;
    }

    public static function save(array $state): void
    {
        // Callers hold the exclusive lease for the entire transition, not just this write.
        $bytes = json_encode(['state' => $state, 'mac' => hash_hmac('sha256', json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), self::key())], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        self::atomic(self::ROOT.'/state.json', $bytes);
    }

    public static function key(): string
    {
        $path = self::ROOT.'/control.key';
        if (is_link($path) || ! is_file($path) || (fileperms($path) & 0777) !== 0600) {
            throw new RuntimeException('Clave de control no disponible.');
        }
        $key = file_get_contents($path);
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $key)) {
            throw new RuntimeException('Clave de control inválida.');
        }

        return $key;
    }

    public static function atomic(string $path, string $bytes): void
    {
        if (realpath(dirname($path)) !== dirname($path) || is_link($path)) {
            throw new RuntimeException('Destino privado no canónico.');
        }
        $tmp = $path.'.tmp-'.bin2hex(random_bytes(8));
        $file = fopen($tmp, 'xb');
        if ($file === false) {
            throw new RuntimeException('No se pudo crear el archivo privado.');
        }
        try {
            if (fwrite($file, $bytes) !== strlen($bytes) || ! fflush($file) || ! fsync($file) || ! chmod($tmp, 0600) || ! rename($tmp, $path)) {
                throw new RuntimeException('Escritura privada incompleta.');
            }
        } finally {
            fclose($file);
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }

    public static function role(bool $web = false): string
    {
        $state = self::state();
        if (($state['owner'] ?? '') === 'production' && in_array($state['phase'] ?? '', $web ? ['active'] : ['prepared', 'active'], true)) {
            if (realpath(self::PARKED) !== self::PARKED || ! is_file(self::STAGE.'/.database-parked')
                || trim((string) file_get_contents(self::STAGE.'/.database-parked')) !== ($state['epoch'] ?? '')
                || is_file(self::STAGE.'/vendor/autoload.php') || is_file(self::STAGE.'/artisan')) {
                throw new RuntimeException('Staging no está aparcado.');
            }

            return 'production';
        }
        if (($state['owner'] ?? '') === 'recovery' && ($state['phase'] ?? '') === 'active' && ($state['expires_at'] ?? 0) > time()
            && ! is_file(self::PROD.'/.iwinds-production-enabled') && realpath(self::STAGE) === self::STAGE
            && ! is_dir(self::PARKED)) {
            return 'recovery';
        }
        throw new RuntimeException('Ambos backends deben permanecer cerrados en esta fase.');
    }

    public static function production(bool $web): void
    {
        self::lock();
        if (self::role($web) !== 'production') {
            throw new RuntimeException('Producción no es propietaria de la base.');
        }
        if ($web) {
            require_once __DIR__.'/ProductionDatabaseProfile.php';
            ProductionDatabaseProfile::verifyActivation();
        }
    }

    public static function recoveryActive(): bool
    {
        try {
            return self::role(true) === 'recovery';
        } catch (\Throwable) {
            return false;
        }
    }

    public static function guardStaging(object $app): void
    {
        self::lock();
        $initial = self::state();
        if (($initial['owner'] ?? '') === 'staging' && ($initial['phase'] ?? '') === 'legacy'
            && ! is_file(self::PROD.'/.iwinds-production-enabled')) {
            return;
        }
        if (! self::recoveryActive()) {
            throw new RuntimeException('Staging sin acceso a la base transferida.');
        }
        require_once __DIR__.'/ProductionDatabaseProfile.php';
        require_once __DIR__.'/ProductionPermissions.php';
        ProductionDatabaseProfile::verifyActivation();
        if ($app->runningInConsole() && (basename($_SERVER['argv'][0] ?? '') !== 'artisan'
            || ! in_array($_SERVER['argv'][1] ?? '', ['route:list', 'config:cache', 'config:clear', 'route:cache', 'route:clear'], true))) {
            throw new RuntimeException('Solo comandos de caché/rutas durante recuperación.');
        }
        $state = self::state();
        if (! hash_equals($state['fingerprint'] ?? '', self::fingerprint([
            'APP_KEY' => config('app.key'), 'AUTH_JWT_SECRET' => config('auth_migration.jwt_secret'),
            'DB_PASSWORD' => config('database.connections.mysql.password')], true))) {
            throw new RuntimeException('Credencial de recuperación no vigente.');
        }
        ProductionPermissions::verify();
    }

    public static function legacyStage(): bool
    {
        try {
            $state = self::state();

            return ($state['owner'] ?? '') === 'staging' && ($state['phase'] ?? '') === 'legacy';
        } catch (\Throwable) {
            return false;
        }
    }

    public static function fingerprint(array $env, bool $recovery = false): string
    {
        return hash_hmac('sha256', implode('|', [$recovery ? self::STAGE : self::PROD,
            $recovery ? 'https://prueba.ensenada.gov.ar' : 'https://www.ensenada.gov.ar', 'ensenada_web', 'ensenada_web_app',
            $env['AUTH_JWT_SECRET'] ?? '', hash('sha256', $env['DB_PASSWORD'] ?? '')]), $env['APP_KEY'] ?? '');
    }
}
