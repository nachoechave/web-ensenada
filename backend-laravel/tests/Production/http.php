<?php

require '/home/ensenada/backend-laravel-produccion/vendor/autoload.php';
require '/source/tests/Fixtures/HaciendaDocuments.php';
require '/source/tests/Fixtures/ProviderDocuments.php';
if (getenv('ENSENADA_PRODUCTION_TEST_DISPOSABLE') !== 'true') {
    exit(1);
}
$reset = new PDO('mysql:host=127.0.0.1;dbname=ensenada_web_prod', 'prod_setup', 'synthetic-prod-setup-only');
$reset->exec('DELETE FROM auth_login_windows');
$reset->exec("UPDATE usuarios SET activo=1 WHERE email IN ('admin@example.test','prensa@example.test','hacienda@example.test')");
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Tests\Fixtures\HaciendaDocuments;
use Tests\Fixtures\ProviderDocuments;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    $checks++;
    if (! $ok) {
        throw new RuntimeException("FALLO $checks: $message");
    }
}
function client(bool $cookies = false): Client
{
    return new Client(['base_uri' => 'https://www.ensenada.gov.ar', 'verify' => false, 'http_errors' => false, 'allow_redirects' => false,
        'cookies' => $cookies ? new CookieJar : false, 'headers' => ['Origin' => 'https://www.ensenada.gov.ar', 'Sec-Fetch-Site' => 'same-origin']]);
}
function code($response, int $status, string $label = ''): void
{
    check($response->getStatusCode() === $status, $label.' HTTP '.$response->getStatusCode().' esperado '.$status);
}
function json($response): array
{
    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}
function login(string $email): Client
{
    $actor = client(true);
    $r = $actor->post('/api/auth/login', ['json' => ['email' => $email, 'password' => 'Synthetic-Production-Test-123!']]);
    code($r, 200, 'login');
    check(json($r)['token'] === '', 'JWT nunca en JSON');
    $cookies = implode(';', $r->getHeader('Set-Cookie'));
    foreach (['secure', 'httponly', 'samesite=strict', 'path=/api'] as $part) {
        check(str_contains(strtolower($cookies), $part), 'cookie '.$part);
    }

    return $actor;
}

$anon = client();
foreach (['/', '/noticias', '/noticias/slug-historico', '/hacienda', '/hacienda/123', '/proveedores', '/registro-proveedores', '/areas', '/intendencia', '/admin/noticias/nueva'] as $path) {
    $r = $anon->get($path);
    code($r, 200, $path);
    check(str_contains((string) $r->getBody(), '<base href="/">'), 'Angular ruta profunda '.$path);
}
foreach (['/api/noticias', '/api/noticias/destacadas', '/api/hacienda', '/api/proveedores/documentos', '/api/site-content'] as $path) {
    $r = $anon->get($path);
    code($r, 200, $path);
    check(is_array(json($r)), 'JSON público');
}
foreach (['/pdf/historico.pdf' => 'historical-pdf', '/hacienda/historico.pdf' => 'historical-hacienda', '/.well-known/acme-challenge/fixture' => 'acme',
    '/wp-admin/' => 'WORDPRESS-UNCHANGED'] as $path => $body) {
    $r = $anon->get($path);
    code($r, 200, $path);
    check((string) $r->getBody() === $body, 'contenido conservado '.$path);
}
$r = $anon->get('/turnos/consulta');
code($r, 301, 'turnos');
check($r->getHeaderLine('Location') === 'https://veterinaria.ensenada.gov.ar/consulta', 'redirección prioritaria');
$r = $anon->get('https://ensenada.gov.ar/noticias');
code($r, 302, 'canonical www');
check($r->getHeaderLine('Location') === 'https://www.ensenada.gov.ar/noticias', 'canonical');
$r = $anon->get('http://www.ensenada.gov.ar/noticias');
code($r, 302, 'HTTPS');
foreach (['prueba', 'tramites'] as $host) {
    $r = $anon->get('https://'.$host.'.ensenada.gov.ar/');
    code($r, 200, 'subdominio');
    check((string) $r->getBody() === $host.'-unchanged', 'aislamiento subdominio');
}
foreach (['/.env', '/vendor/autoload.php', '/storage/logs/laravel.log', '/backend-laravel-produccion/.env',
    '/api', '/API/site-content', '/api/site-content/', '/api/%73ite-content',
    '/api//site-content', '/api/site-content?x=1', '/api/auth/login?_method=GET'] as $path) {
    code($anon->get($path), 404, 'bloqueo '.$path);
}
foreach (['/web-produccion/ensenada-production.php', '/web-produccion/index.html', '/%77eb-produccion/index.html'] as $path) {
    check(in_array($anon->get($path)->getStatusCode(), [403, 404], true), 'directorio interno bloqueado');
}
code($anon->get('/api/admin/usuarios'), 401, 'anónimo');
code($anon->post('/api/auth/login', ['headers' => ['Origin' => 'https://evil.test'], 'json' => []]), 404, 'CSRF boundary');
foreach (['X-Forwarded-Host' => 'www.ensenada.gov.ar', 'X-HTTP-Method-Override' => 'GET', 'Authorization' => 'Bearer forged'] as $key => $value) {
    code($anon->get('/api/site-content', ['headers' => [$key => $value]]), 404, 'cabecera '.$key);
}
$admin = login('admin@example.test');
$prensa = login('prensa@example.test');
$hacienda = login('hacienda@example.test');
code($admin->get('/api/admin/usuarios'), 200, 'superadmin');
code($prensa->get('/api/admin/usuarios'), 403, 'prensa usuarios');
code($hacienda->get('/api/admin/noticias'), 403, 'hacienda noticias');
code($prensa->get('/api/admin/hacienda'), 403, 'prensa hacienda');
code($prensa->put('/api/admin/site-content', ['json' => new stdClass]), 403, 'prensa CMS');
$cms = json($admin->get('/api/admin/site-content'));
$cms['hero']['titulo'] = 'Portada local de producción';
code($admin->put('/api/admin/site-content', ['json' => $cms]), 200, 'guardar CMS');
check(json($anon->get('/api/site-content'))['hero']['titulo'] === 'Portada local de producción', 'persistencia CMS');
$payload = ['titulo' => 'Noticia sintética producción', 'bajada' => 'Bajada sintética', 'contenido' => 'Contenido de prueba',
    'imagen' => '/assets/ensenada-hero.jpg', 'imagenes' => ['/assets/ensenada-hero.jpg'], 'categoria' => 'Actualidad', 'fechaPublicacion' => '2026-11-27', 'estado' => 'BORRADOR', 'destacada' => true];
$r = $prensa->post('/api/admin/noticias', ['json' => $payload]);
code($r, 201, 'crear noticia');
$news = json($r);
$id = $news['id'];
code($anon->get('/api/noticias/'.$id), 404, 'borrador privado');
$im = imagecreatetruecolor(4000, 2250);
imagefilledrectangle($im, 0, 0, 3999, 2249, imagecolorallocate($im, 90, 130, 170));
ob_start();
imagejpeg($im, null, 95);
$jpeg = ob_get_clean();
ob_start();
imagepng($im);
$png = ob_get_clean();
imagedestroy($im);
$images = [];
foreach (['jpeg' => $jpeg, 'png' => $png] as $format => $bytes) {
    $r = $prensa->post('/api/admin/archivos/noticias', ['multipart' => [['name' => 'archivo', 'contents' => $bytes, 'filename' => 'original.'.$format]]]);
    code($r, 200, 'subir '.$format);
    $image = json($r);
    check(str_ends_with($image['url'], '.webp'), 'optimización WebP');
    $images[] = $image['url'];
    code($anon->get($image['url']), 404, 'imagen no publicada');
    $r = $prensa->get($image['url']);
    code($r, 200, 'preview privada');
    $shape = getimagesizefromstring((string) $r->getBody());
    check(max($shape[0], $shape[1]) === 1920, '1920 proporcional');
}
$payload['imagen'] = $images[0];
$payload['imagenes'] = $images;
$payload['estado'] = 'PUBLICADA';
code($prensa->put('/api/admin/noticias/'.$id, ['json' => $payload]), 200, 'publicar noticia');
code($anon->get('/api/noticias/'.$id), 200, 'noticia pública');
code($anon->get($images[0]), 200, 'imagen pública');
$webp = (string) $anon->get($images[0])->getBody();
code($admin->post('/api/admin/archivos/sitio', ['multipart' => [['name' => 'archivo', 'contents' => $webp, 'filename' => 'foto.webp']]]), 200, 'WebP institucional');
code($prensa->post('/api/admin/archivos/noticias', ['multipart' => [['name' => 'archivo', 'contents' => '<script>bad</script>', 'filename' => 'fake.jpg']]]), 400, 'archivo falsificado');
$h = ['titulo' => 'Hacienda sintética', 'descripcion' => 'Prueba', 'tipo' => 'PRESUPUESTO', 'fechaPublicacion' => '2026-11-27', 'estado' => 'BORRADOR'];
$r = $hacienda->post('/api/admin/hacienda', ['json' => $h]);
code($r, 201, 'crear hacienda');
$hid = json($r)['id'];
$h['estado'] = 'PUBLICADA';
code($hacienda->put('/api/admin/hacienda/'.$hid, ['json' => $h]), 400, 'sin documentos');
$r = $hacienda->post('/api/admin/hacienda/'.$hid.'/archivos', HaciendaDocuments::options(HaciendaDocuments::pdf()));
code($r, 201, 'adjunto Hacienda');
$doc = json($r);
$doc = is_array($doc[0] ?? null) ? $doc[0] : $doc;
// The upload contract returns the publication including its complete archivos array.
if (isset($doc['archivos'])) {
    $doc = $doc['archivos'][0];
}
code($anon->get($doc['url']), 404, 'PDF privado');
code($hacienda->get('/api/admin/hacienda/'.$hid.'/archivos/'.$doc['id'].'/contenido'), 200, 'descarga privada');
code($hacienda->put('/api/admin/hacienda/'.$hid, ['json' => $h]), 200, 'publicar Hacienda');
$r = $anon->get($doc['url']);
code($r, 200, 'PDF público');
check($r->getHeaderLine('X-Content-Type-Options') === 'nosniff', 'nosniff');
check(str_contains($r->getHeaderLine('Cache-Control'), 'no-store'), 'no-store');
code($hacienda->delete('/api/admin/hacienda/'.$hid.'/archivos/'.$doc['id']), 400, 'último PDF público protegido');
code($hacienda->delete('/api/admin/hacienda/'.$hid), 204, 'archivar Hacienda');
code($anon->get($doc['url']), 404, 'archivo archivado privado');
$r = $hacienda->post('/api/admin/proveedores/documentos', ProviderDocuments::upload(HaciendaDocuments::pdf()));
code($r, 201, 'proveedores upload');
$provider = json($r);
code($anon->get($provider['url']), 200, 'proveedor público');
code($hacienda->put('/api/admin/proveedores/documentos/'.$provider['id'], ['json' => ['titulo' => 'Documento editado', 'descripcion' => '', 'activo' => false]]), 200, 'despublicar proveedor');
code($anon->get($provider['url']), 404, 'proveedor privado');
code($prensa->delete('/api/admin/noticias/'.$id), 204, 'archivar noticia');
code($anon->get($images[0]), 404, 'imagen archivada');
code($prensa->post('/api/auth/logout'), 204, 'logout');
code($prensa->get('/api/auth/me'), 401, 'sesión revocada');
$pdo = new PDO('mysql:host=127.0.0.1;dbname=ensenada_web_prod', 'prod_setup', 'synthetic-prod-setup-only', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("UPDATE usuarios SET activo=0 WHERE email='hacienda@example.test'");
code($hacienda->get('/api/auth/me'), 401, 'revocación trigger');
$pdo->exec('UPDATE auth_sessions SET expires_at=0');
code($admin->get('/api/auth/me'), 401, 'expiración');
for ($i = 0; $i < 11; $i++) {
    $r = $anon->post('/api/auth/login', ['json' => ['email' => 'missing@example.test', 'password' => 'wrong']]);
}
code($r, 429, 'rate limiting');
echo "OK $checks comprobaciones HTTP reales Apache/PHP/MariaDB\n";
