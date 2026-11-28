<?php

use App\Support\IwindsPublicProbe;

require '/home/ensenada/backend-laravel/vendor/autoload.php';
$root = '/home/ensenada/backend-laravel';
$mode = IwindsPublicProbe::requestMode($root, $_SERVER);
if ($mode === null || ! IwindsPublicProbe::allows($_SERVER, $mode) || ! IwindsPublicProbe::stripApacheAuthorization($_SERVER)) {
    http_response_code(404);
    exit;
}
define('ENSENADA_IWINDS_STAGING_MODE', $mode);
require $root.'/public/index.php';
