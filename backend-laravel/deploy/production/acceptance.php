<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$origin = 'https://www.ensenada.gov.ar';
$options = getopt('', ['news:', 'hacienda:', 'image:', 'provider:', 'private-document:']);
$checks = 0;
function assertAcceptance(bool $ok, string $label): void
{
    global $checks;
    $checks++;
    if (! $ok) {
        fwrite(STDERR, 'BLOQUEADO '.$label."\n");
        exit(1);
    }echo 'OK '.$label."\n";
}
function requestAcceptance(string $path): array
{
    global $origin;
    assertAcceptance(str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! preg_match('/[\r\n]/', $path), 'ruta de comprobación local');
    $ch = curl_init($origin.$path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60]);
    $raw = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    assertAcceptance($raw !== false, 'HTTPS con certificado válido '.$path);

    return [$info['http_code'], substr($raw, $info['header_size']), substr($raw, 0, $info['header_size'])];
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
