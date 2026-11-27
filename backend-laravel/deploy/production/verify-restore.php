<?php

declare(strict_types=1);

// Run on a LOCAL disposable Docker target, never on iWinds. No restored PHP is executed.
if (PHP_SAPI !== 'cli' || ! is_file('/.dockerenv') || getenv('ENSENADA_ISOLATED_RESTORE') !== 'authorized-disposable') {
    exit(1);
}
umask(0077);
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Support\SharedDatabaseAudit as Audit;
use Symfony\Component\Process\Process;

try {
    [$script, $backup, $output] = $argv;
    if (! is_dir($backup) || file_exists($output) || ! is_dir('/restore') || count(scandir('/restore')) !== 2) {
        throw new RuntimeException('Destino aislado vacío y prueba nueva requeridos.');
    }
    $manifest = json_decode(file_get_contents($backup.'/backup.json'), true, flags: JSON_THROW_ON_ERROR);
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=ensenada_web;charset=utf8mb4', 'ensenada_web_schema', getenv('RESTORE_SCHEMA_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($pdo->query('SELECT @@hostname')->fetchColumn() !== 'ensenada-shared-restore-disposable') {
        throw new RuntimeException('Servidor de restauración no aislado.');
    }
    $pdo->exec("SET time_zone='+00:00'");
    foreach ($manifest['archives'] as $name => $sha) {
        if (! in_array($name, ['database.sql', 'production-private.tar', 'staging-private.tar', 'recovery-private.tar', 'control-private.tar', 'web-rollback.tar'], true)
            || ! hash_equals($sha, hash_file('sha256', $backup.'/'.$name))) {
            throw new RuntimeException('Respaldo alterado.');
        }
        if (! str_ends_with($name, '.tar')) {
            continue;
        }
        $list = new Process(['tar', '-tvf', $backup.'/'.$name]);
        $list->mustRun();
        foreach (explode("\n", trim($list->getOutput())) as $entry) {
            if (! in_array($entry[0] ?? '', ['-', 'd'], true)) {
                throw new RuntimeException('Enlaces/dispositivos en respaldo.');
            }
        }
        $names = new Process(['tar', '-tf', $backup.'/'.$name]);
        $names->mustRun();
        foreach (explode("\n", trim($names->getOutput())) as $entry) {
            if (! preg_match('~\A(?:backend-laravel(?:-produccion)?|staging-backend|web-db-transfer|web-produccion|[.]htaccess)(?:/[^\r\n]*)?\z~D', $entry)
                || preg_match('~(?:^|/)\.\.(?:/|$)|\\\\~', $entry)) {
                throw new RuntimeException('Ruta fuera del respaldo.');
            }
        }
        (new Process(['tar', '-xpf', $backup.'/'.$name, '--no-same-owner', '-C', '/restore'], timeout: 600))->mustRun();
    }
    // SQL must already have been imported into the empty local DB by the disposable runner.
    $schema = Audit::schema($pdo);
    $reference = json_decode(file_get_contents(__DIR__.'/schema-reference.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($schema !== $reference) {
        throw new RuntimeException('Esquema restaurado incompatible.');
    }
    $key = file_get_contents('/restore/web-db-transfer/control.key');
    $storage = is_dir('/restore/staging-backend') ? '/restore/staging-backend/storage/app/private' : '/restore/backend-laravel-produccion/storage/app/private';
    $files = Audit::files($storage);
    $data = Audit::data($pdo, $files, $key);
    $actual = ['schema_digest' => hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR)), 'data_digest' => $data['data_digest'],
        'files_digest' => hash('sha256', json_encode($files, JSON_THROW_ON_ERROR)), 'full_data_digest' => Audit::fullDigest($pdo, $key)];
    foreach ($actual as $field => $value) {
        if (! hash_equals($manifest[$field], $value)) {
            throw new RuntimeException('Restauración no idéntica: '.$field);
        }
    }
    if ($files !== $manifest['files']) {
        throw new RuntimeException('Archivos restaurados diferentes.');
    }
    // Exercise the restored trigger with a reversible update. No user is changed permanently.
    $pdo->beginTransaction();
    try {
        $user = $pdo->query('SELECT id,token_version FROM usuarios WHERE activo=1 ORDER BY id LIMIT 1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if (! $user) {
            throw new RuntimeException('Falta usuario para comprobar trigger.');
        }
        $token = hash('sha256', random_bytes(32));
        $pdo->prepare('INSERT INTO auth_sessions(token_hash,usuario_id,token_version,expires_at,created_at) VALUES(?,?,?,?,?)')->execute([$token, $user['id'], $user['token_version'], time() + 3600, time()]);
        $pdo->prepare('UPDATE usuarios SET activo=0 WHERE id=?')->execute([$user['id']]);
        $query = $pdo->prepare('SELECT COUNT(*) FROM auth_sessions WHERE token_hash=?');
        $query->execute([$token]);
        if ((int) $query->fetchColumn() !== 0) {
            throw new RuntimeException('Trigger no revoca.');
        }
    } finally {
        $pdo->rollBack();
    }
    if (Audit::fullDigest($pdo, $key) !== $manifest['full_data_digest']) {
        throw new RuntimeException('La comprobación no fue reversible.');
    }
    $proof = [...$manifest, 'restored_tables' => 12, 'trigger_tested' => true, 'files_verified' => true, 'isolated_target' => true];
    unset($proof['files']);
    file_put_contents($output, json_encode($proof, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    chmod($output, 0600);
    echo "Restauración aislada idéntica: 12 tablas, trigger, datos completos y archivos. Prueba generada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'BLOQUEADO: '.($e instanceof PDOException ? 'Restauración SQL no verificable.' : $e->getMessage())."\n");
    exit(1);
}
