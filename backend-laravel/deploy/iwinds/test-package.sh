#!/bin/sh
# Run inside a disposable PHP 8.3 container with --network none and the ZIP mounted read-only.
set -eu
[ -f /.dockerenv ] && [ ! -e /home/ensenada/backend-laravel ]
cd /release
sha256sum -c web-ensenada-iwinds-staging.zip.sha256
mkdir -p /tmp/unpacked /home/ensenada
unzip -q web-ensenada-iwinds-staging.zip -d /tmp/unpacked
mv /tmp/unpacked/private/backend-laravel /home/ensenada/backend-laravel
cd /home/ensenada/backend-laravel
[ ! -e .env ] && [ ! -e .iwinds-api-enabled ] && [ ! -e database ] && [ ! -e tests ]
[ ! -e vendor/phpunit ] && [ ! -e vendor/laravel/pint ] && [ -z "$(find storage -type f -print)" ]
php -d memory_limit=256M -d upload_max_filesize=32M -d post_max_size=40M -d max_execution_time=120 deploy/iwinds/platform.php
export APP_ENV=staging APP_DEBUG=false APP_URL=https://prueba.ensenada.gov.ar
export DB_CONNECTION=mysql DB_HOST=localhost DB_DATABASE=ensenada_web DB_USERNAME=ensenada_web_app
export DB_PASSWORD=synthetic-package-check
export AUTH_JWT_SECRET=synthetic-package-check-only-not-a-real-key
export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export LOG_CHANNEL=stderr
if APP_ENV=production php artisan route:list >/tmp/refused.log 2>&1; then exit 1; fi
grep -q 'Producción deshabilitada' /tmp/refused.log
if DB_HOST=must-not-connect.invalid php artisan route:list >/tmp/refused.log 2>&1; then exit 1; fi
grep -q 'fuera del alcance permitido' /tmp/refused.log
if php artisan migrate --pretend >/tmp/refused.log 2>&1; then exit 1; fi
grep -q 'Comando Artisan fuera del perfil' /tmp/refused.log
php artisan config:cache
php artisan route:cache
php artisan route:list --json >/tmp/routes.json
php -r '$routes = array_column(json_decode(file_get_contents("/tmp/routes.json"), true, flags: JSON_THROW_ON_ERROR), "uri"); foreach (["api/auth/login", "api/admin/proveedores/documentos", "uploads/hacienda/{nombre}"] as $uri) { if (!in_array($uri, $routes, true)) exit(1); }'
php artisan route:clear
php artisan config:clear
chmod 0700 storage/app/private storage/app/private/*
php -d memory_limit=256M <<'PHP'
<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$store = new App\Proveedores\ProviderStore;
$stream = fopen('php://temp', 'w+b');
fwrite($stream, 'synthetic');
rewind($stream);
$name = $store->put($stream, 'pdf', 9);
fclose($stream);
$opened = $store->open($name);
if ($opened === null || fread($opened[0], 9) !== 'synthetic' || !is_file(storage_path('app/private/proveedores/'.$name))) {
    exit(1);
}
fclose($opened[0]);
$store->discardUpload($name);
if ($store->open($name) !== null) {
    exit(1);
}

foreach (['prueba.ensenada.gov.ar', 'synthetic.on-forge.com'] as $host) {
    $request = Illuminate\Http\Request::create('http://'.$host.'/api/auth/me', server: ['REMOTE_ADDR' => '192.0.2.10']);
    $request->headers->set('X-Forwarded-Proto', 'https');
    $request->headers->set('X-Forwarded-Host', 'localhost');
    (new App\Http\Middleware\TrustLocalProxies)->handle($request, function ($request) use ($host) {
        if ($request->isSecure() || $request->getHost() !== $host || $request->isFromTrustedProxy()) {
            exit(1);
        }
    });
}

$path = '/tmp/synthetic-iwinds-30m.jpg';
imagejpeg(imagecreatetruecolor(64, 64), $path);
$file = fopen($path, 'r+b');
ftruncate($file, 30 * 1024 * 1024);
fclose($file);
$images = new App\Content\SiteImageStore;
$result = (new App\Content\SiteImageEncoder)->encode($path, $images->put(...));
if (! in_array($result['mime'], ['image/webp', 'image/jpeg'], true) || $images->open($result['filename']) === null) {
    exit(1);
}
echo 'Almacenamiento privado, cabeceras de proxy ignoradas y JPEG de 30 MiB procesado'.PHP_EOL;
PHP
mkdir -p /home/ensenada/public_html/prueba.ensenada.gov.ar
cp /tmp/unpacked/apache/ensenada-api.php /home/ensenada/public_html/prueba.ensenada.gov.ar/
php -r '$_SERVER["REQUEST_URI"] = "/api/auth/me"; $_SERVER["HTTPS"] = "on"; include "/home/ensenada/public_html/prueba.ensenada.gov.ar/ensenada-api.php";' | grep -q 'API no disponible'
echo 'ZIP extraído, arranque CLI de staging, caches, guardas y puente cerrado OK'
