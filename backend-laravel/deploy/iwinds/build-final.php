<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 1 || ! extension_loaded('zip')) {
    exit(1);
}

$root = dirname(__DIR__, 2);
$output = $root.'/output/iwinds-final-release';
$name = 'web-ensenada-iwinds-final.zip';
$photosPath = $root.'/output/iwinds-photos-release/web-ensenada-iwinds-photos.zip';
$photosHash = '07d93d5bace02e87c3d82b8ae82cd787ea438b3e4e93818d491e16f58e35f4e5';
if (! is_file($photosPath) || hash_file('sha256', $photosPath) !== $photosHash) {
    throw new RuntimeException('Falta el paquete de fotos validado o su SHA-256 difiere.');
}

$photos = new ZipArchive;
if ($photos->open($photosPath, ZipArchive::RDONLY) !== true) {
    throw new RuntimeException('No se pudo abrir el paquete de fotos.');
}

$components = [];
foreach (['common.sh', 'install.sh', 'rollback.sh', 'routes.php', 'backup.sh', 'accept.sh', 'accept.php', 'web-limits.php',
    'revoke-database-dml.sql', 'GUIA-IWINDS.md'] as $file) {
    $components[$file] = file_get_contents(__DIR__.'/final/'.$file);
}
$components['schema-grants.sql'] = file_get_contents(__DIR__.'/schema-grants.sql');
for ($index = 0; $index < $photos->numFiles; $index++) {
    $file = $photos->getNameIndex($index);
    $components['photos/'.$file] = $photos->getFromIndex($index);
}
$photos->close();
ksort($components, SORT_STRING);

if (! is_dir($output) && ! mkdir($output, 0700, true)) {
    exit(1);
}
$temp = tempnam($output, '.final-');
if ($temp === false) {
    exit(1);
}
try {
    $zip = new ZipArchive;
    if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo crear el paquete.');
    }
    $sums = [];
    foreach ($components as $file => $bytes) {
        if (is_string($bytes) && ! str_starts_with($file, 'photos/')) {
            $bytes = str_replace("\r\n", "\n", $bytes);
        }
        if (! is_string($bytes) || ! $zip->addFromString($file, $bytes)) {
            throw new RuntimeException('No se pudo incorporar '.$file);
        }
        $sums[] = hash('sha256', $bytes).'  '.$file;
    }
    if (! $zip->addFromString('SHA256SUMS', implode("\n", $sums)."\n")) {
        throw new RuntimeException('No se pudo escribir el inventario.');
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        if (! $zip->setMtimeIndex($index, 1577836800) || ! $zip->setExternalAttributesIndex($index, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
            throw new RuntimeException('No se pudieron fijar los metadatos del paquete.');
        }
    }
    if (! $zip->close() || ! rename($temp, "$output/$name")) {
        throw new RuntimeException('No se pudo publicar el paquete local.');
    }
    $sum = hash_file('sha256', "$output/$name").'  '.$name."\n";
    if (file_put_contents("$output/$name.sha256", $sum, LOCK_EX) !== strlen($sum)) {
        throw new RuntimeException('No se pudo guardar el SHA-256 del paquete.');
    }
    echo $sum;
} finally {
    if (is_file($temp)) {
        unlink($temp);
    }
}
