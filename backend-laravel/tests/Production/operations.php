<?php

require '/home/ensenada/backend-laravel-produccion/vendor/autoload.php';
use App\Support\SharedDatabaseAudit;
use GuzzleHttp\Client;
use Symfony\Component\Process\Process;

if (getenv('ENSENADA_PRODUCTION_TEST_DISPOSABLE') !== 'true') {
    exit(1);
}
$root = '/home/ensenada/backend-laravel-produccion';
$ops = $root.'/deploy/production';
$checks = 0;
function verifyOperation(bool $ok, string $label): void
{
    global $checks;
    $checks++;
    if (! $ok) {
        throw new RuntimeException($label);
    }
}
function controlOperation(string $op, array $args = []): Process
{
    global $ops;
    $p = $op === 'enable' && $args !== [] ? new Process([PHP_BINARY, $ops.'/handover.php', 'activate', '--schema-config=/home/ensenada/respaldos_privados/schema.cnf', '--authorize-session-revocation', '--authorize-production',
        ...(getenv('ENSENADA_TEST_DATABASE_PROFILE') === 'cpanel-dml' ? ['--accept-cpanel-dml-production-risk'] : [])]) : new Process([PHP_BINARY, $ops.'/control.php', $op, ...$args]);
    $p->run();

    return $p;
}
$http = new Client(['base_uri' => 'https://www.ensenada.gov.ar', 'verify' => false, 'http_errors' => false, 'allow_redirects' => false]);
$status = fn (): int => $http->get('/api/site-content')->getStatusCode();
verifyOperation($status() === 200, 'API inicial');
$stage = '/home/ensenada/web-db-transfer/staging-backend/.env';
rename($stage, $stage.'.offline');
try {
    verifyOperation($status() === 200, 'Producción independiente de archivos de staging');
} finally {
    rename($stage.'.offline', $stage);
}
$shared = fopen($root.'/.production-control-lock', 'c');
flock($shared, LOCK_SH);
$close = new Process([PHP_BINARY, '-n', $ops.'/control.php', 'close']);
$close->start();
usleep(150000);
verifyOperation($close->isRunning(), 'Cierre debe esperar operaciones en curso');
flock($shared, LOCK_UN);
fclose($shared);
$close->wait();
verifyOperation($close->isSuccessful(), 'Cierre sin extensiones ni BD');
verifyOperation($status() === 503, 'API cerrada');
verifyOperation(controlOperation('enable')->getExitCode() !== 0, 'Habilitación exige aprobación explícita');
verifyOperation(controlOperation('enable', ['--approved-data-and-cutover'])->isSuccessful(), 'Habilitación autorizada');
verifyOperation($status() === 200, 'API restaurada');
controlOperation('close');
file_put_contents($root.'/.iwinds-production-enabled', "forged\n");
chmod($root.'/.iwinds-production-enabled', 0600);
verifyOperation($status() >= 500, 'Marcador falsificado no habilita');
controlOperation('close');
verifyOperation(controlOperation('enable', ['--approved-data-and-cutover'])->isSuccessful(), 'Restauración tras marcador inválido');
$pdo = SharedDatabaseAudit::connect(SharedDatabaseAudit::env($root));
foreach (['DELETE FROM usuarios WHERE 1=0', 'UPDATE auditoria_noticias SET id=id WHERE 1=0', 'DELETE FROM auth_mutexes WHERE 1=0',
    'CREATE TABLE forbidden_structure(id INT)'] as $sql) {
    try {
        $pdo->exec($sql);
        if (getenv('ENSENADA_TEST_DATABASE_PROFILE') === 'cpanel-dml' && ! str_starts_with($sql, 'CREATE')) {
            verifyOperation(true, 'Riesgo DML sobre usuarios/auditoría/mutexes demostrado sin modificar filas');

            continue;
        }
        throw new RuntimeException('Operación prohibida permitida');
    } catch (PDOException $e) {
        verifyOperation(in_array($e->errorInfo[1], [1142, 1143], true), 'Mínimos DML/DDL');
    }
}
if (getenv('ENSENADA_TEST_DATABASE_PROFILE') === 'cpanel-dml') {
    $schema = SharedDatabaseAudit::connect(SharedDatabaseAudit::schemaConfig('/home/ensenada/respaldos_privados/schema.cnf'));
    foreach ([$pdo, $schema] as $connection) {
        try {
            $connection->exec("GRANT SELECT ON `ensenada\\_web`.* TO 'ensenada_web_app'@'localhost'");
            throw new RuntimeException('Delegación inesperada');
        } catch (PDOException$e) {
            verifyOperation(in_array($e->errorInfo[1], [1044, 1142, 1143], true), 'Ni runtime ni schema tienen GRANT OPTION');
        }
    }
}
// A fresh SHA alone must not authorize applying a candidate based on an older original.
$current = '/home/ensenada/public_html/.htaccess';
(new Process([PHP_BINARY, $ops.'/cutover.php', 'rollback', $current, '/source/tests/Production/original.htaccess', hash_file('sha256', $current), '--authorized']))->mustRun();
$candidate = '/tmp/stale-candidate-'.bin2hex(random_bytes(8));
(new Process([PHP_BINARY, $ops.'/cutover.php', 'candidate', $current, $candidate]))->mustRun();
$original = file_get_contents($current);
file_put_contents($current, $original."\n# concurrent change\n");
$changed = hash_file('sha256', $current);
$p = new Process([PHP_BINARY, $ops.'/cutover.php', 'apply', $current, $candidate, $changed, '--authorized']);
$p->run();
verifyOperation(! $p->isSuccessful(), 'Candidato obsoleto rechazado incluso con SHA actual');
verifyOperation(hash_file('sha256', $current) === $changed, 'Cambio concurrente preservado');
file_put_contents($current, $original);
(new Process([PHP_BINARY, $ops.'/cutover.php', 'apply', $current, $candidate, hash_file('sha256', $current), '--authorized']))->mustRun();
// Preview is implemented in Apache using the real peer address, never a forwarded header.
(new Process([PHP_BINARY, '/source/tests/Production/refresh-apache.php', '192.0.2.44']))->mustRun();
$response = $http->get('/');
verifyOperation(str_contains((string) $response->getBody(), 'WORDPRESS-UNCHANGED'), 'Preview no sustituye WordPress para otra IP');
$response = $http->get('/', ['headers' => ['X-Forwarded-For' => '192.0.2.44']]);
verifyOperation(str_contains((string) $response->getBody(), 'WORDPRESS-UNCHANGED'), 'No se puede falsificar IP de preview');
(new Process([PHP_BINARY, '/source/tests/Production/refresh-apache.php', '127.0.0.1']))->mustRun();
verifyOperation(str_contains((string) $http->get('/')->getBody(), '<base href="/">'), 'Preview autorizado');
controlOperation('close');
$current = '/home/ensenada/public_html/.htaccess';
$p = new Process([PHP_BINARY, $ops.'/cutover.php', 'rollback', $current, '/source/tests/Production/original.htaccess', str_repeat('0', 64), '--authorized']);
$p->run();
verifyOperation(! $p->isSuccessful(), 'Rollback rechaza SHA obsoleto');
(new Process([PHP_BINARY, $ops.'/cutover.php', 'rollback', $current, '/source/tests/Production/original.htaccess', hash_file('sha256', $current), '--authorized']))->mustRun();
verifyOperation(file_get_contents($current) === file_get_contents('/source/tests/Production/original.htaccess'), 'Rollback conserva exactamente cada byte');
verifyOperation(str_contains((string) $http->get('/')->getBody(), 'WORDPRESS-UNCHANGED'), 'WordPress restaurado');
(new Process([PHP_BINARY, '/source/tests/Production/refresh-apache.php']))->mustRun();
verifyOperation($status() === 503, 'Reinstalar reglas no habilita API');
verifyOperation(controlOperation('enable', ['--approved-data-and-cutover'])->isSuccessful(), 'Restaurar después de rollback');
verifyOperation($status() === 200, 'API final');
echo "OK $checks comprobaciones de aislamiento, permisos, cierre, preview y rollback\n";
