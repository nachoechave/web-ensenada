<?php

declare(strict_types=1);

use App\Support\IwindsPublicProbe as Probe;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

const ROOT = '/home/ensenada/backend-laravel';
const ORIGIN = 'https://prueba.ensenada.gov.ar';
const NEWS_PUBLIC = 'a1111111-1111-4111-8111-111111111111.png';
const NEWS_DRAFT = 'a2222222-2222-4222-8222-222222222222.png';
const SITE_PUBLIC = 'a3333333-3333-4333-8333-333333333333.png';
const SITE_PRIVATE = 'a4444444-4444-4444-8444-444444444444.png';
const HACIENDA_PUBLIC = 'a5555555-5555-4555-8555-555555555555.pdf';
const HACIENDA_PRIVATE = 'a6666666-6666-4666-8666-666666666666.pdf';
const PROVIDER_PUBLIC = 'a7777777-7777-4777-8777-777777777777.pdf';
const PROVIDER_PRIVATE = 'a8888888-8888-4888-8888-888888888888.pdf';

if (! is_file('/.dockerenv') || getenv('IW_FINAL_TEST') !== 'yes' || getcwd() !== ROOT || ! is_file('/tmp/staging-ca.crt')) {
    exit(1);
}
require ROOT.'/vendor/autoload.php';
$app = require ROOT.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
});
if (config('database.connections.mysql.host') !== 'mysql-auth' || config('database.connections.mysql.database') !== 'ensenada_auth_test') {
    exit(1);
}

function check(bool $valid, string $message): void
{
    if (! $valid) {
        throw new RuntimeException($message);
    }
}

function command(string ...$arguments): string
{
    $process = new Process([PHP_BINARY, ...$arguments], timeout: 20);
    $process->mustRun();

    return $process->getOutput();
}

function request(string $method, string $path, int $expected, ?string $cookies = null, ?array $body = null, ?array $extra = null): array
{
    $curl = curl_init(ORIGIN.$path);
    $headers = [];
    if ($extra === null && ! in_array($method, ['HEAD', 'GET'], true)) {
        $extra = ['Origin: '.ORIGIN, 'Sec-Fetch-Site: same-origin'];
    }
    if ($cookies !== null) {
        curl_setopt($curl, CURLOPT_COOKIE, $cookies);
    }
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        $extra[] = 'Content-Type: application/json';
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_NOBODY => $method === 'HEAD', CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json', ...($extra ?? [])],
        CURLOPT_CAINFO => '/tmp/staging-ca.crt', CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower($key)][] = trim($value);
            }

            return strlen($line);
        },
    ]);
    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    check(is_string($result) && $status === $expected, "$method $path: $status, esperado $expected.");
    check(! isset($headers['access-control-allow-origin']), "La API habilitó CORS inesperadamente en $method $path.");

    return [$result, $headers];
}

function login(string $actor): array
{
    [$body, $headers] = request('POST', '/api/auth/login', 200, body: [
        'email' => $actor.'@example.test', 'password' => 'Synthetic-password-1234',
    ]);
    $cookies = [];
    foreach ($headers['set-cookie'] ?? [] as $cookie) {
        check(stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'Secure') !== false
            && stripos($cookie, 'SameSite=Strict') !== false && stripos($cookie, 'Domain=') === false, 'Cookie de staging inválida.');
        $cookies[] = explode(';', $cookie, 2)[0];
    }
    check(count($cookies) === 2 && json_decode($body, true)['token'] === '', 'La sesión debe regresar únicamente en cookies.');
    $session = current(array_filter($cookies, fn (string $cookie): bool => str_starts_with($cookie, 'WEB_ENSENADA_SESSION=')));
    $preview = current(array_filter($cookies, fn (string $cookie): bool => str_starts_with($cookie, '__Secure-WEB_ENSENADA_IMAGE_PREVIEW=')));
    check(is_string($session) && is_string($preview), 'No llegaron ambas cookies.');

    return [$session, $preview];
}

$db = new PDO('mysql:host=mysql-auth;dbname=ensenada_auth_test', 'auth_setup', 'synthetic-setup-only-1234');
if (($argv[1] ?? '') === 'seed') {
    $db->exec('DELETE FROM auth_login_windows');
    $db->exec("UPDATE usuarios SET token_version=1 WHERE email='super_admin@example.test'");
    $admin = $db->query("SELECT id, password, token_version FROM usuarios WHERE email='super_admin@example.test'")->fetch(PDO::FETCH_ASSOC);
    $proof = hash_hmac('sha256', $admin['id'].':'.$admin['token_version'].':'.$admin['password'], config('auth_migration.jwt_secret'));
    file_put_contents(Probe::ROTATION, $admin['id'].' '.$admin['token_version'].' '.$proof."\n");
    chmod(Probe::ROTATION, 0600);
    $now = time();
    file_put_contents(Probe::ADMIN_MARKER, Probe::extendedAdminMarker($now, $now + 86400, $proof));
    chmod(Probe::ADMIN_MARKER, 0600);

    $png = base64_decode(Probe::IMAGE_BASE64, true);
    $pdf = "%PDF-1.4\nsynthetic fixture\n%%EOF\n";
    foreach (['noticias' => [NEWS_PUBLIC => $png, NEWS_DRAFT => $png], 'sitio' => [SITE_PUBLIC => $png, SITE_PRIVATE => $png],
        'hacienda' => [HACIENDA_PUBLIC => $pdf, HACIENDA_PRIVATE => $pdf], 'proveedores' => [PROVIDER_PUBLIC => $pdf, PROVIDER_PRIVATE => $pdf]] as $module => $files) {
        $folder = ROOT.'/storage/app/private/'.$module;
        if (! is_dir($folder)) {
            mkdir($folder, 0700);
        }
        chmod($folder, 0700);
        foreach ($files as $name => $bytes) {
            file_put_contents($folder.'/'.$name, $bytes);
            chmod($folder.'/'.$name, 0600);
        }
    }
    $news = $db->prepare("INSERT INTO noticias (titulo,slug,bajada,contenido,imagen,categoria,fecha_publicacion,estado,destacada)
        VALUES (?,?,'Bajada','Contenido',?,'Pruebas','2026-11-13',?,0)");
    $news->execute(['Noticia visible', 'noticia-visible', '/uploads/noticias/'.NEWS_PUBLIC, 'PUBLICADA']);
    $news->execute(['Noticia borrador', 'noticia-borrador', '/uploads/noticias/'.NEWS_DRAFT, 'BORRADOR']);
    $publication = $db->prepare("INSERT INTO publicaciones_hacienda (titulo,descripcion,tipo,fecha_publicacion,estado)
        VALUES (?,'Documento sintético','PRESUPUESTO','2026-11-13',?)");
    $attachment = $db->prepare("INSERT INTO archivos_hacienda (publicacion_hacienda_id,nombre_original,nombre_almacenado,tipo_mime,tamanio,orden)
        VALUES (?,'Documento.pdf',?,'application/pdf',?,0)");
    foreach ([HACIENDA_PUBLIC => 'PUBLICADA', HACIENDA_PRIVATE => 'ARCHIVADA'] as $file => $status) {
        $publication->execute(['Documento '.$status, $status]);
        $attachment->execute([$db->lastInsertId(), $file, strlen($pdf)]);
    }
    $provider = $db->prepare("INSERT INTO documentos_proveedores (titulo,descripcion,nombre_original,nombre_almacenado,tipo_mime,tamanio,orden,activo,created_at,updated_at)
        VALUES (?,'Documento sintético','Documento.pdf',?,'application/pdf',?,0,?,NOW(),NOW())");
    $provider->execute(['Proveedor visible', PROVIDER_PUBLIC, strlen($pdf), 1]);
    $provider->execute(['Proveedor privado', PROVIDER_PRIVATE, strlen($pdf), 0]);
    $db->prepare('UPDATE portal_site_content SET contenido_json=? WHERE id=1')
        ->execute([json_encode(['hero' => ['imagen' => '/uploads/sitio/'.SITE_PUBLIC, 'imagenes' => ['/uploads/sitio/'.SITE_PUBLIC]]])]);
    exit;
}

$cli = 'deploy/iwinds/provision-staging.php';
$db->exec('DELETE FROM auth_login_windows');
$expiry = Probe::activeAdminPeriodExpiry(ROOT);
check(is_int($expiry), 'Falta el período existente.');
command($cli, 'open-public-web', '--synthetic-only');
check(Probe::activeAdminPeriodExpiry(ROOT) === $expiry, 'La apertura cambió el vencimiento.');
foreach (['/', '/noticias', '/noticias/noticia-visible', '/areas', '/intendencia', '/hacienda', '/proveedores',
    '/api/site-content', '/api/hacienda', '/api/proveedores/documentos', '/api/noticias?page=0&size=12', '/api/noticias/noticia-visible'] as $path) {
    request('GET', $path, 200);
}
request('GET', '/api/noticias/noticia-borrador', 404);
[$list] = request('GET', '/api/noticias?page=0&size=48', 200);
check(! str_contains($list, 'noticia-borrador'), 'El listado mostró un borrador.');
foreach (['noticias' => [NEWS_PUBLIC, NEWS_DRAFT], 'sitio' => [SITE_PUBLIC, SITE_PRIVATE],
    'hacienda' => [HACIENDA_PUBLIC, HACIENDA_PRIVATE], 'proveedores' => [PROVIDER_PUBLIC, PROVIDER_PRIVATE]] as $module => [$public, $private]) {
    request('GET', "/uploads/$module/$public", 200);
    request('HEAD', "/uploads/$module/$public", 200);
    request('GET', "/uploads/$module/$private", 404);
    request('HEAD', "/uploads/$module/$private", 404);
}
request('GET', '/uploads/hacienda/'.HACIENDA_PUBLIC.'?descargar=true', 200);
$db->exec("UPDATE noticias SET estado='ARCHIVADA' WHERE slug='noticia-visible'");
request('GET', '/uploads/noticias/'.NEWS_PUBLIC, 404);
$db->exec("UPDATE noticias SET estado='PUBLICADA' WHERE slug='noticia-visible'");
request('GET', '/uploads/noticias/'.NEWS_PUBLIC, 200);
request('OPTIONS', '/api/auth/login', 404, extra: ['Origin: https://externo.example.test', 'Access-Control-Request-Method: POST']);
request('GET', '/api/admin/usuarios', 401);
request('POST', '/api/admin/noticias', 401, body: []);
request('POST', '/api/auth/login', 403, body: [], extra: ['Origin: https://externo.example.test', 'Sec-Fetch-Site: cross-site']);
request('POST', '/api/auth/login', 403, body: [], extra: ['Origin: '.ORIGIN]);
request('GET', '/api/admin/usuarios', 404, extra: ['Authorization: Bearer fixture']);

[$press, $pressImage] = login('prensa');
[$treasury, $treasuryImage] = login('hacienda');
[$admin, $adminImage] = login('super_admin');
request('GET', '/api/admin/noticias', 200, $press);
request('GET', '/api/admin/hacienda', 403, $press);
request('GET', '/api/admin/usuarios', 403, $press);
request('GET', '/api/admin/hacienda', 200, $treasury);
request('PUT', '/api/admin/noticias/1', 403, $treasury, body: []);
request('GET', '/api/admin/usuarios', 403, $treasury);
request('GET', '/api/admin/usuarios', 200, $admin);
request('GET', '/api/admin/site-content', 200, $admin);
request('PUT', '/api/admin/site-content', 403, $admin, body: [], extra: ['Origin: '.ORIGIN, 'Sec-Fetch-Site: same-site']);
request('GET', '/api/auth/me', 401, $adminImage);
$sameSite = ['Sec-Fetch-Site: same-origin'];
request('GET', '/uploads/noticias/'.NEWS_DRAFT, 200, $pressImage, extra: $sameSite);
request('GET', '/uploads/noticias/'.NEWS_DRAFT, 404, $treasuryImage, extra: $sameSite);
request('GET', '/uploads/sitio/'.SITE_PRIVATE, 200, $adminImage, extra: $sameSite);
request('GET', '/uploads/sitio/'.SITE_PRIVATE, 404, $pressImage, extra: $sameSite);
request('GET', '/uploads/noticias/'.NEWS_DRAFT, 404, $pressImage);
request('POST', '/api/auth/logout', 204, $press);
request('GET', '/api/auth/me', 401, $press);
request('GET', '/uploads/noticias/'.NEWS_DRAFT, 404, $pressImage, extra: $sameSite);
$token = explode('=', $treasury, 2)[1];
$db->prepare('UPDATE auth_sessions SET expires_at=? WHERE token_hash=?')->execute([time() - 1, hash('sha256', $token)]);
request('GET', '/api/auth/me', 401, $treasury);
for ($attempt = 0; $attempt < 7; $attempt++) {
    request('POST', '/api/auth/login', 401, body: ['email' => 'invalid@example.test', 'password' => 'wrong']);
}
request('POST', '/api/auth/login', 429, body: ['email' => 'invalid@example.test', 'password' => 'wrong']);
$db->exec('DELETE FROM auth_login_windows');

$marker = file_get_contents(Probe::ADMIN_MARKER);
$now = time();
file_put_contents(Probe::ADMIN_MARKER, Probe::extendedAdminMarker($now - 86401, $now - 1, Probe::rotation(ROOT)['proof']));
request('GET', '/api/admin/usuarios', 404, $admin);
request('GET', '/uploads/sitio/'.SITE_PRIVATE, 404, $adminImage, extra: $sameSite);
request('GET', '/api/site-content', 200);
file_put_contents(Probe::ADMIN_MARKER, $marker);
echo "HTTPS Laravel y MariaDB: lecturas, medios, roles, CSRF, expiración, logout y límite de login aprobados.\n";
