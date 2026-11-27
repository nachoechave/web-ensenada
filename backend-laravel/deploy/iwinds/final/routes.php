<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') === 'path') {
    echo $app->getCachedRoutesPath();
    exit;
}

if (($argv[1] ?? '') !== 'check' || ! $app->routesAreCached()) {
    exit(1);
}

$routes = $app->make('router')->getRoutes();
try {
    foreach (['noticias', 'sitio'] as $module) {
        foreach (['GET', 'HEAD'] as $method) {
            foreach (['jpg', 'png', 'webp'] as $extension) {
                $uri = "/uploads/$module/11111111-1111-4111-8111-111111111111.$extension";
                $route = $routes->match(Request::create($uri, $method));
                if ($route->uri() !== "uploads/$module/{filename}") {
                    exit(1);
                }
            }
        }
    }
} catch (HttpExceptionInterface) {
    exit(1);
}

echo "Caché de rutas JPEG, PNG y WebP verificada.\n";
