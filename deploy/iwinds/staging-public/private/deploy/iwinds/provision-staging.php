<?php

use App\Auth\JavaBcrypt;
use App\Auth\Role;
use App\Content\SiteContentDocument;
use App\Models\Usuario;
use App\Support\IwindsPublicProbe;
use App\Support\IwindsStaging;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

ini_set('zend.exception_ignore_args', '1');
$arguments = array_slice($argv, 1);
$operation = array_shift($arguments);
$periodChange = in_array($operation, ['open-admin-extended', 'renew-admin-extended'], true);
$periodLocal = in_array($operation, ['close-admin-extended', 'status-admin-extended'], true);
$webOpen = $operation === 'open-public-web' && ($arguments[0] ?? '') === '--synthetic-only';
$webLocal = in_array($operation, ['close-public-web', 'status-public-web', 'suspend-staging-api'], true);
if ($webOpen) {
    array_shift($arguments);
}
$days = null;
$generation = null;
if ($periodChange && preg_match('/\A[1-7]\z/', $arguments[0] ?? '') === 1 && ($arguments[1] ?? '') === '--synthetic-only') {
    $days = (int) $arguments[0];
    $arguments = array_slice($arguments, 2);
}
if ((! $periodChange && ! $periodLocal && ! $webOpen && ! $webLocal && ! in_array($operation, ['status', 'site-content', 'first-admin', 'rotate-admin', 'open-admin', 'open-full'], true))
    || ($periodChange && $days === null) || ! in_array($arguments, [[], ['--disposable']], true)) {
    fwrite(STDERR, "Uso: php provision-staging.php status|site-content|first-admin|rotate-admin|open-admin|open-full\n"
        ."     php provision-staging.php open-admin-extended|renew-admin-extended DIAS (1 a 7) --synthetic-only\n"
        ."     php provision-staging.php status-admin-extended|close-admin-extended\n"
        ."     php provision-staging.php open-public-web --synthetic-only\n"
        ."     php provision-staging.php status-public-web|close-public-web|suspend-staging-api\n");
    exit(1);
}

const PROVISION_MARKERS = ['site-content' => 'provision-site-content', 'first-admin' => 'provision-first-admin'];

function requireClosedApi(string $backend): void
{
    if (IwindsPublicProbe::presentMarkers($backend) !== [] || IwindsPublicProbe::webMarkerPresent($backend)) {
        throw new DomainException('La API debe estar deshabilitada. No debe haber marcadores activos ni vencidos.');
    }
}

function readProvisionLine(string $prompt): string
{
    fwrite(STDERR, $prompt);
    $line = fgets(STDIN, 1024);
    if ($line === false || ! str_ends_with($line, "\n")) {
        throw new DomainException('Entrada incompleta. Operación cancelada.');
    }

    return rtrim($line, "\r\n");
}

function provisionStty(string $argument): string
{
    $process = @proc_open(['stty', $argument], [STDIN, ['pipe', 'w'], ['file', '/dev/null', 'w']], $pipes);
    if (! is_resource($process)) {
        throw new DomainException('No se pudo controlar el eco de la terminal.');
    }
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0 || $result === false) {
        throw new DomainException('No se pudo controlar el eco de la terminal.');
    }

    return trim($result);
}

function promptAdminPassword(): string
{
    if (! function_exists('proc_open') || ! stream_isatty(STDIN) || ! stream_isatty(STDERR)) {
        throw new DomainException('La operación requiere una terminal interactiva con lectura de contraseña oculta.');
    }
    $mode = provisionStty('-g');
    if ($mode === '') {
        throw new DomainException('No se pudo verificar el modo de la terminal.');
    }

    try {
        provisionStty('-echo');
        $password = readProvisionLine('Contraseña nueva (oculta): ');
        fwrite(STDERR, PHP_EOL);
        $confirmation = readProvisionLine('Confirmar contraseña (oculta): ');
        fwrite(STDERR, PHP_EOL);
    } finally {
        provisionStty($mode);
    }

    if (! hash_equals($password, $confirmation) || strlen($password) < 20 || strlen($password) > 72
        || ! mb_check_encoding($password, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $password)
        || $password !== trim($password)) {
        throw new DomainException('La contraseña debe coincidir y tener de 20 a 72 bytes, sin controles ni espacios en los extremos.');
    }

    return $password;
}

function writeStagingFile(string $backend, string $name, string $contents): void
{
    $temp = tempnam($backend, '.iwinds-write-');
    if ($temp === false) {
        throw new DomainException('No se pudo preparar el archivo privado de staging.');
    }
    try {
        if (dirname($temp) !== $backend || is_link($backend.'/'.$name) || ! chmod($temp, 0600)
            || file_put_contents($temp, $contents) !== strlen($contents) || ! rename($temp, $backend.'/'.$name)) {
            throw new DomainException('No se pudo instalar el archivo privado de staging.');
        }
    } finally {
        if (is_file($temp)) {
            unlink($temp);
        }
    }
}

function rotationProof(Usuario $user, string $version): string
{
    return hash_hmac('sha256', $user->id.':'.$version.':'.$user->password, config('auth_migration.jwt_secret'));
}

function matchesGrantProfile(bool $disposable, bool $cpanel): bool
{
    $command = [PHP_BINARY, __DIR__.'/schema-check.php'];
    if ($disposable) {
        $command[] = '--disposable';
    }
    if ($cpanel) {
        $command[] = '--privilege-profile=cpanel-dml';
    }
    $process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);

    return is_resource($process) && proc_close($process) === 0;
}

function withAdminPeriodLock(string $backend, Closure $action): mixed
{
    $path = $backend.'/'.IwindsPublicProbe::ADMIN_PERIOD_LOCK;
    clearstatcache(true, $path);
    if (is_link($path) || (file_exists($path) && ! is_file($path))) {
        throw new DomainException('El bloqueo privado de staging no es válido.');
    }
    $mask = umask(0077);
    try {
        $lock = @fopen($path, 'c+');
    } finally {
        umask($mask);
    }
    if ($lock === false) {
        throw new DomainException('No se pudo abrir el bloqueo privado de staging.');
    }
    try {
        $entry = @lstat($path);
        $opened = fstat($lock);
        if ($entry === false || $opened === false || ($entry['mode'] & 0170777) !== 0100600
            || $entry['nlink'] !== 1 || $entry['ino'] !== $opened['ino'] || $entry['dev'] !== $opened['dev']
            || ! flock($lock, LOCK_EX)) {
            throw new DomainException('No se pudo verificar el bloqueo privado de staging.');
        }

        return $action($lock);
    } finally {
        fclose($lock);
    }
}

function invalidateAdminPeriodOperations($lock): string
{
    $generation = bin2hex(random_bytes(16));
    if (! rewind($lock) || ! ftruncate($lock, 0) || fwrite($lock, $generation) !== strlen($generation) || ! fflush($lock)) {
        throw new DomainException('No se pudo registrar el cambio de estado de staging.');
    }

    return $generation;
}

function checkAdminPeriodTransition(string $backend, string $operation): ?int
{
    if (IwindsPublicProbe::webMarkerPresent($backend) && ! IwindsPublicProbe::webEnabled($backend)) {
        throw new DomainException('El marcador público es inválido o existe un conflicto de activación.');
    }
    if ($operation === 'open-admin-extended') {
        if (IwindsPublicProbe::presentMarkers($backend) !== []) {
            throw new DomainException('Retirá los marcadores administrativos o de diagnóstico anteriores.');
        }

        return null;
    }
    $expires = IwindsPublicProbe::activeAdminPeriodExpiry($backend);
    if ($expires === null) {
        throw new DomainException('No hay un período administrativo prolongado vigente. Cerrá el marcador previo antes de abrir otro.');
    }

    return $expires;
}

function checkPublicWebTransition(string $backend): void
{
    $markers = IwindsPublicProbe::presentMarkers($backend);
    if (array_diff_key($markers, ['admin' => true]) !== []
        || (isset($markers['admin']) && IwindsPublicProbe::activeAdminPeriodExpiry($backend) === null)
        || (IwindsPublicProbe::webMarkerPresent($backend) && ! IwindsPublicProbe::webEnabled($backend))) {
        throw new DomainException('La apertura pública requiere marcadores compatibles y un período prolongado válido si existe un marcador administrativo.');
    }
}

function adminPeriodRoot(array $arguments): string
{
    $root = dirname(__DIR__, 2);
    if (realpath($root) === $root && (($arguments === [] && $root === IwindsStaging::ROOT)
        || ($arguments === ['--disposable'] && $root === '/app' && is_file('/.dockerenv')
            && in_array(getenv('APP_ENV'), ['local', 'testing'], true) && getenv('AUTH_DISPOSABLE_MYSQL') === 'true'))) {
        return $root;
    }
    throw new DomainException('El comando está restringido al staging privado de iWinds.');
}

if ($periodLocal || $periodChange || $webLocal || $webOpen) {
    require_once __DIR__.'/../../app/Support/IwindsPublicProbe.php';
    require_once __DIR__.'/../../app/Support/IwindsStaging.php';
    try {
        $backend = adminPeriodRoot($arguments);
        if ($operation === 'status-public-web') {
            echo IwindsPublicProbe::webEnabled($backend) ? "Lectura pública habilitada.\n" : "Lectura pública cerrada.\n";
            exit;
        }
        if ($operation === 'close-public-web' || $operation === 'suspend-staging-api') {
            withAdminPeriodLock($backend, static function ($lock) use ($backend, $operation): void {
                invalidateAdminPeriodOperations($lock);
                $markers = IwindsPublicProbe::presentMarkers($backend);
                foreach ($markers as $mode => $path) {
                    if ($operation === 'suspend-staging-api' || $mode !== 'admin') {
                        if (! @unlink($path)) {
                            throw new DomainException('No se pudo retirar el marcador de staging.');
                        }
                    }
                }
                $path = $backend.'/'.IwindsPublicProbe::WEB_MARKER;
                if (IwindsPublicProbe::webMarkerPresent($backend) && ! @unlink($path)) {
                    throw new DomainException('No se pudo retirar el marcador público.');
                }
            });
            echo $operation === 'suspend-staging-api' ? "API de staging suspendida.\n"
                : "Lectura pública cerrada. Un período administrativo vigente vuelve a requerir HTTP Basic.\n";
            exit;
        }
        if ($operation === 'status-admin-extended') {
            $expires = IwindsPublicProbe::activeAdminPeriodExpiry($backend);
            echo $expires === null ? "Sin período prolongado vigente.\n" : 'Período sintético vigente hasta '.gmdate(DATE_ATOM, $expires)." (UTC).\n";
            exit;
        }
        if ($operation === 'close-admin-extended') {
            withAdminPeriodLock($backend, static function ($lock) use ($backend): void {
                // Cancel an activation that started before the close and is still checking MariaDB.
                invalidateAdminPeriodOperations($lock);
                $marker = IwindsPublicProbe::presentMarkers($backend)['admin'] ?? null;
                if ($marker !== null && ! @unlink($marker)) {
                    throw new DomainException('No se pudo retirar el marcador administrativo.');
                }
            });
            echo "Marcador administrativo retirado.\n";
            exit;
        }
        $generation = withAdminPeriodLock($backend, static function ($lock) use ($backend, $operation, $webOpen): string {
            if ($webOpen) {
                checkPublicWebTransition($backend);
            } else {
                checkAdminPeriodTransition($backend, $operation);
            }
            $current = stream_get_contents($lock, 33);

            return is_string($current) && preg_match('/\A[a-f0-9]{32}\z/', $current) === 1
                ? $current : invalidateAdminPeriodOperations($lock);
        });
    } catch (Throwable $error) {
        fwrite(STDERR, ($error instanceof DomainException ? $error->getMessage() : 'No se pudo comprobar el estado del período de staging.').PHP_EOL);
        exit(1);
    }
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$backend = $app->basePath();

try {
    if (! $periodChange && ! $webOpen) {
        requireClosedApi($backend);
    }
    $app->make(Kernel::class)->bootstrap();
    $mysql = config('database.connections.mysql');
    $disposable = $arguments === ['--disposable'] && $app->environment(['local', 'testing'])
        && config('auth_migration.disposable_mysql') === true && $mysql['host'] === 'mysql-auth'
        && $mysql['database'] === 'ensenada_auth_test' && $mysql['username'] === 'auth_runtime';
    if ((! $disposable && (! $app->environment('staging') || $arguments !== []))
        || config('database.default') !== 'mysql' || $mysql['collation'] !== 'utf8mb4_unicode_520_ci') {
        throw new DomainException('Entorno fuera del perfil privado de staging MariaDB.');
    }

    $server = DB::selectOne('SELECT VERSION() AS version, DATABASE() AS db, CURRENT_USER() AS account,
        @@collation_database AS collation, @@SESSION.foreign_key_checks AS fk,
        @@SESSION.check_constraint_checks AS checks, @@SESSION.unique_checks AS uniques');
    $tables = DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')
        ->whereIn('TABLE_NAME', ['auth_mutexes', 'portal_site_content', 'usuarios', 'usuario_roles'])
        ->where('ENGINE', 'InnoDB')->count();
    if (! preg_match('/^(?:5[.]5[.]5-)?10[.]11[.][0-9]+-MariaDB/i', $server->version)
        || $server->db !== $mysql['database'] || ! str_starts_with($server->account, $mysql['username'].'@')
        || $server->collation !== $mysql['collation'] || $tables !== 4
        || (int) $server->fk !== 1 || (int) $server->checks !== 1 || (int) $server->uniques !== 1
        || ! DB::table('auth_mutexes')->where('name', 'schema-ready')->exists()) {
        throw new DomainException('Base, identidad o esquema fuera del perfil de aprovisionamiento.');
    }

    if ($operation === 'rotate-admin') {
        if (! stream_isatty(STDIN) || ! stream_isatty(STDERR)) {
            throw new DomainException('La rotación requiere una terminal interactiva privada.');
        }
        $email = mb_strtolower(trim(readProvisionLine('Correo del SUPER_ADMIN existente: ')));
        if (readProvisionLine('Escribí ROTAR para confirmar: ') !== 'ROTAR') {
            throw new DomainException('Rotación cancelada.');
        }
        $password = promptAdminPassword();
        $bcrypt = $app->make(JavaBcrypt::class);
        DB::transaction(static function () use ($backend, $email, $password, $bcrypt): void {
            DB::table('auth_mutexes')->where('name', 'schema-ready')->lockForUpdate()->firstOrFail();
            requireClosedApi($backend);
            $user = Usuario::where('email', $email)->lockForUpdate()->first();
            if ($user === null || ! $user->activo || ! in_array(Role::SuperAdmin->value, $user->currentRoles(true), true)) {
                throw new DomainException('El correo no corresponde a un SUPER_ADMIN activo.');
            }
            if ($bcrypt->verify($password, $user->password)) {
                throw new DomainException('Elegí una contraseña distinta de la actual.');
            }
            $previousVersion = $user->token_version;
            $user->password = $bcrypt->hash($password);
            $user->save();
            $user->refresh();
            if ($user->token_version <= $previousVersion || DB::table('auth_sessions')->where('usuario_id', $user->id)->exists()) {
                throw new DomainException('La revocación no se confirmó. La rotación se revirtió.');
            }
            $version = (string) $user->token_version;
            writeStagingFile($backend, IwindsPublicProbe::ROTATION, $user->id.' '.$version.' '.rotationProof($user, $version)."\n");
        });
        echo 'Rotación y revocación confirmadas. La API permanece cerrada.'.PHP_EOL;
        exit;
    }

    if ($periodChange || $webOpen || in_array($operation, ['open-admin', 'open-full'], true)) {
        if (! matchesGrantProfile($disposable, false) && ($operation === 'open-full' || ! matchesGrantProfile($disposable, true))) {
            throw new DomainException('El perfil de permisos requerido no aprobó schema-check.php.');
        }
        $expires = DB::transaction(static function () use ($backend, $operation, $periodChange, $webOpen, $generation, $days): int {
            DB::table('auth_mutexes')->where('name', 'schema-ready')->lockForUpdate()->firstOrFail();
            if (! $periodChange && ! $webOpen) {
                requireClosedApi($backend);
            }
            $receipt = IwindsPublicProbe::rotation($backend);
            $user = $receipt === null ? null : Usuario::query()->lockForUpdate()->find($receipt['id']);
            if ($user === null || ! $user->activo || ! in_array(Role::SuperAdmin->value, $user->currentRoles(true), true)
                || $user->token_version < (int) $receipt['version'] || ! hash_equals($receipt['proof'], rotationProof($user, $receipt['version']))) {
                throw new DomainException('Confirmá primero la rotación privada del SUPER_ADMIN con rotate-admin.');
            }
            $site = DB::table('portal_site_content')->where('id', 1)->value('contenido_json');
            if (! is_string($site) || strlen($site) > SiteContentDocument::MAX_BYTES || ! SiteContentDocument::valid($site)) {
                throw new DomainException('Falta el contenido institucional válido para staging.');
            }

            return withAdminPeriodLock($backend, static function ($lock) use ($backend, $operation, $periodChange, $webOpen, $generation, $days, $receipt): int {
                $now = time();
                if ($periodChange || $webOpen) {
                    if ($generation !== stream_get_contents($lock, 33)) {
                        throw new DomainException('El estado cambió durante la comprobación. Revisá el estado antes de volver a operar.');
                    }
                    if ($webOpen) {
                        checkPublicWebTransition($backend);
                        if (! IwindsPublicProbe::webEnabled($backend)) {
                            invalidateAdminPeriodOperations($lock);
                            writeStagingFile($backend, IwindsPublicProbe::WEB_MARKER, IwindsPublicProbe::webMarker($receipt['proof']));
                        }

                        return 0;
                    }
                    $previous = checkAdminPeriodTransition($backend, $operation);
                    $expires = $now + $days * 86400;
                    if ($previous !== null && $expires <= $previous) {
                        throw new DomainException('La renovación debe extender el vencimiento vigente.');
                    }
                    invalidateAdminPeriodOperations($lock);
                    writeStagingFile($backend, IwindsPublicProbe::ADMIN_MARKER, IwindsPublicProbe::extendedAdminMarker($now, $expires, $receipt['proof']));

                    return $expires;
                }

                requireClosedApi($backend);
                $mode = $operation === 'open-admin' ? 'admin' : 'full';
                $marker = $mode === 'admin' ? IwindsPublicProbe::ADMIN_MARKER : IwindsPublicProbe::FULL_MARKER;
                $expires = $now + 1800;
                writeStagingFile($backend, $marker, IwindsPublicProbe::privilegedMarker($mode, $expires, $receipt['proof']));

                return $expires;
            });
        });
        echo match (true) {
            $webOpen => "Lectura pública habilitada. El período administrativo conserva su vencimiento.\n",
            $periodChange => 'Período administrativo sintético vigente hasta '.gmdate(DATE_ATOM, $expires)." (UTC).\n",
            default => "Ventana de staging habilitada por 30 minutos.\n",
        };
        exit;
    }

    $adminRoles = ['SUPER_ADMIN', 'ROLE_SUPER_ADMIN', 'ADMIN', 'ROLE_ADMIN'];
    $existingAdmin = static fn (): bool => Usuario::query()->whereIn(DB::raw('UPPER(TRIM(rol))'), $adminRoles)
        ->orWhereHas('assignedRoles', fn (Builder $roles) => $roles->whereIn(DB::raw('UPPER(TRIM(rol))'), $adminRoles))->exists();
    $markers = DB::table('auth_mutexes');
    if ($operation === 'status') {
        $site = DB::table('portal_site_content')->where('id', 1)->value('contenido_json');
        $valid = is_string($site) && strlen($site) <= SiteContentDocument::MAX_BYTES && SiteContentDocument::valid($site);
        $hasSiteRows = $site !== null || DB::table('portal_site_content')->exists();
        $siteStatus = $valid ? 'VÁLIDO' : ($hasSiteRows ? 'INVÁLIDO O FALTA ID=1' : 'PENDIENTE');
        echo 'Contenido institucional id=1: '.$siteStatus.PHP_EOL;
        echo 'Identidad administrativa existente o histórica: '.($existingAdmin() ? 'SÍ' : 'NO').PHP_EOL;
        foreach (PROVISION_MARKERS as $name => $key) {
            echo 'Aprovisionamiento '.$name.': '.((clone $markers)->where('name', $key)->exists() ? 'REGISTRADO' : 'SIN MARCA').PHP_EOL;
        }
        exit($hasSiteRows && ! $valid ? 1 : 0);
    }

    $assertAvailable = static function () use ($operation, $markers, $existingAdmin): void {
        if ((clone $markers)->where('name', PROVISION_MARKERS[$operation])->exists()) {
            throw new DomainException('Este aprovisionamiento ya se ejecutó.');
        }
        if ($operation === 'site-content' && DB::table('portal_site_content')->exists()) {
            throw new DomainException('Ya existe contenido institucional. Se conserva íntegro.');
        }
        if ($operation === 'first-admin' && $existingAdmin()) {
            throw new DomainException('Ya existe una identidad administrativa actual o histórica. Se conserva íntegra.');
        }
    };
    $assertAvailable();

    if ($operation === 'site-content') {
        $json = @file_get_contents(__DIR__.'/site-content.staging.json', length: SiteContentDocument::MAX_BYTES + 1);
        if ($json === false || strlen($json) > SiteContentDocument::MAX_BYTES || ! SiteContentDocument::valid($json)) {
            throw new DomainException('El JSON inicial no supera la validación institucional.');
        }
        $write = static function () use ($json): void {
            DB::table('portal_site_content')->insert(['id' => 1, 'contenido_json' => $json, 'updated_at' => now('UTC')]);
        };
    } else {
        if (! stream_isatty(STDIN) || ! stream_isatty(STDERR)) {
            throw new DomainException('El alta requiere una terminal interactiva con lectura de contraseña oculta.');
        }
        $name = trim(readProvisionLine('Nombre de la persona responsable: '));
        $email = mb_strtolower(trim(readProvisionLine('Correo de la persona responsable: ')));
        if (Validator::make(['nombre' => $name, 'email' => $email], [
            'nombre' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:255'],
        ])->fails() || ! mb_check_encoding($name, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new DomainException('Nombre o correo inválido.');
        }
        if (Usuario::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw new DomainException('Ya existe una cuenta con ese correo. Se conserva íntegra.');
        }
        $hash = $app->make(JavaBcrypt::class)->hash(promptAdminPassword());
        $write = static function () use ($name, $email, $hash): void {
            if (Usuario::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw new DomainException('Ya existe una cuenta con ese correo. Se conserva íntegra.');
            }
            $user = new Usuario(['nombre' => $name, 'email' => $email]);
            $user->password = $hash;
            $user->rol = Role::SuperAdmin->value;
            $user->activo = true;
            $user->token_version = 0;
            $user->save();
            $user->assignedRoles()->create(['rol' => Role::SuperAdmin->value]);
        };
    }

    DB::transaction(static function () use ($operation, $assertAvailable, $backend, $write): void {
        if (DB::table('auth_mutexes')->where('name', 'schema-ready')->lockForUpdate()->first() === null) {
            throw new DomainException('Falta la marca de esquema completo.');
        }
        requireClosedApi($backend);
        $assertAvailable();
        $write();
        DB::table('auth_mutexes')->insert(['name' => PROVISION_MARKERS[$operation]]);
        requireClosedApi($backend);
    });
    echo 'Aprovisionamiento confirmado. La API permanece deshabilitada.'.PHP_EOL;
} catch (DomainException $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, 'No se pudo confirmar el aprovisionamiento. Consultá status antes de reintentar.'.PHP_EOL);
    exit(1);
}
