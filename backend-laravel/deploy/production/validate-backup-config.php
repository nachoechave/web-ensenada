<?php

declare(strict_types=1);
use App\Support\SharedDatabaseAudit;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
try {
    SharedDatabaseAudit::schemaConfig($argv[1] ?? '');
    echo "Configuración privada de respaldo existente válida.\n";
} catch (Throwable) {
    fwrite(STDERR, "BLOQUEADO: se requiere [client] privado 0600, localhost/127.0.0.1, ensenada_web_schema y database=ensenada_web, sin inclusiones.\n");
    exit(1);
}
