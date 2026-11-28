<?php

require __DIR__.'/common.php';
use App\Support\ProductionDatabaseProfile as Profile;
use App\Support\SharedDatabaseAudit;
use App\Support\SharedDatabaseLease as Lease;
use App\Support\SharedDatabaseTransfer as Transfer;
use Symfony\Component\Process\Process;

$review = json_decode(file_get_contents(P.'/deploy/production/cpanel-dml-risk.example.json'), true);
Transfer::newJson('/home/ensenada/respaldos_privados/invalid-risk.json', $review);
command([H, 'configure-profile', '--profile=cpanel-dml', '--schema-config='.C, '--risk-review=/home/ensenada/respaldos_privados/invalid-risk.json', '--accept-cpanel-dml-production-risk'], false);
ok((Lease::state()['database_profile'] ?? 'per-table') === 'per-table', 'perfil estricto sigue siendo default');
if (getenv('ENSENADA_TEST_DATABASE_PROFILE') === 'cpanel-dml') {
    $review['policy_sha256'] = Profile::policyDigest();
    foreach (Profile::ACKS as $key) {
        $review['acknowledgements'][$key] = true;
    }
    foreach (['accepted_by', 'authority_reference', 'offsite_backup_reference', 'audit_monitoring_reference', 'incident_response_reference'] as $key) {
        $review[$key] = 'Synthetic fixture acceptance only, never official';
    }
    Transfer::newJson('/home/ensenada/respaldos_privados/accepted-risk.json', $review);
    command([H, 'configure-profile', '--profile=cpanel-dml', '--schema-config='.C, '--risk-review=/home/ensenada/respaldos_privados/accepted-risk.json'], false);
    hand('pause-stage', ['--authorize-staging-pause', '--confirm-exclusive-account-use', '--confirm-no-background-writers'], false);
    command([H, 'configure-profile', '--profile=cpanel-dml', '--schema-config='.C, '--risk-review=/home/ensenada/respaldos_privados/accepted-risk.json', '--accept-cpanel-dml-production-risk']);
    ok(Profile::selected() === 'cpanel-dml', 'perfil requiere aceptación registrada');
    ok(SharedDatabaseAudit::connect(SharedDatabaseAudit::env(P))->query('SELECT CURRENT_USER()')->fetchColumn() === 'ensenada_web_app@localhost', 'cuenta runtime real @localhost por socket');
    ok(schema()->query('SELECT CURRENT_USER()')->fetchColumn() === 'ensenada_web_schema@localhost', 'cuenta esquema real @localhost por socket');
    $state = Lease::state();
    $file = Lease::ROOT.'/'.$state['risk_acceptance']['file'];
    $bytes = file_get_contents($file);
    file_put_contents($file, $bytes.' ');
    try {
        Profile::selected();
        ok(false, 'aceptó receipt alterado');
    } catch (RuntimeException) {
        ok(true, 'receipt alterado rechazado');
    }file_put_contents($file, $bytes);
    $p = new Process([PHP_BINARY, '/tmp/increment/update.php', 'rollback-code']);
    $p->run();
    ok(! $p->isSuccessful(), 'rollback de código exige retirar el perfil alternativo antes');
}
echo "OK $checks comprobaciones de selección explícita y riesgo\n";
