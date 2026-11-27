<?php

require __DIR__.'/common.php';
use App\Support\SharedDatabaseAudit as Audit;
use App\Support\SharedDatabaseLease as Lease;

$stage = http('prueba.ensenada.gov.ar', true);
ok($stage->get('/api/site-content')->getStatusCode() === 200, 'staging anterior operativo con Basic');
ok($stage->post('/api/auth/login', ['json' => ['email' => 'admin@example.test', 'password' => 'Synthetic-Production-Test-123!']])->getStatusCode() === 200, 'sesión real anterior al corte');
$skeys = Audit::env(Lease::STAGE);
$pkeys = Audit::env(P);
$wp = hash_file('sha256', '/home/ensenada/public_html/.htaccess');
$initial = Audit::snapshot(schema(), Lease::STAGE.'/storage/app/private');
hand('activate', ['--schema-config='.C, '--authorize-production', '--authorize-session-revocation'], false);
hand('audit-live', ['--schema-config='.C, '--out=/home/ensenada/respaldos_privados/provisional.json']);
hand('pause-stage', [], false);
$unsafe = '/home/ensenada/public_html/prueba.ensenada.gov.ar/uploads';
mkdir($unsafe, 0700);
hand('pause-stage', ['--authorize-staging-pause', '--confirm-exclusive-account-use', '--confirm-no-background-writers'], false);
rmdir($unsafe);
// Hold an old runtime connection with an uncommitted write: rotation must kill/rollback it.
$old = Audit::connect($skeys);
$old->beginTransaction();
$old->exec("UPDATE portal_site_content SET updated_at='2001-01-01 00:00:00' WHERE id=1");
hand('pause-stage', ['--authorize-staging-pause', '--confirm-exclusive-account-use', '--confirm-no-background-writers']);
sleep(3); // Allow Apache's real OPcache timestamp revalidation after atomic bridge replacement.
ok($stage->get('/api/site-content')->getStatusCode() === 503, 'staging cerrado antes de producción');
ok(! is_file(Lease::STAGE.'/artisan') && ! is_file(Lease::STAGE.'/vendor/autoload.php'), 'backend antiguo aparcado');
hand('rotate-production', ['--authorize-mysql-password-rotation']);
hand('rotate-production', ['--authorize-mysql-password-rotation']);
try {
    $old->query('SELECT 1');
    ok(false, 'conexión vieja viva');
} catch (PDOException) {
    ok(true, 'conexión previa terminada sin PROCESS/KILL global');
}
try {
    Audit::connect($skeys);
    ok(false, 'password vieja válida');
} catch (PDOException) {
    ok(true, 'password anterior rechazada');
}
$prod = Audit::env(P);
foreach (['APP_KEY', 'AUTH_JWT_SECRET'] as $key) {
    ok($prod[$key] === $pkeys[$key], 'clave original producción conservada');
}
ok(Audit::snapshot(schema(), Lease::PARKED.'/storage/app/private')['data_digest'] === $initial['data_digest'], 'ningún contenido/ID/relación cambió y transacción antigua revirtió');
hand('copy-storage', ['--confirm-account-quota'], false);
// Clock control exists only in this excluded disposable test, never in the delivered CLI.
Lease::save([...Lease::state(), 'paused_at' => time() - 126]);
hand('copy-storage', ['--confirm-account-quota']);
hand('copy-storage', ['--confirm-account-quota']);
ok(Audit::files(P.'/storage/app/private') === $initial['files'], 'copia exacta de históricos');
$file = P.'/storage/app/private/hacienda/historico.pdf';
$saved = file_get_contents($file);
file_put_contents($file, 'collision');
hand('copy-storage', ['--confirm-account-quota'], false);
file_put_contents($file, $saved);
$report = '/home/ensenada/respaldos_privados/review.json';
hand('audit', ['--schema-config='.C, '--out='.$report]);
hand('approve-data', ['--schema-config='.C, '--review='.$report, '--authorize-reviewed-data'], false);
hand('activate', ['--schema-config='.C, '--authorize-production', '--authorize-session-revocation'], false);
hand('backup', ['--schema-config='.C, '--out=/home/ensenada/respaldos_privados/corte-01']);
ok(hash_file('sha256', '/home/ensenada/public_html/.htaccess') === $wp, 'WordPress y .htaccess principal sin modificar');
echo "OK $checks comprobaciones de pre-corte/base única\n";
