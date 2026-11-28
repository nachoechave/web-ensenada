<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);
try {
    $bundle = __DIR__;
    $op = $argv[1] ?? '';
    $manifest = json_decode(file_get_contents($bundle.'/MANIFEST.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($manifest as $name => $hash) {
        if (str_contains($name, '..') || str_starts_with($name, '/') || is_link($bundle.'/'.$name)
            || ! is_file($bundle.'/'.$name) || ! hash_equals($hash, hash_file('sha256', $bundle.'/'.$name))) {
            throw new RuntimeException('Manifiesto inválido.');
        }
    }
    $root = '/home/ensenada/backend-laravel-produccion';
    $public = '/home/ensenada/public_html/web-produccion';
    $mkdir = static function (string $path) use (&$mkdir): void {
        if (is_link($path)) {
            throw new RuntimeException('Enlace simbólico no permitido.');
        }
        if (! is_dir($path)) {
            $mkdir(dirname($path));
            if (! mkdir($path, 0700)) {
                throw new RuntimeException('No se pudo crear directorio.');
            }
        }
        if (realpath($path) !== $path) {
            throw new RuntimeException('Ruta no canónica.');
        }
    };
    if ($op === 'private') {
        $mkdir($root);
        foreach ($manifest as $name => $hash) {
            $prefix = 'private/backend-laravel-produccion/';
            if (! str_starts_with($name, $prefix)) {
                continue;
            }
            $target = $root.'/'.substr($name, strlen($prefix));
            $mkdir(dirname($target));
            if (is_link($target)) {
                throw new RuntimeException('Destino inseguro.');
            }
            if (file_exists($target)) {
                if (! hash_equals($hash, hash_file('sha256', $target))) {
                    throw new RuntimeException('Archivo existente distinto; no se sobrescribe: '.substr($name, strlen($prefix)));
                }
            } else {
                $stream = fopen($target, 'xb');
                if ($stream === false || fwrite($stream, file_get_contents($bundle.'/'.$name)) !== filesize($bundle.'/'.$name)) {
                    throw new RuntimeException('Copia incompleta.');
                }
                fclose($stream);
                chmod($target, 0600);
            }
        }
        foreach (['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs',
            'storage/app/private/noticias', 'storage/app/private/sitio', 'storage/app/private/hacienda', 'storage/app/private/proveedores',
            'storage/app/private/ensenada-site-scratch', 'storage/app/private/ensenada-news-input'] as $path) {
            $mkdir($root.'/'.$path);
        }
        echo "Instalación privada verificada. No se habilitó tráfico.\n";
        exit;
    }
    if (in_array($op, ['angular', 'rollback-angular'], true)) {
        if (realpath($public) !== $public || is_link($public.'/index.html') || file_exists($root.'/.iwinds-production-enabled')) {
            throw new RuntimeException('Directorio inválido o backend habilitado; cerrar antes del cambio Angular.');
        }
        $release = json_decode(file_get_contents($bundle.'/angular/release.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($release['required'] as $name => $hash) {
            if (is_link($public.'/'.$name) || ! is_file($public.'/'.$name) || ! hash_equals($hash, hash_file('sha256', $public.'/'.$name))) {
                throw new RuntimeException('Angular V13 instalado no coincide con los componentes requeridos.');
            }
        }
        $index = $public.'/index.html';
        $current = hash_file('sha256', $index);
        $backup = $root.'/deploy-state/angular-index.before';
        if ($op === 'angular') {
            if ($current === $release['files']['index.html']) {
                echo "Angular ya actualizado.\n";
                exit;
            }
            if ($current !== $release['previous_index']) {
                throw new RuntimeException('Index V13 diferente; revisar antes de sustituir.');
            }
            $mkdir(dirname($backup));
            if (is_link($backup) || (file_exists($backup) && hash_file('sha256', $backup) !== $current)) {
                throw new RuntimeException('Respaldo Angular anterior diferente.');
            }
            if (! file_exists($backup)) {
                $stream = fopen($backup, 'xb');
                fwrite($stream, file_get_contents($index));
                fclose($stream);
            }
            foreach ($release['files'] as $name => $hash) {
                if ($name === 'index.html') {
                    continue;
                }
                $path = $public.'/'.$name;
                if (is_link($path) || (file_exists($path) && hash_file('sha256', $path) !== $hash)) {
                    throw new RuntimeException('Bundle existente diferente.');
                }
                if (! file_exists($path)) {
                    $stream = fopen($path, 'xb');
                    fwrite($stream, file_get_contents($bundle.'/angular/'.$name));
                    fclose($stream);
                    chmod($path, 0644);
                }
            }
            $bytes = file_get_contents($bundle.'/angular/index.html');
        } else {
            if ($current !== $release['files']['index.html'] || ! is_file($backup) || is_link($backup)
                || hash_file('sha256', $backup) !== $release['previous_index']) {
                throw new RuntimeException('Reversión Angular requiere estado conocido.');
            }
            $bytes = file_get_contents($backup);
        }
        $temp = $public.'/.index-'.bin2hex(random_bytes(8));
        $stream = fopen($temp, 'xb');
        fwrite($stream, $bytes);
        fflush($stream);
        fsync($stream);
        fclose($stream);
        chmod($temp, 0644);
        if (hash_file('sha256', $index) !== $current || ! rename($temp, $index)) {
            throw new RuntimeException('Index cambió durante la operación.');
        }
        echo "Index actualizado atómicamente; bundles anteriores, estilos y assets conservados.\n";
        exit;
    }
    if ($op === 'web') {
        if (realpath($public) !== $public || ! is_file($public.'/index.html')) {
            throw new RuntimeException('Angular existente requerido.');
        }
        $index = file_get_contents($public.'/index.html');
        if (! preg_match('~<base\s+href=["\x27]/["\x27]~i', $index)) {
            throw new RuntimeException('Base Angular incompatible.');
        }
        $release = json_decode(file_get_contents($bundle.'/angular/release.json'), true, flags: JSON_THROW_ON_ERROR);
        $reviewed = [...$release['required'], ...$release['files']];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Enlace en Angular no permitido.');
            }
            if (in_array(strtolower($entry->getExtension()), ['js', 'html', 'json'], true)) {
                $contents = file_get_contents($entry->getPathname());
                $name = $entry->getFilename();
                // Angular's reviewed auth interceptor uses localhost solely as its SSR URL-parser fallback.
                $knownMain = preg_match('/\Amain-[A-Z0-9]+\.js\z/D', $name) === 1
                    && isset($reviewed[$name]) && hash_equals($reviewed[$name], hash('sha256', $contents));
                if (preg_match('~https?://(?:prueba\.ensenada\.gov\.ar|127\.0\.0\.1)~i', $contents)
                    || (! $knownMain && preg_match('~https?://localhost~i', $contents))) {
                    throw new RuntimeException('Referencia a staging/local sin revisión en Angular.');
                }
            }
        }
        $back = $root.'/deploy-state';
        $mkdir($back);
        $dest = $public.'/.htaccess';
        $candidate = file_get_contents($bundle.'/apache/web.htaccess');
        if (is_link($dest) || ! is_file($dest)) {
            throw new RuntimeException('.htaccess previo requerido.');
        }
        $old = file_get_contents($dest);
        if ($old !== $candidate) {
            if (trim($old) !== 'Require all denied') {
                throw new RuntimeException('Protección previa inesperada; revisar manualmente.');
            }
            if (! file_exists($back.'/web.htaccess.before')) {
                file_put_contents($back.'/web.htaccess.before', $old, LOCK_EX);
            }
            $temp = $public.'/.htaccess.production-candidate';
            if (file_exists($temp) || is_link($temp)) {
                throw new RuntimeException('Temporal existente.');
            }
            file_put_contents($temp, $candidate, LOCK_EX);
            chmod($temp, 0644);
            if (! rename($temp, $dest)) {
                throw new RuntimeException('No se pudo instalar protección web.');
            }
        }
        $bridge = $public.'/ensenada-production.php';
        $bytes = file_get_contents($bundle.'/apache/ensenada-production.php');
        if (is_link($bridge) || (file_exists($bridge) && file_get_contents($bridge) !== $bytes)) {
            throw new RuntimeException('Puente existente diferente.');
        }
        if (! file_exists($bridge)) {
            $handle = fopen($bridge, 'xb');
            fwrite($handle, $bytes);
            fclose($handle);
            chmod($bridge, 0644);
        }
        echo "Puente y protección instalados. Angular intacto. El .htaccess principal no cambió.\n";
        exit;
    }
    if ($op === 'rollback-web') {
        $dest = $public.'/.htaccess';
        $backup = $root.'/deploy-state/web.htaccess.before';
        if (is_link($dest) || ! is_file($backup) || file_get_contents($dest) !== file_get_contents($bundle.'/apache/web.htaccess')) {
            throw new RuntimeException('Reversión requiere estado conocido.');
        }
        $temp = $public.'/.htaccess.rollback';
        if (file_exists($temp) || is_link($temp)) {
            throw new RuntimeException('Temporal existente.');
        }
        file_put_contents($temp, file_get_contents($backup));
        chmod($temp, 0644);
        rename($temp, $dest);
        echo "Protección anterior restaurada. Backend privado conservado.\n";
        exit;
    }
    throw new RuntimeException('Uso: install.php private|web|rollback-web');
} catch (Throwable $error) {
    fwrite(STDERR, 'BLOQUEADO: '.$error->getMessage()."\n");
    exit(1);
}
