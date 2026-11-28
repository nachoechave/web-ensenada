<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);
$root = '/home/ensenada/backend-laravel-produccion';
if (realpath($root) !== $root || is_link($root) || file_exists($root.'/.env') || is_link($root.'/.env') || file_exists($root.'/bootstrap/cache/config.php')) {
    fwrite(STDERR, "Configuración existente o destino inválido. No se modifica.\n");
    exit(1);
}
$env = file_get_contents(__DIR__.'/environment.example');
$env = str_replace('APP_KEY=', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $env);
$env = str_replace('AUTH_JWT_SECRET=', 'AUTH_JWT_SECRET='.bin2hex(random_bytes(48)), $env);
$handle = fopen($root.'/.env', 'xb');
if ($handle === false || fwrite($handle, $env) !== strlen($env)) {
    fwrite(STDERR, "No se pudo inicializar.\n");
    exit(1);
}
fclose($handle);
chmod($root.'/.env', 0600);
echo "Claves independientes guardadas en privado. Completar DB_PASSWORD mediante editor privado antes de generar cachés.\n";
