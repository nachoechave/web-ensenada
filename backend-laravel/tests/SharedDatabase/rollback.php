<?php

require __DIR__.'/common.php';
use App\Support\SharedDatabaseAudit as Audit;
use App\Support\SharedDatabaseLease as Lease;
use Symfony\Component\Process\Process;

$prod = Audit::env(P);
$stageKeys = Audit::env(Lease::PARKED);
$writer = Audit::connect($prod);
$writer->exec("UPDATE portal_site_content SET updated_at='2026-11-27 12:34:56' WHERE id=1");
$newFile = P.'/storage/app/private/hacienda/produccion-posterior.pdf';
file_put_contents($newFile, "%PDF-1.4\nnew committed production artifact\n%%EOF\n");
chmod($newFile, 0600);
$before = Audit::snapshot(schema(), P.'/storage/app/private');
// Shared request leases block an ownership transition until the request drains.
$lease = fopen(Lease::ROOT.'/owner.lock', 'c');
flock($lease, LOCK_SH);
$pause = new Process([PHP_BINARY, H, 'pause-production', '--authorize-production-pause']);
$pause->start();
usleep(150000);
ok($pause->isRunning(), 'corte espera la solicitud que posee la base');
flock($lease, LOCK_UN);
fclose($lease);
$pause->wait();
ok($pause->isSuccessful(), 'pausa exclusiva finaliza');
ok(http()->get('/api/site-content')->getStatusCode() === 503, 'producción cerrada para rollback');
hand('recover-stage', ['--minutes=30', '--authorize-recovery-with-current-data', '--authorize-mysql-password-rotation', '--authorize-session-revocation'], false);
hand('backup', ['--schema-config='.C, '--out=/home/ensenada/respaldos_privados/reversion-01']);
hand('recover-stage', ['--minutes=30', '--authorize-recovery-with-current-data', '--authorize-mysql-password-rotation', '--authorize-session-revocation']);
sleep(3);
ok(Audit::snapshot(schema(), Lease::STAGE.'/storage/app/private')['data_digest'] === $before['data_digest'], 'rollback conserva todas las escrituras editoriales de producción');
ok(Audit::files(Lease::STAGE.'/storage/app/private') === $before['files'], 'rollback conserva archivos nuevos e históricos');
$stageEnv = Audit::env(Lease::STAGE);
foreach (['APP_KEY', 'AUTH_JWT_SECRET'] as $key) {
    ok($stageEnv[$key] === $stageKeys[$key], 'clave staging original conservada');
}
try {
    Audit::connect($prod);
    ok(false, 'producción reconecta');
} catch (PDOException) {
    ok(true, 'producción ya no puede conectar');
}
try {
    $writer->query('SELECT 1');
    ok(false, 'conexión producción sobrevivió');
} catch (PDOException) {
    ok(true, 'conexión de producción cerrada');
}
ok(http()->get('/api/noticias')->getStatusCode() === 503, 'producción no accede durante recuperación');
ok(http('prueba.ensenada.gov.ar')->get('/api/noticias')->getStatusCode() === 401, 'Basic sigue obligatorio en subdominio');
$stage = http('prueba.ensenada.gov.ar', true);
ok($stage->get('/api/site-content')->getStatusCode() === 200, 'recuperación sirve datos actuales con Basic');
ok($stage->get('/api/admin/usuarios')->getStatusCode() === 401, 'Basic no reemplaza sesión individual');
schema()->exec('DELETE FROM auth_login_windows');
schema()->exec("UPDATE usuarios SET activo=1 WHERE email='admin@example.test'");
ok($stage->post('/api/auth/login', ['json' => ['email' => 'admin@example.test', 'password' => 'Synthetic-Production-Test-123!']])->getStatusCode() === 200, 'login real durante recuperación');
ok($stage->get('/api/admin/usuarios')->getStatusCode() === 200, 'roles vigentes durante recuperación');
ok(in_array(http('prueba.ensenada.gov.ar')->get('/api/admin/usuarios', ['headers' => ['Authorization' => 'Bearer invalid']])->getStatusCode(), [401, 403, 404], true), 'Bearer bloqueado por Basic/puente');
ok($stage->post('/api/auth/logout', ['headers' => ['Origin' => 'https://evil.example.test']])->getStatusCode() === 404, 'Origin/CSRF conservados');
Lease::save([...Lease::state(), 'expires_at' => time() - 1]);
ok($stage->get('/api/site-content')->getStatusCode() === 503, 'recuperación expira antes de controladores');
hand('close-recovery', ['--authorize-production-pause']);
ok($stage->get('/api/site-content')->getStatusCode() === 503, 'cierre inmediato de recuperación');
// Returning to production starts a NEW epoch and invalidates all earlier approvals/proofs.
hand('pause-stage', ['--authorize-staging-pause', '--confirm-exclusive-account-use', '--confirm-no-background-writers']);
hand('rotate-production', ['--authorize-mysql-password-rotation']);
hand('activate', ['--schema-config='.C, '--authorize-production', '--authorize-session-revocation'], false);
ok(Audit::snapshot(schema(), Lease::PARKED.'/storage/app/private')['data_digest'] === $before['data_digest'], 'nuevo corte conserva datos actuales y exige nueva aprobación');
echo "OK $checks comprobaciones de rollback sin pérdida de escrituras\n";
