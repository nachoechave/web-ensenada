<?php

namespace App\Support;

use Dotenv\Dotenv;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/** CLI coordinator. Never creates/imports a database or replaces business rows. */
final class SharedDatabaseTransfer
{
    public static function initialize(): void
    {
        $root = SharedDatabaseLease::ROOT;
        if (! is_dir($root) && ! mkdir($root, 0700)) {
            throw new RuntimeException('No se pudo preparar el control privado.');
        }
        if (realpath($root) !== $root || (fileperms($root) & 0077) !== 0) {
            throw new RuntimeException('Control privado inseguro.');
        }
        if (! is_file($root.'/control.key')) {
            $file = fopen($root.'/control.key', 'xb');
            if (! $file) {
                throw new RuntimeException('Clave de control existente.');
            }
            fwrite($file, bin2hex(random_bytes(32)));
            fclose($file);
            chmod($root.'/control.key', 0600);
        }
        SharedDatabaseLease::lock(true);
        if (! SharedDatabaseLease::managed()) {
            SharedDatabaseLease::save(['version' => 2, 'owner' => 'staging', 'phase' => 'legacy', 'epoch' => bin2hex(random_bytes(16))]);
        }
    }

    public static function flag(array $options, string $name): void
    {
        if (($options[$name] ?? false) !== true) {
            throw new RuntimeException('Falta autorización explícita: --'.$name);
        }
    }

    public static function state(array $changes = []): array
    {
        $state = [...SharedDatabaseLease::state(), ...$changes];
        if ($changes !== []) {
            SharedDatabaseLease::save($state);
        }

        return $state;
    }

    public static function schemaPdo(string $config): PDO
    {
        $pdo = SharedDatabaseAudit::connect(SharedDatabaseAudit::schemaConfig($config));
        if (ProductionDatabaseProfile::selected() === 'cpanel-dml') {
            ProductionPermissions::verifySchemaPdo($pdo);
        }

        return $pdo;
    }

    public static function closeProduction(): void
    {
        $path = SharedDatabaseLease::PROD.'/.iwinds-production-enabled';
        if (is_link($path)) {
            throw new RuntimeException('Marcador no regular.');
        }
        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('No se pudo cerrar producción.');
        }
    }

    public static function pauseStage(array $options): void
    {
        self::flag($options, 'authorize-staging-pause');
        self::flag($options, 'confirm-exclusive-account-use');
        self::flag($options, 'confirm-no-background-writers');
        $state = self::state();
        $fromRecovery = $state['owner'] === 'recovery' && in_array($state['phase'], ['active', 'paused'], true);
        if (! in_array($state['phase'], ['legacy', 'parking'], true) && ! $fromRecovery) {
            throw new RuntimeException('Fase inválida para aparcar staging.');
        }
        self::closeProduction();
        if ($state['phase'] === 'legacy' || $fromRecovery) {
            $stageWeb = '/home/ensenada/public_html/prueba.ensenada.gov.ar';
            foreach (['uploads', 'storage', 'backend-laravel'] as $name) {
                if (file_exists($stageWeb.'/'.$name) || is_link($stageWeb.'/'.$name)) {
                    throw new RuntimeException('Almacenamiento/backend directo dentro del staging web: revisar sin borrar ni transferir.');
                }
            }
            $env = SharedDatabaseAudit::env(SharedDatabaseLease::STAGE);
            ProductionPermissions::verifyPdo(SharedDatabaseAudit::connect($env));
            self::independentKeys($env, SharedDatabaseAudit::env(SharedDatabaseLease::PROD));
            foreach (['pending-production.json', 'pending-recovery.json'] as $name) {
                self::archiveFile(SharedDatabaseLease::ROOT.'/'.$name, 'prior-'.$name, true);
            }
            $state = self::state(['owner' => 'none', 'phase' => 'parking', 'target' => 'production', 'paused_at' => time(),
                'epoch' => bin2hex(random_bytes(16)), 'approved' => false, 'restored' => false, 'revoked' => false, 'copied' => false, 'backup' => null, 'expires_at' => 0]);
        }
        $bridge = '/home/ensenada/public_html/prueba.ensenada.gov.ar/ensenada-api.php';
        if (! is_file(SharedDatabaseLease::ROOT.'/staging-bridge.before.php')) {
            if (is_link($bridge) || ! is_file($bridge)) {
                throw new RuntimeException('Puente de staging existente requerido.');
            }
            SharedDatabaseLease::atomic(SharedDatabaseLease::ROOT.'/staging-bridge.before.php', file_get_contents($bridge));
        }
        SharedDatabaseLease::atomic($bridge, file_get_contents(SharedDatabaseLease::PROD.'/deploy/production/recovery-bridge.php'));
        chmod($bridge, 0644);
        if (is_dir(SharedDatabaseLease::STAGE) && ! is_dir(SharedDatabaseLease::PARKED)) {
            if (realpath(SharedDatabaseLease::STAGE) !== SharedDatabaseLease::STAGE || is_link(SharedDatabaseLease::PARKED)
                || ! rename(SharedDatabaseLease::STAGE, SharedDatabaseLease::PARKED)) {
                throw new RuntimeException('No se pudo aparcar atómicamente el backend.');
            }
        }
        if (! is_dir(SharedDatabaseLease::PARKED)) {
            throw new RuntimeException('Falta el backend conservado.');
        }
        if (! is_dir(SharedDatabaseLease::STAGE)) {
            mkdir(SharedDatabaseLease::STAGE, 0700);
        }
        SharedDatabaseLease::atomic(SharedDatabaseLease::STAGE.'/.database-parked', $state['epoch']."\n");
        self::archiveStageMarkers();
        self::state(['phase' => 'frozen']);
    }

    private static function archiveStageMarkers(): void
    {
        $archive = SharedDatabaseLease::ROOT.'/markers-'.self::state()['epoch'];
        if (! is_dir($archive)) {
            mkdir($archive, 0700);
        }
        foreach (['.iwinds-api-enabled', '.iwinds-api-public-probe', '.iwinds-api-admin-probe', '.iwinds-staging-public-web'] as $name) {
            $path = SharedDatabaseLease::PARKED.'/'.$name;
            if (is_link($path)) {
                throw new RuntimeException('Marcador de staging enlazado.');
            }
            if (is_file($path) && ! file_exists($archive.'/'.$name) && ! rename($path, $archive.'/'.$name)) {
                throw new RuntimeException('No se pudo conservar el marcador.');
            }
        }
    }

    public static function independentKeys(array $stage, array $prod): void
    {
        $decode = static fn ($key) => str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
        $s = $decode($stage['APP_KEY'] ?? '');
        $p = $decode($prod['APP_KEY'] ?? '');
        if (! is_string($s) || ! is_string($p) || strlen($s) !== 32 || strlen($p) !== 32 || hash_equals($s, $p)
            || strlen($prod['AUTH_JWT_SECRET'] ?? '') < 64 || ($prod['AUTH_JWT_SECRET'] ?? '') === ($stage['AUTH_JWT_SECRET'] ?? '')
            || in_array($prod['AUTH_JWT_SECRET'], [$prod['APP_KEY'], $p, substr($prod['APP_KEY'], 7)], true)) {
            throw new RuntimeException('Se requieren las claves originales, válidas e independientes. No se regeneran.');
        }
    }

    public static function patchEnv(string $root, array $fields): array
    {
        $before = SharedDatabaseAudit::env($root);
        $text = file_get_contents($root.'/.env');
        foreach ($fields as $name => $value) {
            if (! in_array($name, ['DB_DATABASE', 'DB_USERNAME', 'DB_HOST', 'DB_PORT', 'DB_PASSWORD'], true) || ! preg_match('/\A[A-Za-z0-9_.:-]+\z/D', (string) $value)) {
                throw new RuntimeException('Campo de conexión inválido.');
            }
            $count = 0;
            $text = preg_replace('/^'.preg_quote($name, '/').'\s*=.*$/m', $name.'='.$value, $text, -1, $count);
            if ($count > 1) {
                throw new RuntimeException('Variables de conexión duplicadas.');
            }if ($count === 0) {
                $text = rtrim($text)."\n$name=$value\n";
            }
        }
        $after = Dotenv::parse($text);
        if (($before['APP_KEY'] ?? null) !== ($after['APP_KEY'] ?? null) || ($before['AUTH_JWT_SECRET'] ?? null) !== ($after['AUTH_JWT_SECRET'] ?? null)) {
            throw new RuntimeException('Se impidió alterar claves.');
        }
        foreach ($fields as $name => $value) {
            if (($after[$name] ?? null) !== (string) $value) {
                throw new RuntimeException('La configuración no pudo conservarse.');
            }
        }
        self::archiveFile($root.'/.env', 'env-'.basename($root));
        SharedDatabaseLease::atomic($root.'/.env', $text);
        self::archiveFile($root.'/bootstrap/cache/config.php', 'cache-'.basename($root), true);

        return $after;
    }

    public static function archiveFile(string $path, string $label, bool $move = false): void
    {
        if (is_link($path)) {
            throw new RuntimeException('No se archivan enlaces.');
        }if (! is_file($path)) {
            return;
        }
        $dir = SharedDatabaseLease::ROOT.'/history';
        if (! is_dir($dir)) {
            mkdir($dir, 0700);
        }
        $dest = $dir.'/'.$label.'-'.hash_file('sha256', $path);
        if (! is_file($dest)) {
            SharedDatabaseLease::atomic($dest, file_get_contents($path));
        }
        if ($move) {
            if (! unlink($path)) {
                throw new RuntimeException('No se pudo retirar la caché conservada.');
            }
        }
    }

    private static function rotateTo(string $target, string $source): PDO
    {
        $state = self::state();
        $file = SharedDatabaseLease::ROOT.'/pending-'.$target.'.json';
        if (is_file($file)) {
            $pending = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            if ($pending['epoch'] !== $state['epoch']) {
                throw new RuntimeException('Rotación pendiente de otro corte.');
            }
        } else {
            $pending = ['epoch' => $state['epoch'], 'password' => bin2hex(random_bytes(32))];
            SharedDatabaseLease::atomic($file, json_encode($pending, JSON_THROW_ON_ERROR));
        }
        $old = SharedDatabaseAudit::env($source);
        $next = [...$old, 'DB_PASSWORD' => $pending['password']];
        try {
            $pdo = SharedDatabaseAudit::connect($next);
        } catch (\PDOException) {
            $pdo = SharedDatabaseAudit::connect($old);
            ProductionPermissions::verifyPdo($pdo);
            // MariaDB 10.11 does not accept a native prepared placeholder in SET PASSWORD.
            // The value is generated hex and quoted by PDO; errors never include SQL in CLI output.
            $pdo->exec('SET PASSWORD = PASSWORD('.$pdo->quote($pending['password']).')');
            unset($pdo);
            $pdo = SharedDatabaseAudit::connect($next);
        }
        ProductionPermissions::verifyPdo($pdo);
        self::killPreviousConnections($pdo);
        try {
            SharedDatabaseAudit::connect($old);
            throw new RuntimeException('La credencial anterior todavía conecta.');
        } catch (\PDOException) {
        }
        $root = $target === 'production' ? SharedDatabaseLease::PROD : (is_dir(SharedDatabaseLease::PARKED) ? SharedDatabaseLease::PARKED : SharedDatabaseLease::STAGE);
        $env = self::patchEnv($root, ['DB_DATABASE' => 'ensenada_web', 'DB_USERNAME' => 'ensenada_web_app', 'DB_HOST' => $old['DB_HOST'], 'DB_PORT' => '3306', 'DB_PASSWORD' => $pending['password']]);
        self::state(['fingerprint' => SharedDatabaseLease::fingerprint($env, $target === 'recovery')]);

        return $pdo;
    }

    public static function killPreviousConnections(PDO $pdo): void
    {
        $id = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        foreach ($pdo->query('SHOW PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['User'] !== 'ensenada_web_app') {
                throw new RuntimeException('Cuenta con visibilidad global inesperada.');
            }
            $other = (int) $row['Id'];
            if ($other !== $id) {
                $pdo->exec('KILL CONNECTION '.$other);
            }
        }
        $rows = $pdo->query('SHOW PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1 || (int) $rows[0]['Id'] !== $id) {
            throw new RuntimeException('Persisten conexiones anteriores.');
        }
    }

    public static function rotateProduction(array $options): void
    {
        self::flag($options, 'authorize-mysql-password-rotation');
        $state = self::state();
        if (! in_array($state['phase'], ['frozen', 'prepared'], true) || ($state['target'] ?? '') !== 'production') {
            throw new RuntimeException('Aparcar staging primero.');
        }
        if ($state['phase'] === 'prepared') {
            return;
        }
        self::rotateTo('production', SharedDatabaseLease::PARKED);
        self::state(['owner' => 'production', 'phase' => 'prepared']);
        self::archiveFile(SharedDatabaseLease::PROD.'/.production-isolation', 'isolation-before', true);
        $env = SharedDatabaseAudit::env(SharedDatabaseLease::PROD);
        SharedDatabaseLease::atomic(SharedDatabaseLease::PROD.'/.production-isolation', 'isolation-v1 '.SharedDatabaseLease::fingerprint($env)."\n");
    }

    public static function revoke(PDO $pdo): int
    {
        $pdo->beginTransaction();
        try {
            $users = $pdo->query('SELECT id FROM usuarios ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);
            $pdo->exec('UPDATE usuarios SET token_version=token_version+1');
            $count = $pdo->exec('DELETE FROM auth_sessions');
            $pdo->commit();

            return $count;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function audit(string $config, string $output, bool $live = false): array
    {
        $state = self::state();
        if (! in_array($state['phase'], $live ? ['legacy'] : ['prepared', 'active', 'paused'], true)) {
            throw new RuntimeException('Fase de auditoría inválida.');
        }
        if ($state['phase'] === 'active') {
            throw new RuntimeException('Pausar antes de la auditoría definitiva.');
        }
        $pdo = self::schemaPdo($config);
        $storage = ($live ? SharedDatabaseLease::STAGE : ($state['owner'] === 'production' && $state['phase'] === 'paused' ? SharedDatabaseLease::PROD : SharedDatabaseLease::PARKED)).'/storage/app/private';
        $snapshot = SharedDatabaseAudit::snapshot($pdo, $storage);
        $report = ['epoch' => $state['epoch'], 'provisional' => $live, 'created_at' => time(), ...$snapshot];
        self::newJson($output, $report);

        return $report;
    }

    public static function newJson(string $path, array $data): void
    {
        if (file_exists($path) || is_link($path) || realpath(dirname($path)) !== dirname($path)
            || ! str_starts_with(realpath(dirname($path)).'/', '/home/ensenada/respaldos_privados/')) {
            throw new RuntimeException('Salida nueva fuera de public_html requerida en respaldos_privados.');
        }
        $file = fopen($path, 'xb');
        if (! $file) {
            throw new RuntimeException('Salida existente.');
        }
        fwrite($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        fclose($file);
        chmod($path, 0600);
    }

    public static function readJson(string $path): array
    {
        if (is_link($path) || ! is_file($path) || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('Informe privado requerido.');
        }

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function sourceStorage(): string
    {
        $s = self::state();

        return (($s['phase'] === 'paused' && $s['owner'] === 'production') ? SharedDatabaseLease::PROD : SharedDatabaseLease::PARKED).'/storage/app/private';
    }

    public static function copyStorage(string $source, string $target): array
    {
        $files = SharedDatabaseAudit::files($source);
        foreach (SharedDatabaseAudit::MODULES as $module) {
            $dir = $target.'/'.$module;
            if (! is_dir($dir)) {
                mkdir($dir, 0700, true);
            }if (realpath($dir) !== $dir || (fileperms($dir) & 0077) !== 0) {
                throw new RuntimeException('Destino de almacenamiento inseguro.');
            }
        }
        foreach ($files as $name => $meta) {
            $dest = $target.'/'.$name;
            $src = $source.'/'.$name;
            if (is_link($dest)) {
                throw new RuntimeException('Enlace en destino.');
            }
            if (is_file($dest)) {
                if (lstat($dest)['nlink'] !== 1 || hash_file('sha256', $dest) !== $meta['sha256']) {
                    throw new RuntimeException('Colisión de archivo; no se sobrescribe.');
                }

                continue;
            }
            $tmp = dirname($dest).'/.transfer-'.bin2hex(random_bytes(12));
            $in = fopen($src, 'rb');
            $out = fopen($tmp, 'xb');
            if (! $in || ! $out) {
                throw new RuntimeException('No se pudo abrir la copia.');
            }
            try {
                if (stream_copy_to_stream($in, $out) !== $meta['size'] || ! fflush($out) || ! fsync($out) || ! chmod($tmp, 0600)
                    || hash_file('sha256', $tmp) !== $meta['sha256'] || ! link($tmp, $dest)) {
                    throw new RuntimeException('Copia incompleta o disco sin capacidad.');
                }
            } finally {
                fclose($in);
                fclose($out);
                if (is_file($tmp)) {
                    unlink($tmp);
                }
            }
        }
        if (SharedDatabaseAudit::files($source) !== $files) {
            throw new RuntimeException('Origen cambió durante la copia.');
        }

        return $files;
    }

    public static function copyToProduction(array $options): void
    {
        self::flag($options, 'confirm-account-quota');
        $state = self::state();
        if ($state['phase'] !== 'prepared') {
            throw new RuntimeException('Fase de copia inválida.');
        }
        if (time() - $state['paused_at'] < 125) {
            throw new RuntimeException('Esperar 125 segundos desde el aparcado para detectar restos de peticiones anteriores.');
        }
        $source = SharedDatabaseLease::PARKED.'/storage/app/private';
        $target = SharedDatabaseLease::PROD.'/storage/app/private';
        $files = SharedDatabaseAudit::files($source);
        $required = array_sum(array_column($files, 'size'));
        if (disk_free_space($target) < $required + 10485760) {
            throw new RuntimeException('Espacio insuficiente; también comprobar cuota efectiva.');
        }
        self::copyStorage($source, $target);
        self::state(['copied' => true]);
    }

    public static function backup(string $config, string $directory): void
    {
        $s = self::state();
        if (! in_array($s['phase'], ['prepared', 'paused'], true)) {
            throw new RuntimeException('Ambos escritores deben estar detenidos.');
        }
        $cfg = SharedDatabaseAudit::schemaConfig($config);
        $pdo = self::schemaPdo($config);
        $snapshot = SharedDatabaseAudit::snapshot($pdo, self::sourceStorage());
        if (file_exists($directory) || is_link($directory) || ! str_starts_with($directory, '/home/ensenada/respaldos_privados/')
            || realpath(dirname($directory)) !== dirname($directory) || ! mkdir($directory, 0700)) {
            throw new RuntimeException('Directorio nuevo privado requerido.');
        }
        $raw = preg_replace('/^\s*database\s*=.*\R?/m', '', file_get_contents($config));
        SharedDatabaseLease::atomic($directory.'/dump-client.cnf', $raw);
        $dest = fopen($directory.'/database.sql.partial', 'xb');
        $dump = is_executable('/usr/local/bin/mariadb-dump') ? '/usr/local/bin/mariadb-dump' : (is_executable('/usr/bin/mariadb-dump') ? '/usr/bin/mariadb-dump' : '/usr/bin/mysqldump');
        $process = new Process([$dump, '--defaults-extra-file='.$directory.'/dump-client.cnf', '--single-transaction', '--skip-add-drop-table',
            '--skip-add-drop-trigger', '--skip-add-drop-database', '--skip-add-locks', '--skip-disable-keys', '--triggers', '--hex-blob', 'ensenada_web'], timeout: 120);
        $process->run(static function ($type, $bytes) use ($dest): void {
            if ($type === Process::OUT) {
                if (fwrite($dest, $bytes) !== strlen($bytes)) {
                    throw new RuntimeException('Disco sin capacidad.');
                }
            }
        });
        fflush($dest);
        fsync($dest);
        fclose($dest);
        if (! $process->isSuccessful() || filesize($directory.'/database.sql.partial') === 0) {
            throw new RuntimeException('Exportación fallida; se conserva parcial sin aprobar.');
        }
        rename($directory.'/database.sql.partial', $directory.'/database.sql');
        $archives = ['production-private.tar' => ['-C', '/home/ensenada', 'backend-laravel-produccion'],
            'staging-private.tar' => ['-C', SharedDatabaseLease::ROOT, 'staging-backend'],
            'control-private.tar' => ['--exclude=web-db-transfer/staging-backend', '-C', '/home/ensenada', 'web-db-transfer'],
            'web-rollback.tar' => ['-C', '/home/ensenada/public_html', '.htaccess', 'web-produccion']];
        if (! is_dir(SharedDatabaseLease::PARKED)) {
            unset($archives['staging-private.tar']);
            $archives['recovery-private.tar'] = ['-C', '/home/ensenada', 'backend-laravel'];
        }
        foreach ($archives as $name => $args) {
            (new Process(['/usr/bin/tar', '-cpf', $directory.'/'.$name, ...$args], timeout: 600))->mustRun();
            (new Process(['/usr/bin/tar', '-tf', $directory.'/'.$name], timeout: 120))->mustRun();
        }
        if (SharedDatabaseAudit::snapshot($pdo, self::sourceStorage()) !== $snapshot) {
            throw new RuntimeException('Datos cambiaron durante el respaldo.');
        }
        $hashes = [];
        foreach (['database.sql', ...array_keys($archives)] as $name) {
            $hashes[$name] = hash_file('sha256', $directory.'/'.$name);
        }
        $manifest = ['epoch' => $s['epoch'], 'schema_digest' => $snapshot['schema_digest'], 'data_digest' => $snapshot['data_digest'],
            'full_data_digest' => SharedDatabaseAudit::fullDigest($pdo, SharedDatabaseLease::key()),
            'files_digest' => $snapshot['files_digest'], 'files' => $snapshot['files'], 'archives' => $hashes, 'backup_id' => bin2hex(random_bytes(16))];
        self::newJson($directory.'/backup.json', $manifest);
        self::state(['backup' => $directory.'/backup.json', 'restored' => false]);
    }

    public static function approve(string $config, string $reviewFile, array $options): void
    {
        self::flag($options, 'authorize-reviewed-data');
        $s = self::state();
        if ($s['phase'] !== 'prepared' || empty($s['copied'])) {
            throw new RuntimeException('Transferencia privada pendiente.');
        }
        $pdo = self::schemaPdo($config);
        $snapshot = SharedDatabaseAudit::snapshot($pdo, SharedDatabaseLease::PARKED.'/storage/app/private');
        $review = self::readJson($reviewFile);
        if (($review['epoch'] ?? '') !== $s['epoch'] || ($review['data_digest'] ?? '') !== $snapshot['data_digest'] || ($review['files_digest'] ?? '') !== $snapshot['files_digest']) {
            throw new RuntimeException('Revisión obsoleta.');
        }
        SharedDatabaseAudit::approve($snapshot, $review['review'] ?? []);
        self::state(['approved' => ['schema' => $snapshot['schema_digest'], 'data' => $snapshot['data_digest'], 'files' => $snapshot['files_digest'], 'review_sha256' => hash_file('sha256', $reviewFile)]]);
    }

    public static function attestRestore(string $proofFile, array $options): void
    {
        self::flag($options, 'authorize-restore-proof');
        $s = self::state();
        $backup = self::readJson($s['backup'] ?? '');
        $proof = self::readJson($proofFile);
        foreach (['backup_id', 'schema_digest', 'data_digest', 'full_data_digest', 'files_digest', 'archives'] as $key) {
            if (($proof[$key] ?? null) !== ($backup[$key] ?? null)) {
                throw new RuntimeException('Prueba de restauración no corresponde al respaldo actual.');
            }
        }
        if (($proof['restored_tables'] ?? 0) !== 12 || ($proof['trigger_tested'] ?? false) !== true || ($proof['files_verified'] ?? false) !== true || ($proof['isolated_target'] ?? false) !== true) {
            throw new RuntimeException('Prueba de restauración incompleta.');
        }
        foreach ($backup['archives'] as $name => $hash) {
            if (hash_file('sha256', dirname($s['backup']).'/'.$name) !== $hash) {
                throw new RuntimeException('Respaldo modificado.');
            }
        }
        self::state(['restored' => hash_file('sha256', $proofFile)]);
    }

    public static function activate(string $config, array $options): void
    {
        self::flag($options, 'authorize-session-revocation');
        self::flag($options, 'authorize-production');
        ProductionDatabaseProfile::requireActivationFlag($options);
        $s = self::state();
        if ($s['phase'] === 'active' && $s['owner'] === 'production') {
            ProductionDatabaseProfile::verifyActivation();
            SharedDatabaseLease::production(false);
            $env = SharedDatabaseAudit::env(SharedDatabaseLease::PROD);
            ProductionPermissions::verifyPdo(SharedDatabaseAudit::connect($env));
            $schema = self::schemaPdo($config);
            AuditCheckpoint::requireAnchor($schema);
            if (SharedDatabaseAudit::schema($schema) !== json_decode(file_get_contents(SharedDatabaseLease::PROD.'/deploy/production/schema-reference.json'), true)) {
                throw new RuntimeException('Esquema modificado.');
            }
            SharedDatabaseLease::atomic(SharedDatabaseLease::PROD.'/.iwinds-production-enabled', 'production-v1 '.SharedDatabaseLease::fingerprint($env)."\n");

            return;
        }
        if ($s['phase'] !== 'prepared' || empty($s['approved']) || empty($s['restored']) || empty($s['copied'])) {
            throw new RuntimeException('Aprobación, copia o restauración verificadas pendientes.');
        }
        $schema = self::schemaPdo($config);
        AuditCheckpoint::requireAnchor($schema);
        $snapshot = SharedDatabaseAudit::snapshot($schema, SharedDatabaseLease::PARKED.'/storage/app/private');
        if ((int) $schema->query('SELECT COUNT(*) FROM portal_site_content WHERE id=1')->fetchColumn() !== 1
            || (int) $schema->query("SELECT COUNT(*) FROM usuarios u JOIN usuario_roles r ON r.usuario_id=u.id WHERE u.activo=1 AND r.rol='SUPER_ADMIN'")->fetchColumn() < 1) {
            throw new RuntimeException('Contenido singleton y SUPER_ADMIN activo existentes requeridos. No se aprovisionan ni sobrescriben.');
        }
        if ($s['approved']['schema'] !== $snapshot['schema_digest'] || $s['approved']['data'] !== $snapshot['data_digest'] || $s['approved']['files'] !== $snapshot['files_digest']) {
            throw new RuntimeException('Datos modificados desde la aprobación.');
        }
        $dest = SharedDatabaseAudit::files(SharedDatabaseLease::PROD.'/storage/app/private');
        foreach ($snapshot['files'] as $name => $meta) {
            if (($dest[$name] ?? null) !== $meta) {
                throw new RuntimeException('Falta una copia íntegra.');
            }
        }
        $env = SharedDatabaseAudit::env(SharedDatabaseLease::PROD);
        self::independentKeys(SharedDatabaseAudit::env(SharedDatabaseLease::PARKED), $env);
        if (($env['APP_ENV'] ?? '') !== 'production' || ($env['APP_URL'] ?? '') !== 'https://www.ensenada.gov.ar'
            || ! in_array(strtolower($env['APP_DEBUG'] ?? ''), ['false', '(false)'], true)
            || ! in_array(strtolower($env['AUTH_LOCAL_ONLY'] ?? 'false'), ['false', '(false)'], true)
            || ! in_array(strtolower($env['AUTH_DISPOSABLE_MYSQL'] ?? 'false'), ['false', '(false)'], true)
            || ! empty($env['DB_URL'])) {
            throw new RuntimeException('Perfil de producción inválido.');
        }
        $pdo = SharedDatabaseAudit::connect($env);
        ProductionPermissions::verifyPdo($pdo);
        self::killPreviousConnections($pdo);
        if (! $s['revoked']) {
            self::revoke($pdo);
            self::state(['revoked' => true]);
        }
        SharedDatabaseLease::atomic(SharedDatabaseLease::PROD.'/.iwinds-production-enabled', 'production-v1 '.SharedDatabaseLease::fingerprint($env)."\n");
        self::state(['owner' => 'production', 'phase' => 'active', 'activated_at' => time(), 'profile_activation' => ProductionDatabaseProfile::activationRecord()]);
    }

    public static function pauseProduction(array $options): void
    {
        self::flag($options, 'authorize-production-pause');
        $s = self::state();
        if (! in_array($s['owner'], ['production', 'recovery'], true)) {
            throw new RuntimeException('No hay propietario recuperable.');
        }
        self::closeProduction();
        self::state(['phase' => 'paused', 'backup' => null]);
    }

    public static function recovery(array $options): void
    {
        self::flag($options, 'authorize-recovery-with-current-data');
        self::flag($options, 'authorize-mysql-password-rotation');
        self::flag($options, 'authorize-session-revocation');
        ProductionDatabaseProfile::requireActivationFlag($options);
        $s = self::state();
        $minutes = (int) ($options['minutes'] ?? 30);
        if ($minutes < 1 || $minutes > 60) {
            throw new RuntimeException('Recuperación entre 1 y 60 minutos.');
        }
        if (! in_array($s['phase'], ['paused', 'recovering'], true) || $s['owner'] !== 'production') {
            throw new RuntimeException('Pausar producción antes de recuperar.');
        }
        if ($s['phase'] === 'paused') {
            $pdo = SharedDatabaseAudit::connect(SharedDatabaseAudit::env(SharedDatabaseLease::PROD));
            ProductionPermissions::verifyPdo($pdo);
            AuditCheckpoint::requireAnchor($pdo);
            $backup = self::readJson($s['backup'] ?? '');
            if (($backup['epoch'] ?? '') !== $s['epoch'] || ($backup['full_data_digest'] ?? '') !== SharedDatabaseAudit::fullDigest($pdo, SharedDatabaseLease::key())
                || ($backup['files'] ?? []) !== SharedDatabaseAudit::files(SharedDatabaseLease::PROD.'/storage/app/private')) {
                throw new RuntimeException('Respaldo reciente de producción pendiente.');
            }
            self::copyStorage(SharedDatabaseLease::PROD.'/storage/app/private', SharedDatabaseLease::PARKED.'/storage/app/private');
            self::state(['phase' => 'recovering', 'epoch' => bin2hex(random_bytes(16)), 'target' => 'recovery']);
        }
        $pdo = self::rotateTo('recovery', SharedDatabaseLease::PROD);
        self::revoke($pdo);
        self::prepareRecoveryCode();
        $placeholder = SharedDatabaseLease::ROOT.'/placeholder-'.self::state()['epoch'];
        if (is_dir(SharedDatabaseLease::PARKED)) {
            if (is_dir(SharedDatabaseLease::STAGE)) {
                if (! is_file(SharedDatabaseLease::STAGE.'/.database-parked') || is_file(SharedDatabaseLease::STAGE.'/artisan')) {
                    throw new RuntimeException('Placeholder de staging modificado.');
                }
                if (! rename(SharedDatabaseLease::STAGE, $placeholder)) {
                    throw new RuntimeException('No se pudo conservar placeholder.');
                }
            }
            if (! rename(SharedDatabaseLease::PARKED, SharedDatabaseLease::STAGE)) {
                throw new RuntimeException('No se pudo restaurar backend conservado.');
            }
        }
        self::state(['owner' => 'recovery', 'phase' => 'active', 'expires_at' => time() + $minutes * 60, 'profile_activation' => ProductionDatabaseProfile::activationRecord()]);
    }

    private static function prepareRecoveryCode(): void
    {
        $source = SharedDatabaseLease::PROD;
        $target = is_dir(SharedDatabaseLease::PARKED) ? SharedDatabaseLease::PARKED : SharedDatabaseLease::STAGE;
        foreach (['SharedDatabaseLease.php', 'ProductionPermissions.php', 'ProductionDatabaseProfile.php', 'IwindsProduction.php', 'IwindsPublishedImages.php'] as $name) {
            self::archiveFile($target.'/app/Support/'.$name, 'recovery-code-'.$name);
            SharedDatabaseLease::atomic($target.'/app/Support/'.$name, file_get_contents($source.'/app/Support/'.$name));
        }
        foreach ([$target.'/deploy', $target.'/deploy/production'] as $directory) {
            if (is_link($directory) || (file_exists($directory) && realpath($directory) !== $directory)) {
                throw new RuntimeException('Destino de política de recuperación enlazado.');
            }
            if (! is_dir($directory) && ! mkdir($directory, 0700)) {
                throw new RuntimeException('No se pudo preparar política privada de recuperación.');
            }
        }
        self::archiveFile($target.'/deploy/production/CPANEL-DML-RISK.md', 'recovery-risk-policy');
        SharedDatabaseLease::atomic($target.'/deploy/production/CPANEL-DML-RISK.md', file_get_contents($source.'/deploy/production/CPANEL-DML-RISK.md'));
        foreach (['IwindsStaging.php', 'IwindsPublicProbe.php'] as $name) {
            self::archiveFile($target.'/app/Support/'.$name, 'recovery-code-'.$name);
        }
        $stage = file_get_contents($target.'/app/Support/IwindsStaging.php');
        if (! str_contains($stage, 'SharedDatabaseLease::guardStaging')) {
            $stage = preg_replace('/(        if \(\$app->runningInConsole\(\)\) \{)/', "        SharedDatabaseLease::guardStaging(\$app);\n$1", $stage, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException('Perfil staging diferente: requiere integrar guard sin retirar sus controles.');
            }
        }
        SharedDatabaseLease::atomic($target.'/app/Support/IwindsStaging.php', $stage);
        $probe = file_get_contents($target.'/app/Support/IwindsPublicProbe.php');
        if (! str_contains($probe, 'SharedDatabaseLease::recoveryActive')) {
            $rules = [
                'public static function requestMode(string $backend, array $server): ?string' => "        if (SharedDatabaseLease::managed() && ! SharedDatabaseLease::legacyStage()) { return SharedDatabaseLease::recoveryActive() ? (preg_match('~^/api/(auth|admin)/~', \$server['REQUEST_URI'] ?? '') ? 'web-admin' : 'web') : null; }\n",
                'public static function activeAdminPeriodExpiry(string $backend): ?int' => "        if (SharedDatabaseLease::managed() && ! SharedDatabaseLease::legacyStage()) { return SharedDatabaseLease::recoveryActive() ? SharedDatabaseLease::state()['expires_at'] : null; }\n"];
            foreach ($rules as $signature => $line) {
                $pattern = '/('.preg_quote($signature, '/').'\s*\{\R)/';
                $probe = preg_replace($pattern, '$1'.$line, $probe, 1, $count);
                if ($count !== 1) {
                    throw new RuntimeException('Probe staging diferente; recuperación bloqueada.');
                }
            }
        }
        SharedDatabaseLease::atomic($target.'/app/Support/IwindsPublicProbe.php', $probe);
        foreach (['IwindsStaging.php', 'IwindsPublicProbe.php'] as $name) {
            (new Process([PHP_BINARY, '-l', $target.'/app/Support/'.$name]))->mustRun();
        }
    }
}
