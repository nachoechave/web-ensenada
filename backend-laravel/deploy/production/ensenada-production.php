<?php

// Public bridge only. Application, keys and files stay outside every document root.
declare(strict_types=1);
use App\Support\IwindsProduction;
use App\Support\IwindsPublicProbe;

$root = '/home/ensenada/backend-laravel-produccion';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    require_once $root.'/app/Support/IwindsPublicProbe.php';
    require_once $root.'/app/Support/IwindsProduction.php';
    if (! IwindsProduction::allows($_SERVER) || array_key_exists('_method', $_POST)) {
        http_response_code(404);
        exit;
    }
    // Preserve Basic compatibility for an optional restricted Apache preview, never accept Bearer.
    if (! IwindsPublicProbe::stripApacheAuthorization($_SERVER, true)) {
        http_response_code(404);
        exit;
    }
    // A close or backup waits for in-flight requests and prevents new database/file operations.
    $deploymentLock = @fopen($root.'/.production-control-lock', 'c');
    if ($deploymentLock === false || ! flock($deploymentLock, LOCK_SH)) {
        http_response_code(503);
        exit;
    }
    if (! is_file($root.'/'.IwindsProduction::MARKER)) {
        http_response_code(503);
        header('Retry-After: 300');
        exit;
    }
    if (PHP_VERSION_ID < 80300 || PHP_VERSION_ID >= 80400
        || (ini_parse_quantity((string) ini_get('memory_limit')) !== -1 && ini_parse_quantity((string) ini_get('memory_limit')) < 268435456)
        || ini_parse_quantity((string) ini_get('upload_max_filesize')) < 33554432
        || ini_parse_quantity((string) ini_get('post_max_size')) < 41943040) {
        http_response_code(503);
        exit;
    }
    header_remove('Cache-Control');
    header_remove('X-Content-Type-Options');
    define('ENSENADA_IWINDS_PRODUCTION', true);
    require $root.'/public/index.php';
} catch (Throwable $exception) {
    // Do not print keys, paths, SQL, request bodies or credentials.
    error_log('Web Ensenada: producción bloqueada; revisar comprobaciones privadas.');
    http_response_code(503);
}
