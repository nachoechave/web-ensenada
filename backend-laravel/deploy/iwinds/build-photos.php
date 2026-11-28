<?php

// Run locally with PHP 8.3 and ext-zip, passing the Angular browser build directory.
if (PHP_SAPI !== 'cli' || ! extension_loaded('zip') || $argc !== 2) {
    exit(1);
}

$root = dirname(__DIR__, 2);
$browser = rtrim($argv[1], '/\\');
$output = $root.'/output/iwinds-photos-release';
$name = 'web-ensenada-iwinds-photos.zip';
$components = [
    'IMAGENES-IWINDS.md' => $root.'/deploy/iwinds/IMAGENES-IWINDS.md',
    'angular/index.html' => $browser.'/index.csr.html',
    'private/deploy/iwinds/policy.xml' => $root.'/docker/site-image-policy.xml',
];
foreach ([
    'app/Content/SiteImageEncoder.php', 'app/Content/SiteImageStore.php',
    'app/Http/Controllers/NewsImageController.php', 'app/Http/Controllers/SiteImageController.php',
    'app/Images/LocalImageStore.php', 'app/News/NewsDto.php', 'app/News/NewsImageStore.php',
    'app/Support/IwindsPublicProbe.php',
] as $path) {
    $components['private/'.$path] = $root.'/'.$path;
}
foreach (glob($browser.'/*.{js,css}', GLOB_BRACE) ?: [] as $file) {
    if (preg_match('/\A[a-zA-Z0-9_-]+-[A-Z0-9]{8}\.(?:js|css)\z/', basename($file)) === 1) {
        $components['angular/'.basename($file)] = $file;
    }
}
$index = file_get_contents($components['angular/index.html']);
if ($index === false || ! preg_match_all('/\b(?:src|href)="([^"]+\.(?:js|css))"/', $index, $references)) {
    throw new RuntimeException('Falta el punto de entrada Angular compilado.');
}
foreach ($references[1] as $reference) {
    if (! isset($components['angular/'.$reference])) {
        throw new RuntimeException('Falta el recurso de Angular '.$reference);
    }
}
ksort($components);

if (! is_dir($output) && ! mkdir($output, 0700, true)) {
    exit(1);
}
$temp = tempnam($output, '.photos-');
if ($temp === false) {
    exit(1);
}

try {
    $zip = new ZipArchive;
    if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo preparar el ZIP.');
    }
    $sums = [];
    foreach ($components as $destination => $source) {
        $bytes = file_get_contents($source);
        if ($bytes === false || ! $zip->addFromString($destination, $bytes)) {
            throw new RuntimeException('No se pudo incluir '.$destination);
        }
        $sums[] = hash('sha256', $bytes).'  '.$destination;
    }
    if (! $zip->addFromString('FILES', implode("\n", array_keys($components))."\n")
        || ! $zip->addFromString('SHA256SUMS', implode("\n", $sums)."\n")) {
        throw new RuntimeException('No se pudo escribir el inventario.');
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        if (! $zip->setMtimeIndex($index, 1577836800)) {
            throw new RuntimeException('No se pudo fijar la fecha del ZIP.');
        }
    }
    if (! $zip->close() || ! rename($temp, $output.'/'.$name)) {
        throw new RuntimeException('No se pudo publicar el ZIP local.');
    }
    $hash = hash_file('sha256', $output.'/'.$name).'  '.$name."\n";
    if (file_put_contents($output.'/'.$name.'.sha256', $hash, LOCK_EX) !== strlen($hash)) {
        throw new RuntimeException('No se pudo escribir el SHA-256 externo.');
    }
    echo $hash;
} finally {
    if (is_file($temp)) {
        unlink($temp);
    }
}
