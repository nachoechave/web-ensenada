<?php

use App\Support\IwindsPublicProbe;

if (getenv('ENSENADA_PRODUCTION_TEST_DISPOSABLE') !== 'true' || ! is_file('/.dockerenv')) {
    exit(1);
}
umask(0077);
$zip = new ZipArchive;
$zip->open('/base-release.zip');
$zip->extractTo('/tmp/base');
$zip->close();
passthru('php /tmp/base/install.php private', $status);
if ($status !== 0) {
    exit($status);
}
$p = '/home/ensenada/backend-laravel-produccion';
$s = '/home/ensenada/backend-laravel';
mkdir($s, 0700);
passthru('cp -a /home/ensenada/backend-laravel-produccion/. /home/ensenada/backend-laravel/');
$env = file_get_contents('/source/deploy/production/environment.example');
$env = str_replace(['APP_KEY=', 'AUTH_JWT_SECRET=', 'DB_HOST=localhost', 'DB_PASSWORD='], ['APP_KEY=base64:'.base64_encode(random_bytes(32)), 'AUTH_JWT_SECRET='.bin2hex(random_bytes(48)), 'DB_HOST=127.0.0.1', 'DB_PASSWORD=synthetic-staging-runtime-only'], $env);
$stage = str_replace(['APP_ENV=production', 'https://www.ensenada.gov.ar'], ['APP_ENV=staging', 'https://prueba.ensenada.gov.ar'], $env);
file_put_contents($s.'/.env', $stage);
chmod($s.'/.env', 0600);
$env = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $env);
$env = preg_replace('/^AUTH_JWT_SECRET=.*$/m', 'AUTH_JWT_SECRET='.bin2hex(random_bytes(48)), $env);
file_put_contents($p.'/.env', $env);
chmod($p.'/.env', 0600);
mkdir('/home/ensenada/respaldos_privados', 0700);
file_put_contents('/home/ensenada/respaldos_privados/schema.cnf', "[client]\nhost=127.0.0.1\nport=3306\nuser=ensenada_web_schema\npassword=synthetic-schema-only\ndatabase=ensenada_web\n");
chmod('/home/ensenada/respaldos_privados/schema.cnf', 0600);
$pdo = new PDO('mysql:host=127.0.0.1;dbname=ensenada_web', 'ensenada_web_schema', 'synthetic-schema-only', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$hash = password_hash('Synthetic-Production-Test-123!', PASSWORD_BCRYPT, ['cost' => 4]);
foreach (['SUPER_ADMIN' => 'admin', 'PRENSA' => 'prensa', 'HACIENDA' => 'hacienda'] as $role => $email) {
    $pdo->prepare('INSERT INTO usuarios(nombre,email,password,activo,rol,token_version) VALUES(?,?,?,1,?,0)')->execute([$role, $email.'@example.test', $hash, $role]);
    $pdo->prepare('INSERT INTO usuario_roles(usuario_id,rol) VALUES(?,?)')->execute([$pdo->lastInsertId(), $role]);
}
$pdo->prepare('INSERT INTO portal_site_content(id,contenido_json,updated_at) VALUES(1,?,UTC_TIMESTAMP())')->execute([file_get_contents('/source/tests/Fixtures/site-content.json')]);
$pdo->prepare('INSERT INTO auth_sessions(token_hash,usuario_id,token_version,created_at,expires_at) VALUES(?,1,0,?,?)')->execute([hash('sha256', 'old-staging-session'), time(), time() + 3600]);
// A historical private artifact must survive transfer and rollback unchanged.
file_put_contents($s.'/storage/app/private/hacienda/historico.pdf', "%PDF-1.4\nSynthetic historical document\n%%EOF\n");
chmod($s.'/storage/app/private/hacienda/historico.pdf', 0600);
require $s.'/vendor/autoload.php';
$proof = bin2hex(random_bytes(32));
file_put_contents($s.'/'.IwindsPublicProbe::ROTATION, "1 1 $proof\n");
file_put_contents($s.'/'.IwindsPublicProbe::ADMIN_MARKER, IwindsPublicProbe::privilegedMarker('admin', time() + 1800, $proof));
foreach ([IwindsPublicProbe::ROTATION, IwindsPublicProbe::ADMIN_MARKER] as $name) {
    chmod($s.'/'.$name, 0600);
}
$zip = new ZipArchive;
$zip->open('/shared-update.zip');
$zip->extractTo('/tmp/increment');
$zip->close();
foreach (['install-private', 'install-private', 'prepare-web'] as $op) {
    passthru('php /tmp/increment/update.php '.$op, $status);
    if ($status !== 0) {
        exit($status);
    }
}
// A partial/manual code change must never be overwritten by the incremental updater.
$route = $p.'/routes/api.php';
$original = file_get_contents($route);
file_put_contents($route, $original."\n// deliberately modified fixture\n");
exec('php /tmp/increment/update.php install-private 2>&1', $failure, $exit);
if ($exit === 0 || file_get_contents($route) !== $original."\n// deliberately modified fixture\n") {
    throw new RuntimeException('Sobrescritura indebida.');
}
file_put_contents($route, $original);
$envSha = hash_file('sha256', $p.'/.env');
foreach (['rollback-code', 'install-private', 'prepare-web'] as $op) {
    passthru('php /tmp/increment/update.php '.$op, $status);
    if ($status !== 0) {
        exit($status);
    }
}
if (hash_file('sha256', $p.'/.env') !== $envSha) {
    throw new RuntimeException('Claves modificadas por instalador/reversión.');
}
echo "Fixture baseline instalado, claves independientes conservadas, incremento idempotente.\n";
