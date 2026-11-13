<?php

use App\Support\IwindsPublicProbe;

// Candidate bridge for /home/ensenada/public_html/prueba.ensenada.gov.ar only.
$path = explode('?', $_SERVER['REQUEST_URI'] ?? '', 2)[0];
if (preg_match('~^/(?:api|uploads/(?:noticias|sitio|hacienda|proveedores))(?:/|$)~D', $path) !== 1) {
    http_response_code(404);
    exit;
}

$backend = dirname(__DIR__, 2).'/backend-laravel';
$entry = $backend.'/public/index.php';
$policy = $backend.'/app/Support/IwindsPublicProbe.php';
if (is_readable($policy)) {
    require_once $policy;
}
$mode = class_exists(IwindsPublicProbe::class, false) && method_exists(IwindsPublicProbe::class, 'requestMode')
    ? IwindsPublicProbe::requestMode($backend, $_SERVER) : null;
if (($_SERVER['HTTPS'] ?? '') !== 'on' || $mode === null || ! is_readable($entry)) {
    http_response_code(503);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo '{"detail":"API no disponible"}';
    }
    exit;
}

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (! IwindsPublicProbe::allows($_SERVER, $mode) || array_key_exists('_method', $_POST)
    || ! IwindsPublicProbe::stripApacheAuthorization($_SERVER, in_array($mode, ['web', 'web-admin'], true))) {
    http_response_code(404);
    header('Content-Type: application/json');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo '{"detail":"Ruta no disponible en este modo de staging"}';
    }
    exit;
}
if (in_array($mode, ['web', 'web-admin'], true) && ! IwindsPublicProbe::validWebOrigin($_SERVER)) {
    http_response_code(403);
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        echo '{"detail":"Origen de solicitud no permitido"}';
    }
    exit;
}
if ($mode === 'public-image' && $path === IwindsPublicProbe::IMAGE_PATH) {
    $image = base64_decode(IwindsPublicProbe::IMAGE_BASE64, true);
    header('Content-Type: image/png');
    header('Content-Length: '.strlen($image));
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo $image;
    }
    exit;
}
define('ENSENADA_IWINDS_STAGING_MODE', $mode);

require $entry;
