<?php

use Tests\Http\DisposableDatabase;
use Tests\Http\EditorialFixture;
use Tests\Http\LocalHttps;

require __DIR__.'/../../vendor/autoload.php';

$db = DisposableDatabase::connect();
if (($argv[1] ?? null) === 'write') {
    $db->exec('DELETE FROM auth_login_windows');
    $admin = EditorialFixture::browser();
    $payload = '{"hero":{"titulo":"Persistencia sintética Ñandú 😀"},"extra":{"lista":[],"objeto":{}}}';
    if (EditorialFixture::login($admin, 'super_admin@example.test')->getStatusCode() !== 200
        || $admin->put('/api/admin/site-content', ['body' => $payload, 'headers' => ['Content-Type' => 'application/json']])->getStatusCode() !== 200) {
        throw new RuntimeException('Falló la escritura HTTP del fixture de persistencia.');
    }
}
$row = $db->query('SELECT contenido_json, updated_at FROM portal_site_content WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$response = LocalHttps::client()->get('/api/site-content');
if ($row === false || $response->getStatusCode() !== 200 || $row['contenido_json'] !== (string) $response->getBody()) {
    throw new RuntimeException('El cuerpo HTTP no coincide con el singleton de la base.');
}
echo json_encode([
    'engine' => $db->getAttribute(PDO::ATTR_DRIVER_NAME),
    'status' => $response->getStatusCode(),
    'bytes' => strlen($row['contenido_json']),
    'sha256' => hash('sha256', $row['contenido_json']),
    'updated_at' => $row['updated_at'],
], JSON_THROW_ON_ERROR).PHP_EOL;
