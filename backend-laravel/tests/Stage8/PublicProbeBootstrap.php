<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

if (PHP_SAPI !== 'cli' || ! is_file('/.dockerenv') || getcwd() !== '/home/ensenada/backend-laravel') {
    exit(1);
}

$scenario = $argv[1] ?? '';
$target = in_array($scenario, ['forbidden', 'admin', 'bearer'], true) ? '/api/auth/me' : '/api/site-content';
$_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REMOTE_USER'] = 'synthetic-apache-user';
$_SERVER['HTTP_HOST'] = 'prueba.ensenada.gov.ar';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $target;
$_SERVER['REDIRECT_ENSENADA_REQUEST_LINE'] = 'GET '.$target.' HTTP/2.0';
$_SERVER['HTTP_AUTHORIZATION'] = 'Basic c3ludGhldGljOnRlc3Q=';
$_SERVER['PHP_AUTH_USER'] = 'synthetic-apache-user';
$_SERVER['PHP_AUTH_PW'] = 'synthetic';
if ($scenario === 'bearer') {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer synthetic';
}
if ($scenario === 'no-apache-user') {
    unset($_SERVER['REMOTE_USER']);
    $_SERVER['HTTP_REMOTE_USER'] = 'forged';
}
if ($scenario === 'no-tls') {
    unset($_SERVER['HTTPS']);
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
}
$mode = match ($scenario) {
    'allowed', 'forbidden', 'expired', 'conflict', 'no-apache-user', 'no-tls' => 'public',
    'admin', 'bearer' => 'admin',
    'full' => 'full',
    default => null,
};
if ($mode !== null) {
    define('ENSENADA_IWINDS_STAGING_MODE', $mode);
}

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
if ($app->runningInConsole()) {
    exit(1);
}

try {
    $app->make(Kernel::class)->bootstrap();
    $route = $app->make('router')->getRoutes()->match(Request::createFromBase(SymfonyRequest::createFromGlobals()));
    $methodTest = Request::createFromBase(SymfonyRequest::create('/api/auth/login', 'POST', ['_method' => 'DELETE']));
    if (! in_array($scenario, ['allowed', 'admin', 'full'], true) || $route->uri() !== ltrim($target, '/')
        || isset($_SERVER['HTTP_AUTHORIZATION']) || isset($_SERVER['PHP_AUTH_USER']) || isset($_SERVER['PHP_AUTH_PW'])
        || $methodTest->getMethod() !== 'POST') {
        exit(1);
    }
} catch (RuntimeException $error) {
    if (! in_array($scenario, ['closed', 'direct', 'forbidden', 'expired', 'conflict', 'no-apache-user', 'no-tls', 'bearer'], true)
        || $error->getMessage() !== 'API de staging deshabilitada.') {
        throw $error;
    }
}

echo 'Bootstrap de Laravel: '.$scenario.' verificado'.PHP_EOL;
