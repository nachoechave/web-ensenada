<?php

// Staging is parked throughout production. Recovery is explicit, bounded, and requires Apache Basic.
declare(strict_types=1);
use App\Support\IwindsPublicProbe;
use App\Support\SharedDatabaseLease;

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    require_once '/home/ensenada/backend-laravel-produccion/app/Support/SharedDatabaseLease.php';
    SharedDatabaseLease::lock();
    if (! SharedDatabaseLease::recoveryActive()) {
        http_response_code(503);
        exit;
    }
    if (($_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? '') === '') {
        http_response_code(403);
        exit;
    }
    require_once SharedDatabaseLease::STAGE.'/app/Support/IwindsPublicProbe.php';
    $mode = IwindsPublicProbe::requestMode(SharedDatabaseLease::STAGE, $_SERVER);
    if ($mode === null || ! IwindsPublicProbe::allows($_SERVER, $mode) || ! IwindsPublicProbe::validWebOrigin($_SERVER)
        || array_key_exists('_method', $_POST) || ! IwindsPublicProbe::stripApacheAuthorization($_SERVER, true)) {
        http_response_code(404);
        exit;
    }
    header_remove('Cache-Control');
    header_remove('X-Content-Type-Options');
    define('ENSENADA_IWINDS_STAGING_MODE', $mode);
    require SharedDatabaseLease::STAGE.'/public/index.php';
} catch (Throwable) {
    http_response_code(503);
}
