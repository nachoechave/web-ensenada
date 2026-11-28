<?php

// Smoke-test the exact delivery artifact in a new networkless tmpfs, without a DB.
require '/app/vendor/autoload.php';
use Symfony\Component\Process\Process;

if (! is_file('/.dockerenv') || getenv('ENSENADA_PRODUCTION_TEST_DISPOSABLE') !== 'true') {
    exit(1);
}
umask(0077);
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (! $ok) {
        throw new RuntimeException($label);
    }
};
foreach (['/base-release.zip' => 'base', '/prior-update.zip' => 'prior', '/final-release.zip' => 'update'] as $path => $dir) {
    $zip = new ZipArchive;
    $assert($zip->open($path) === true, 'ZIP abre');
    $assert($zip->extractTo('/tmp/'.$dir), 'ZIP extrae');
    $zip->close();
}
$p = new Process([PHP_BINARY, '/tmp/base/install.php', 'private']);
$p->mustRun();
$root = '/home/ensenada/backend-laravel-produccion';
file_put_contents($root.'/.env', 'APP_KEY=base64:'.base64_encode(random_bytes(32))."\nAUTH_JWT_SECRET=".bin2hex(random_bytes(48))."\nDB_PASSWORD=synthetic-preserved-only\n");
chmod($root.'/.env', 0600);
$before = hash_file('sha256', $root.'/.env');
mkdir('/home/ensenada/public_html/web-produccion', 0700, true);
file_put_contents('/home/ensenada/public_html/web-produccion/.htaccess', "Require all denied\n");
foreach (['install-private', 'prepare-web'] as $op) {
    (new Process([PHP_BINARY, '/tmp/prior/update.php', $op]))->mustRun();
}
$controlKeyBefore = hash_file('sha256', '/home/ensenada/web-db-transfer/control.key');
rename('/home/ensenada/web-db-transfer/control.key', '/home/ensenada/web-db-transfer/control.key.saved');
$missingKey = new Process([PHP_BINARY, '/tmp/update/update.php', 'install-private']);
$missingKey->run();
$assert(! $missingKey->isSuccessful(), 'No admite perder la clave de transferencia');
$assert(! file_exists('/home/ensenada/web-db-transfer/control.key'), 'No regenera una clave faltante');
rename('/home/ensenada/web-db-transfer/control.key.saved', '/home/ensenada/web-db-transfer/control.key');
foreach (['install-private', 'install-private', 'prepare-web', 'rollback-code', 'install-private', 'prepare-web'] as $operation) {
    $p = new Process([PHP_BINARY, '/tmp/update/update.php', $operation]);
    $p->run();
    $assert($p->isSuccessful(), $p->getErrorOutput());
    $assert(hash_file('sha256', $root.'/.env') === $before, '.env y claves cambian');
}
$assert(! is_file($root.'/.iwinds-production-enabled'), 'No habilita API');
$assert(hash_file('sha256', '/home/ensenada/web-db-transfer/control.key') === $controlKeyBefore, 'Clave de transferencia original conservada');
$manifest = json_decode(file_get_contents('/tmp/update/MANIFEST.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($manifest as $name => $sha) {
    $assert(hash_file('sha256', '/tmp/update/'.$name) === $sha, 'SHA componente');
    $assert(! preg_match('~(?:^|/)(?:vendor|storage|node_modules|[.]env)(?:/|$)~', $name), 'Datos/secretos en incremento');
}
echo "OK $checks comprobaciones del ZIP final, instalación idempotente y reversión sin BD/red\n";
