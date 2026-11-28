<?php

declare(strict_types=1);
use App\Support\SharedDatabaseLease;
use App\Support\SharedDatabaseTransfer;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);
try {
    $bundle = __DIR__;
    $operation = $argv[1] ?? '';
    $root = '/home/ensenada/backend-laravel-produccion';
    if (realpath($root) !== $root || ! is_file($root.'/.env') || is_link($root.'/.env')) {
        throw new RuntimeException('Instalación privada existente requerida; no se crean claves.');
    }
    if (is_file($root.'/.iwinds-production-enabled')) {
        throw new RuntimeException('Cerrar producción antes de actualizar.');
    }
    $release = json_decode(file_get_contents($bundle.'/UPDATE.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest = json_decode(file_get_contents($bundle.'/MANIFEST.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($manifest as $name => $sha) {
        if (preg_match('~(?:^|/)\.\.(?:/|$)|^/|\\\\~', $name) || is_link($bundle.'/'.$name) || ! is_file($bundle.'/'.$name) || ! hash_equals($sha, hash_file('sha256', $bundle.'/'.$name))) {
            throw new RuntimeException('Integridad del incremento inválida.');
        }
    }
    $envHash = hash_file('sha256', $root.'/.env');
    require_once $root.'/vendor/autoload.php';
    require_once $bundle.'/private/app/Support/SharedDatabaseLease.php';
    require_once $bundle.'/private/app/Support/SharedDatabaseTransfer.php';
    if (($release['baseline_sha256'] ?? '') === '7fde8cd1577de52689e26f1227f356aedb6db0ad0e45221ce3dccbfc4f9183da'
        && (! SharedDatabaseLease::managed() || ! is_file(SharedDatabaseLease::ROOT.'/control.key'))) {
        throw new RuntimeException('Primer incremento y clave de transferencia originales requeridos; no se regeneran.');
    }
    SharedDatabaseTransfer::initialize();
    $state = SharedDatabaseLease::state();
    if ($state['owner'] !== 'staging' || $state['phase'] !== 'legacy') {
        throw new RuntimeException('Actualización de código solo antes de transferir la base.');
    }
    $backup = SharedDatabaseLease::ROOT.'/incremental-'.substr($release['id'], 0, 16);
    if (! is_dir($backup)) {
        mkdir($backup, 0700);
    }
    $checkParents = static function (string $file, string $base): void {
        $dir = dirname($file);
        while ($dir !== $base) {
            if (is_link($dir) || (file_exists($dir) && realpath($dir) !== $dir)) {
                throw new RuntimeException('Directorio enlazado.');
            }$dir = dirname($dir);
            if ($dir === '/' || $dir === '.') {
                throw new RuntimeException('Destino fuera de instalación.');
            }
        }
    };
    if ($operation === 'install-private') {
        // Validate all immutable baseline components first, including unchanged authentication/vendor.
        foreach ($release['baseline'] as $name => $old) {
            $target = $root.'/'.$name;
            $checkParents($target, $root);
            if (is_link($target) || ! is_file($target)) {
                throw new RuntimeException('Componente original ausente: '.$name);
            }
            $actual = hash_file('sha256', $target);
            $next = $release['changes'][$name]['new'] ?? $old;
            if ($actual !== $old && $actual !== $next) {
                throw new RuntimeException('Instalación distinta; no se sobrescribe: '.$name);
            }
        }
        foreach ($release['changes'] as $name => $item) {
            $target = $root.'/'.$name;
            $checkParents($target, $root);
            if (is_link($target) || ($item['old'] === null && file_exists($target) && hash_file('sha256', $target) !== $item['new'])) {
                throw new RuntimeException('Archivo adicional diferente: '.$name);
            }
        }
        foreach ($release['changes'] as $name => $item) {
            $target = $root.'/'.$name;
            if (is_file($target) && hash_file('sha256', $target) === $item['new']) {
                continue;
            }
            $before = $backup.'/'.$name;
            if (! is_dir(dirname($before))) {
                mkdir(dirname($before), 0700, true);
            }
            if (is_file($target) && ! is_file($before)) {
                copy($target, $before);
                chmod($before, 0600);
            }
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }
            SharedDatabaseLease::atomic($target, file_get_contents($bundle.'/private/'.$name));
        }
        if (hash_file('sha256', $root.'/.env') !== $envHash) {
            throw new RuntimeException('La configuración existente cambió.');
        }
        echo "Incremento privado instalado/verificado. .env, claves, datos, vendor y staging conservados.\n";
    } elseif ($operation === 'prepare-web') {
        $web = '/home/ensenada/public_html/web-produccion';
        if (realpath($web) !== $web) {
            throw new RuntimeException('Carpeta web existente requerida.');
        }
        foreach (['ensenada-production.php', 'web.htaccess'] as $name) {
            $dest = $web.'/'.($name === 'web.htaccess' ? '.htaccess' : $name);
            $new = file_get_contents($bundle.'/apache/'.$name);
            if (is_link($dest)) {
                throw new RuntimeException('Destino web enlazado.');
            }
            if (is_file($dest) && file_get_contents($dest) === $new) {
                continue;
            }
            if (is_file($dest)) {
                $old = hash_file('sha256', $dest);
                $allowed = $release['web_baseline'][$name] ?? null;
                if ($old !== $allowed && ! ($name === 'web.htaccess' && trim(file_get_contents($dest)) === 'Require all denied')) {
                    throw new RuntimeException('Puente/protección web diferente.');
                }
                $saved = $backup.'/web-'.$name;
                if (! is_file($saved)) {
                    copy($dest, $saved);
                    chmod($saved, 0600);
                }
            }
            SharedDatabaseLease::atomic($dest, $new);
            chmod($dest, 0644);
        }
        echo "Puente y protección del directorio preparados. Angular y .htaccess principal intactos.\n";
    } elseif ($operation === 'rollback-code') {
        if (($state['database_profile'] ?? 'per-table') !== 'per-table') {
            throw new RuntimeException('Volver a per-table con configure-profile antes de revertir código; no se cambian grants ni se habilita API.');
        }
        foreach (['ensenada-production.php', 'web.htaccess'] as $name) {
            $saved = $backup.'/web-'.$name;
            $dest = '/home/ensenada/public_html/web-produccion/'.($name === 'web.htaccess' ? '.htaccess' : $name);
            if (is_file($saved) && (! is_file($dest) || is_link($dest) || hash_file('sha256', $dest) !== hash_file('sha256', $bundle.'/apache/'.$name))) {
                throw new RuntimeException('Puente web cambió; reversión detenida.');
            }
        }
        foreach ($release['changes'] as $name => $item) {
            $target = $root.'/'.$name;
            if (! is_file($target) || hash_file('sha256', $target) !== $item['new']) {
                throw new RuntimeException('Código cambió; reversión detenida.');
            }
            $saved = $backup.'/'.$name;
            if ($item['old'] !== null && $item['old'] !== $item['new'] && (is_link($saved) || ! is_file($saved) || hash_file('sha256', $saved) !== $item['old'])) {
                throw new RuntimeException('Respaldo de código inválido; no se inició la reversión.');
            }
        }
        foreach ($release['changes'] as $name => $item) {
            $target = $root.'/'.$name;
            $before = $backup.'/'.$name;
            if ($item['old'] === $item['new']) {
                continue;
            }
            if ($item['old'] !== null) {
                if (! is_file($before) || hash_file('sha256', $before) !== $item['old']) {
                    throw new RuntimeException('Respaldo de código inválido.');
                }SharedDatabaseLease::atomic($target, file_get_contents($before));
            } else {
                $saved = $backup.'/retired/'.str_replace('/', '__', $name);
                if (! is_dir(dirname($saved))) {
                    mkdir(dirname($saved), 0700, true);
                }if (! rename($target, $saved)) {
                    throw new RuntimeException('No se pudo conservar archivo nuevo.');
                }
            }
        }
        foreach (['ensenada-production.php', 'web.htaccess'] as $name) {
            $saved = $backup.'/web-'.$name;
            if (is_file($saved)) {
                $dest = '/home/ensenada/public_html/web-produccion/'.($name === 'web.htaccess' ? '.htaccess' : $name);
                SharedDatabaseLease::atomic($dest, file_get_contents($saved));
                chmod($dest, 0644);
            }
        }
        echo "Código y protección anterior restaurados antes del corte. Sin modificar la base ni claves.\n";
    } else {
        throw new RuntimeException('Uso: update.php install-private|prepare-web|rollback-code');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'BLOQUEADO: '.$error->getMessage()."\n");
    exit(1);
}
