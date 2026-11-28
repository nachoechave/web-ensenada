<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$request = defined('ENSENADA_IWINDS_STAGING_MODE') || defined('ENSENADA_IWINDS_PRODUCTION')
    ? Request::createFromBase(SymfonyRequest::createFromGlobals())
    : Request::capture();
$app->handleRequest($request);
