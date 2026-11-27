<?php

require __DIR__.'/common.php';
use App\Support\SharedDatabaseTransfer as Transfer;

$review = json_decode(file_get_contents('/home/ensenada/respaldos_privados/review.json'), true, flags: JSON_THROW_ON_ERROR);
// Explicit simulated reviewer decisions for synthetic test fixtures, never shipped as official data.
foreach ($review['review'] as &$item) {
    $item['classification'] = 'APPROVED';
    $item['reason'] = 'Aprobación simulada únicamente del fixture Docker descartable';
}unset($item);
Transfer::newJson('/home/ensenada/respaldos_privados/approved.json', $review);
hand('approve-data', ['--schema-config='.C, '--review=/home/ensenada/respaldos_privados/approved.json', '--authorize-reviewed-data']);
hand('attest-restore', ['--proof=/home/ensenada/respaldos_privados/restore-proof.json', '--authorize-restore-proof']);
$hashes = schema()->query('SELECT id,password FROM usuarios ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
hand('activate', ['--schema-config='.C, '--authorize-production', '--authorize-session-revocation']);
ok((int) schema()->query('SELECT COUNT(*) FROM auth_sessions')->fetchColumn() === 0, 'todas sesiones anteriores revocadas');
ok(schema()->query('SELECT id,password FROM usuarios ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $hashes, 'passwords de usuarios intactas');
command([P.'/artisan', 'config:cache']);
command([P.'/artisan', 'route:cache']);
command([P.'/deploy/production/control.php', 'check']);
command([P.'/deploy/production/cutover.php', 'candidate', '/home/ensenada/public_html/.htaccess', '/tmp/candidate.htaccess']);
command([P.'/deploy/production/cutover.php', 'apply', '/home/ensenada/public_html/.htaccess', '/tmp/candidate.htaccess', hash_file('sha256', '/home/ensenada/public_html/.htaccess'), '--authorized']);
ok(http()->get('/api/site-content')->getStatusCode() === 200, 'producción sirve base transferida');
ok(http('prueba.ensenada.gov.ar', true)->get('/api/noticias')->getStatusCode() === 503, 'staging sigue cerrado con Basic');
ok(http('tramites.ensenada.gov.ar')->get('/')->getBody()->getContents() === 'tramites-unchanged', 'otro subdominio intacto');
echo "OK $checks comprobaciones de activación autorizada\n";
