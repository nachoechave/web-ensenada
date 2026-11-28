<?php

require __DIR__.'/common.php';
use App\Support\SharedDatabaseAudit as Audit;
use App\Support\SharedDatabaseLease as Lease;

if (getenv('ENSENADA_TEST_DATABASE_PROFILE') !== 'cpanel-dml') {
    exit;
}
$file = '/home/ensenada/respaldos_privados/audit-observed.json';
hand('audit-checkpoint', ['--schema-config='.C, '--out='.$file, '--previous=/home/ensenada/respaldos_privados/audit-anchor.json']);
hand('verify-audit', ['--schema-config='.C, '--checkpoint='.$file]);
hand('audit-checkpoint', ['--schema-config='.C, '--out=/home/ensenada/respaldos_privados/no-downgrade.json', '--previous=/home/ensenada/respaldos_privados/audit-anchor.json'], false);
hand('verify-audit', ['--schema-config='.C, '--checkpoint=/home/ensenada/respaldos_privados/audit-anchor.json'], false);
$state = Lease::state();
$marker = file_get_contents(P.'/.iwinds-production-enabled');
$runtime = Audit::connect(Audit::env(P));
$row = $runtime->query('SELECT * FROM auditoria_noticias ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
ok((bool) $row, 'hay auditoría real de operaciones HTTP para observar');
$runtime->prepare('UPDATE auditoria_noticias SET noticia_titulo=? WHERE id=?')->execute(['Alteración sintética directa con DML', $row['id']]);
hand('audit-checkpoint', ['--schema-config='.C, '--out=/home/ensenada/respaldos_privados/hidden-tampering.json'], false);
ok(! file_exists('/home/ensenada/respaldos_privados/hidden-tampering.json'), 'no se firma un checkpoint para ocultar cambios');
hand('verify-audit', ['--schema-config='.C, '--checkpoint='.$file], false);
ok(http()->get('/api/site-content')->getStatusCode() === 503, 'alteración observada cierra producción');
ok(! empty(Lease::state()['integrity_blocked']), 'alerta queda registrada fuera de MySQL');
// Controlled restoration is exclusive to this disposable harness, not a delivered reset command.
$runtime->prepare('UPDATE auditoria_noticias SET noticia_titulo=? WHERE id=?')->execute([$row['noticia_titulo'], $row['id']]);
Lease::save($state);
Lease::atomic(P.'/.iwinds-production-enabled', $marker);
$runtime->prepare('DELETE FROM auditoria_noticias WHERE id=?')->execute([$row['id']]);
hand('verify-audit', ['--schema-config='.C, '--checkpoint='.$file], false);
ok(http()->get('/api/noticias')->getStatusCode() === 503, 'borrado observado cierra producción');
$columns = array_keys($row);
$runtime->prepare('INSERT INTO auditoria_noticias(`'.implode('`,`', $columns).'`) VALUES('.implode(',', array_fill(0, count($columns), '?')).')')->execute(array_values($row));
Lease::save($state);
Lease::atomic(P.'/.iwinds-production-enabled', $marker);
$bytes = file_get_contents($file);
file_put_contents($file, $bytes.' '); // A harmless whitespace change preserves its MAC but not its external SHA.
ok(hash_file('sha256', $file) !== hash('sha256', $bytes), 'la copia externa requiere comparar SHA de bytes');
file_put_contents($file, $bytes);
hand('verify-audit', ['--schema-config='.C, '--checkpoint='.$file]);
ok(http()->get('/api/site-content')->getStatusCode() === 200, 'restauración sintética comprobada');
echo "OK $checks comprobaciones de detección de alteración/borrado de auditoría\n";
