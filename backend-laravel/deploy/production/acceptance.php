<?php

declare(strict_types=1);
use App\Support\IwindsProduction;
use App\Support\ProductionDatabaseProfile;
use App\Support\ProductionPermissions;
use App\Support\SharedDatabaseLease;
use Illuminate\Contracts\Console\Kernel;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$origin = 'https://www.ensenada.gov.ar';
$options = getopt('', ['news:', 'hacienda:', 'image:', 'provider:', 'private-document:', 'staging-basic-file:', 'accept-cpanel-dml-production-risk']);
require dirname(__DIR__, 2).'/vendor/autoload.php';
try {
    SharedDatabaseLease::production(true);
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    ProductionPermissions::verify();
    ProductionDatabaseProfile::verifyActivation();
    ProductionDatabaseProfile::requireActivationFlag(isset($options['accept-cpanel-dml-production-risk']) ? ['accept-cpanel-dml-production-risk' => true] : []);
    if (! IwindsProduction::enabled() || ! IwindsProduction::isolationVerified()) {
        throw new RuntimeException;
    }
    $basicFile = $options['staging-basic-file'] ?? '';
    if (is_link($basicFile) || ! is_file($basicFile) || (fileperms($basicFile) & 0777) !== 0600) {
        throw new RuntimeException;
    }
    $stageBasic = rtrim(file_get_contents($basicFile), "\r\n");
    if (! preg_match('/\A[^:\r\n]+:[^\r\n]+\z/D', $stageBasic)) {
        throw new RuntimeException;
    }
} catch (Throwable) {
    fwrite(STDERR, "BLOQUEADO propiedad exclusiva, perfil SQL autorizado/aceptación de riesgo, marcador válido o archivo Basic privado pendientes.\n");
    exit(1);
}
$checks = 0;
echo 'Perfil SQL verificado: '.ProductionDatabaseProfile::selected().". Mantener controles y custodia externa.\n";
function assertAcceptance(bool $ok, string $label): void
{
    global $checks;
    $checks++;
    if (! $ok) {
        fwrite(STDERR, 'BLOQUEADO '.$label."\n");
        exit(1);
    }echo 'OK '.$label."\n";
}
function requestAcceptance(string $path, bool $staging = false): array
{
    global $origin,$stageBasic;
    assertAcceptance(str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! preg_match('/[\r\n]/', $path), 'ruta de comprobación local');
    $ch = curl_init(($staging ? 'https://prueba.ensenada.gov.ar' : $origin).$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60]);
    if ($staging) {
        curl_setopt($ch, CURLOPT_USERPWD, $stageBasic);
    }
    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    assertAcceptance($raw !== false, 'HTTPS con certificado válido '.$path);

    return [$info['http_code'], substr($raw, $info['header_size']), substr($raw, 0, $info['header_size'])];
}
foreach (['/api/site-content', '/api/noticias', '/api/auth/me', '/api/admin/usuarios'] as $path) {
    [$status] = requestAcceptance($path, true);
    assertAcceptance($status === 503, 'staging sin acceso a datos productivos aun con Basic '.$path);
}
foreach (['image', 'provider', 'private-document'] as $key) {
    if (isset($options[$key])) {
        [$status] = requestAcceptance($options[$key], true);
        assertAcceptance($status === 503, 'staging no sirve archivos transferidos '.$key);
    }
}
foreach (['/', '/noticias', '/hacienda', '/proveedores', '/areas', '/intendencia', '/admin/login'] as $path) {
    [$status,$body] = requestAcceptance($path);
    assertAcceptance($status === 200 && preg_match('~<base\s+href=["\x27]/["\x27]~i', $body) === 1, 'Angular '.$path);
    assertAcceptance(! str_contains($body, 'prueba.ensenada.gov.ar'), 'sin referencias a staging');
}
foreach (['/api/noticias', '/api/noticias/destacadas', '/api/hacienda', '/api/proveedores/documentos', '/api/site-content'] as $path) {
    [$status,$body,$headers] = requestAcceptance($path);
    $json = json_decode($body, true);
    assertAcceptance($status === 200 && is_array($json) && str_contains(strtolower($headers), 'application/json'), 'contrato JSON '.$path);
    if ($path === '/api/noticias') {
        assertAcceptance(isset($json['content'],$json['page'],$json['totalElements']), 'paginación Noticias');
    }
    assertAcceptance(! preg_match('/"(?:password|token_hash|jwt_secret)"\s*:/i', $body), 'sin secretos en respuesta');
}
foreach (['/api/auth/me', '/api/admin/usuarios', '/api/admin/noticias', '/api/admin/hacienda', '/api/admin/site-content', '/api/admin/proveedores/documentos'] as $path) {
    [$status] = requestAcceptance($path);
    assertAcceptance($status === 401, 'administración requiere sesión '.$path);
}
foreach (['/.env', '/vendor/autoload.php', '/storage/logs/laravel.log', '/web-produccion/ensenada-production.php'] as $path) {
    [$status] = requestAcceptance($path);
    assertAcceptance(in_array($status, [403, 404], true), 'archivo interno bloqueado');
}
$required = ['news', 'hacienda', 'image', 'provider', 'private-document'];
assertAcceptance(array_diff($required, array_keys($options)) === [], 'URLs aprobadas de noticias, imágenes y documentos requeridas para validación completa');
foreach (['news' => '/api/noticias/', 'hacienda' => '/api/hacienda/'] as $key => $prefix) {
    assertAcceptance(preg_match('/\A[a-z0-9-]+\z/D', $options[$key]) === 1, 'identificador');
    [$status,$body] = requestAcceptance($prefix.$options[$key]);
    assertAcceptance($status === 200 && is_array(json_decode($body, true)), 'detalle '.$key);
}
foreach (['image', 'provider'] as $key) {
    [$status,$body,$headers] = requestAcceptance($options[$key]);
    assertAcceptance($status === 200 && strlen($body) > 0, 'descarga '.$key);
    assertAcceptance(str_contains(strtolower($headers), 'nosniff'), 'nosniff descarga');
}
[$status] = requestAcceptance($options['private-document']);
assertAcceptance($status === 404, 'documento privado inaccesible');
echo "OK $checks comprobaciones. Falta la aceptación administrativa manual y la comparación de servicios críticos.\n";
