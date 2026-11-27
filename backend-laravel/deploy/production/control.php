<?php

declare(strict_types=1);
use App\Content\SiteContentDocument;
use App\Support\IwindsProduction;
use App\Support\ProductionPermissions;
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
    if (realpath($root) !== $root || is_link($root)) {
        throw new RuntimeException('Directorio privado inválido.');
    }
    $lock = fopen($root.'/.production-control-lock', 'c');
    if ($lock === false || ! flock($lock, LOCK_EX)) {
        throw new RuntimeException('No se pudo bloquear el control.');
    }
    $marker = $root.'/.iwinds-production-enabled';
    if ($op === 'close') {
        if (is_link($marker) || (file_exists($marker) && ! unlink($marker))) {
            throw new RuntimeException('No se pudo cerrar.');
        }
        echo "Producción cerrada. Las peticiones nuevas responden 503.\n";
        exit;
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! $app->environment('production')) {
        throw new RuntimeException('Entorno incorrecto.');
    }
    if ($op === 'status') {
        echo IwindsProduction::enabled() ? "Habilitado\n" : "Deshabilitado\n";
        exit;
    }
    if ($op === 'attest-isolation') {
        IwindsProduction::verifyIndependentStageSecrets();
        $path = $root.'/.production-isolation';
        $value = 'isolation-v1 '.IwindsProduction::fingerprint()."\n";
        if (is_link($path) || (file_exists($path) && file_get_contents($path) !== $value)) {
            throw new RuntimeException('Comprobante de aislamiento anterior diferente.');
        }
        if (! file_exists($path)) {
            $stream = fopen($path, 'xb');
            fwrite($stream, $value);
            fclose($stream);
            chmod($path, 0600);
        }
        echo "Aislamiento inicial comprobado. Producción no requiere leer staging durante su ejecución.\n";
        exit;
    }
    if (! in_array($op, ['check', 'enable'], true)) {
        throw new RuntimeException('Uso: control.php close|status|check|enable --approved-data-and-cutover');
    }
    if (! IwindsProduction::isolationVerified()) {
        throw new RuntimeException('Ejecutar attest-isolation antes de comprobar o habilitar.');
    }
    ProductionPermissions::verify();
    $db = DB::connection();
    $version = $db->selectOne('SELECT VERSION() AS v')->v;
    if (! preg_match('/^(?:5[.]5[.]5-)?10[.]11[.][0-9]+-MariaDB/i', $version)
        || $db->table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA=DATABASE()')->count() !== 12
        || $db->table('auth_mutexes')->whereIn('name', ['login', 'news-slug', 'proveedores', 'schema-ready'])->count() !== 4) {
        throw new RuntimeException('Esquema o motor incorrecto.');
    }
    $content = $db->table('portal_site_content')->where('id', 1)->value('contenido_json');
    if (! is_string($content) || ! SiteContentDocument::valid($content)) {
        throw new RuntimeException('Contenido aprobado pendiente.');
    }
    $admin = $db->table('usuarios as u')->join('usuario_roles as r', 'r.usuario_id', '=', 'u.id')
        ->where('u.activo', true)->where('r.rol', 'SUPER_ADMIN')->first(['u.id', 'u.token_version']);
    if (! $admin) {
        throw new RuntimeException('Administrador inicial pendiente.');
    }
    // Reversible proof of the trigger using the actual low-privilege runtime account.
    $db->beginTransaction();
    try {
        $row = $db->table('usuarios')->where('id', $admin->id)->lockForUpdate()->first();
        $db->table('auth_sessions')->insert(['token_hash' => str_repeat('a', 64), 'usuario_id' => $row->id,
            'token_version' => $row->token_version, 'created_at' => time(), 'expires_at' => time() + 60]);
        $db->table('usuarios')->where('id', $row->id)->update(['activo' => false]);
        if ($db->table('auth_sessions')->where('usuario_id', $row->id)->exists()
            || (int) $db->table('usuarios')->where('id', $row->id)->value('token_version') <= $row->token_version) {
            throw new RuntimeException('Revocación por trigger inválida.');
        }
    } finally {
        $db->rollBack();
    }
    foreach (['noticias', 'sitio', 'hacienda', 'proveedores', 'ensenada-site-scratch', 'ensenada-news-input'] as $name) {
        $path = $root.'/storage/app/private/'.$name;
        if (realpath($path) !== $path || ! is_dir($path) || ! is_writable($path) || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('Almacenamiento privado pendiente.');
        }
    }
    if ($op === 'enable') {
        if (($argv[2] ?? '') !== '--approved-data-and-cutover') {
            throw new RuntimeException('Aprobación explícita obligatoria.');
        }
        if (is_link($marker)) {
            throw new RuntimeException('Marcador inválido.');
        }
        $value = 'production-v1 '.IwindsProduction::fingerprint()."\n";
        if (file_exists($marker)) {
            if (! hash_equals($value, file_get_contents($marker))) {
                throw new RuntimeException('Marcador existente diferente.');
            }
        } else {
            $stream = fopen($marker, 'xb');
            if ($stream === false || fwrite($stream, $value) !== strlen($value)) {
                throw new RuntimeException('No se pudo habilitar.');
            }
            fclose($stream);
            chmod($marker, 0600);
        }
        echo "Backend habilitado explícitamente. Este comando no cambia Apache.\n";
    } else {
        echo "Configuración, permisos por tabla, contenido, administrador y trigger verificados.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'BLOQUEADO: '.$error->getMessage()."\n");
    exit(1);
}
