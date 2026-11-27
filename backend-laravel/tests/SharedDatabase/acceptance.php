<?php

use Tests\Fixtures\HaciendaDocuments;
use Tests\Fixtures\ProviderDocuments;

require __DIR__.'/common.php';
require '/source/tests/Fixtures/HaciendaDocuments.php';
require '/source/tests/Fixtures/ProviderDocuments.php';
$admin = http();
ok($admin->post('/api/auth/login', ['json' => ['email' => 'admin@example.test', 'password' => 'Synthetic-Production-Test-123!']])->getStatusCode() === 200, 'login fixture aceptación');
$publication = ['titulo' => 'Publicación sintética aceptación', 'descripcion' => 'Solo Docker', 'tipo' => 'PRESUPUESTO', 'fechaPublicacion' => '2026-11-27', 'estado' => 'BORRADOR'];
$r = $admin->post('/api/admin/hacienda', ['json' => $publication]);
ok($r->getStatusCode() === 201, 'borrador aceptación');
$entry = json_decode((string) $r->getBody(), true);
$r = $admin->post('/api/admin/hacienda/'.$entry['id'].'/archivos', HaciendaDocuments::options(HaciendaDocuments::pdf()));
ok($r->getStatusCode() === 201, 'documento aceptación');
$publication['estado'] = 'PUBLICADA';
ok($admin->put('/api/admin/hacienda/'.$entry['id'], ['json' => $publication])->getStatusCode() === 200, 'publicar aceptación');
$r = $admin->post('/api/admin/proveedores/documentos', ProviderDocuments::upload(HaciendaDocuments::pdf()));
ok($r->getStatusCode() === 201, 'proveedor aceptación');
$provider = json_decode((string) $r->getBody(), true);
$browser = json_decode(file_get_contents('/source/output/browser-production/browser-result.json'), true);
$r = $admin->get('/api/noticias/'.$browser['slug']);
$news = json_decode((string) $r->getBody(), true);
$private = schema()->query("SELECT a.nombre_almacenado FROM archivos_hacienda a JOIN publicaciones_hacienda p ON p.id=a.publicacion_hacienda_id WHERE p.estado='ARCHIVADA' ORDER BY a.id LIMIT 1")->fetchColumn();
file_put_contents('/home/ensenada/respaldos_privados/staging-basic.txt', 'staging:synthetic-basic-only');
chmod('/home/ensenada/respaldos_privados/staging-basic.txt', 0600);
$p = command(['-d', 'curl.cainfo=/tmp/cert.pem', P.'/deploy/production/acceptance.php', '--news='.$news['slug'], '--hacienda='.$entry['id'],
    '--image='.$news['imagen'], '--provider='.$provider['url'], '--private-document=/uploads/hacienda/'.$private, '--staging-basic-file=/home/ensenada/respaldos_privados/staging-basic.txt']);
echo $p->getOutput();
echo "OK $checks preparación y ejecución de aceptación con validación TLS\n";
