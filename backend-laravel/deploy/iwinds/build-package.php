<?php

$root = '/bundle';
$private = $root.'/private/backend-laravel';
$installed = json_decode(file_get_contents($private.'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
$lock = json_decode(file_get_contents($private.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$packages = array_column($installed['packages'], 'version', 'name');
$locked = array_column($lock['packages'], 'version', 'name');
ksort($packages);
ksort($locked);
if ($installed['dev'] !== false || $packages !== $locked) {
    throw new RuntimeException('El vendor no coincide con las dependencias de producción del lock.');
}

$zipPath = '/result/web-ensenada-iwinds-staging.zip';
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('No se pudo crear el paquete.');
}
$hashes = [];
$tree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($tree as $entry) {
    $name = substr($entry->getPathname(), strlen($root) + 1);
    if ($entry->isLink() || preg_match('~(?:^|/)(?:\.git|\.env(?:\..*)?|\.iwinds-api-enabled|auth\.json)(?:/|$)|\.(?:log|sqlite3?|pem|key)$~iD', $name)) {
        throw new RuntimeException('Entrada no permitida en el paquete: '.$name);
    }
    if ($entry->isDir()) {
        if (! $zip->addEmptyDir($name)) {
            throw new RuntimeException('No se pudo agregar un directorio.');
        }
    } else {
        if (! $entry->isFile() || ! $zip->addFile($entry->getPathname(), $name)) {
            throw new RuntimeException('No se pudo agregar un archivo regular.');
        }
        $hashes[$name] = hash_file('sha256', $entry->getPathname());
    }
}
ksort($hashes);
if (! $zip->addFromString('MANIFEST.json', json_encode($hashes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n") || ! $zip->close()) {
    throw new RuntimeException('No se pudo cerrar el paquete.');
}
if ($zip->open($zipPath, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
    throw new RuntimeException('Paquete inconsistente.');
}
foreach ($hashes as $name => $expected) {
    $stream = $zip->getStream($name);
    $ctx = hash_init('sha256');
    if ($stream === false || hash_update_stream($ctx, $stream) === false || hash_final($ctx) !== $expected) {
        throw new RuntimeException('El contenido del paquete no coincide con el manifiesto.');
    }
    fclose($stream);
}
$zip->close();
file_put_contents($zipPath.'.sha256', hash_file('sha256', $zipPath).'  '.basename($zipPath)."\n");
echo count($packages).' paquetes de producción y '.count($hashes).' archivos verificados.'.PHP_EOL;
