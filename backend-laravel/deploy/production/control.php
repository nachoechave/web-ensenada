<?php

declare(strict_types=1);
use App\Content\SiteContentDocument;
use App\Support\ProductionDatabaseProfile;
use App\Support\ProductionPermissions;
use App\Support\SharedDatabaseLease;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);
$root = '/home/ensenada/backend-laravel-produccion';
$op = $argv[1] ?? '';
try {
    require_once $root.'/app/Support/SharedDatabaseLease.php';
    SharedDatabaseLease::lock();
    $lock = fopen($root.'/.production-control-lock', 'c');
    if (! $lock || ! flock($lock, LOCK_EX)) {
        throw new RuntimeException('No se pudo bloquear.');
    }
    if ($op === 'close') {
        $file = $root.'/.iwinds-production-enabled';
        if (is_link($file) || (is_file($file) && ! unlink($file))) {
            throw new RuntimeException('No se pudo cerrar.');
        }
        echo "Producción cerrada. Staging no recibe acceso automáticamente.\n";
        exit;
    }
    if ($op === 'status') {
        echo json_encode(array_intersect_key(SharedDatabaseLease::state(), array_flip(['owner', 'phase', 'epoch', 'expires_at'])), JSON_PRETTY_PRINT)."\n";
        exit;
    }
    if ($op !== 'check') {
        throw new RuntimeException('Usar handover.php para autorizar transferencia, aislamiento y activación; no se admite habilitación directa.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    ProductionPermissions::verify();
    $db = DB::connection();
    if ($db->table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA=DATABASE()')->count() !== 12
        || $db->table('auth_mutexes')->whereIn('name', ['login', 'news-slug', 'proveedores', 'schema-ready'])->count() !== 4) {
        throw new RuntimeException('Esquema incompleto.');
    }
    $json = $db->table('portal_site_content')->where('id', 1)->value('contenido_json');
    if (! is_string($json) || ! SiteContentDocument::valid($json)) {
        throw new RuntimeException('Contenido existente incompatible.');
    }
    if (! $db->table('usuarios as u')->join('usuario_roles as r', 'r.usuario_id', '=', 'u.id')->where('u.activo', true)->where('r.rol', 'SUPER_ADMIN')->exists()) {
        throw new RuntimeException('SUPER_ADMIN existente requerido.');
    }
    foreach (['noticias', 'sitio', 'hacienda', 'proveedores', 'ensenada-site-scratch', 'ensenada-news-input'] as $name) {
        $path = $root.'/storage/app/private/'.$name;
        if (realpath($path) !== $path || ! is_writable($path) || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('Almacenamiento privado inválido.');
        }
    }
    echo 'Configuración, propiedad exclusiva, permisos / '.ProductionDatabaseProfile::selected().", usuarios y contenido existentes verificados.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'BLOQUEADO: '.($error instanceof PDOException ? 'Comprobación MySQL rechazada.' : $error->getMessage())."\n");
    exit(1);
}
