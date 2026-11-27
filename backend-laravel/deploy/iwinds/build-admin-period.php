<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 1 || ! extension_loaded('zip')) {
    exit(1);
}

$root = dirname(__DIR__, 2);
$output = $root.'/output/iwinds-admin-period-release';
$name = 'web-ensenada-iwinds-admin-period.zip';
$previous = [
    'app/Support/IwindsPublicProbe.php' => ['photos', '07d93d5bace02e87c3d82b8ae82cd787ea438b3e4e93818d491e16f58e35f4e5'],
    'deploy/iwinds/provision-staging.php' => ['closure', 'ab45d2d4b2926f62dcca3ae0472fe93ef2c0fb3910fe2900c6daff3be1c2378c'],
];
$files = [
    'GUIA-IWINDS.md' => __DIR__.'/admin-period/GUIA-IWINDS.md',
    'install.sh' => __DIR__.'/admin-period/install.sh',
    'common.sh' => __DIR__.'/final/common.sh',
];
$baseline = '';
foreach ($previous as $path => [$version, $hash]) {
    $original = "$root/output/iwinds-$version-release/web-ensenada-iwinds-$version.zip";
    $archive = new ZipArchive;
    if (! is_file($original) || hash_file('sha256', $original) !== $hash || $archive->open($original, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException('Falta el paquete de referencia: '.$version);
    }
    $originalBytes = $archive->getFromName('private/'.$path);
    $archive->close();
    if ($originalBytes === false) {
        throw new RuntimeException('Falta el archivo de referencia: '.$path);
    }
    $baseline .= hash('sha256', $originalBytes).'  '.$path."\n";
    $files['private/'.$path] = $root.'/'.$path;
}

if (! is_dir($output) && ! mkdir($output, 0700, true)) {
    exit(1);
}
$temp = tempnam($output, '.admin-period-');
if ($temp === false) {
    exit(1);
}
try {
    $zip = new ZipArchive;
    if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo crear el paquete.');
    }
    ksort($files, SORT_STRING);
    $components = ['BASELINE.sha256' => $baseline];
    foreach ($files as $path => $source) {
        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new RuntimeException('Falta el archivo: '.$path);
        }
        $components[$path] = str_replace("\r\n", "\n", $contents);
    }
    $sums = '';
    foreach ($components as $path => $contents) {
        if (! $zip->addFromString($path, $contents)) {
            throw new RuntimeException('No se pudo incorporar '.$path);
        }
        $sums .= hash('sha256', $contents).'  '.$path."\n";
    }
    if (! $zip->addFromString('SHA256SUMS', $sums)) {
        throw new RuntimeException('No se pudo escribir el manifiesto.');
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
        throw new RuntimeException('No se pudo guardar el SHA-256.');
    }
    echo $sum;
} finally {
    if (is_file($temp)) {
        unlink($temp);
    }
}
