<?php

// Build locally with PHP 8.3 and ext-zip from the reviewed workspace.
if (PHP_SAPI !== 'cli' || ! extension_loaded('zip')) {
    exit(1);
}

$root = dirname(__DIR__, 2);
$output = $root.'/output/iwinds-closure-release';
$filename = 'web-ensenada-iwinds-closure.zip';
$files = [
    'CIERRE-IWINDS.md' => 'deploy/iwinds/CIERRE-IWINDS.md',
    'apache/ensenada-api.php' => 'deploy/iwinds/ensenada-api.php',
    'apache/htaccess.fragment' => 'deploy/iwinds/htaccess.fragment',
    'private/app/Auth/JwtSessions.php' => 'app/Auth/JwtSessions.php',
    'private/app/Content/SiteImageEncoder.php' => 'app/Content/SiteImageEncoder.php',
    'private/app/Http/Controllers/AuthController.php' => 'app/Http/Controllers/AuthController.php',
    'private/app/Support/IwindsPublicProbe.php' => 'app/Support/IwindsPublicProbe.php',
    'private/app/Support/IwindsStaging.php' => 'app/Support/IwindsStaging.php',
    'private/deploy/iwinds/provision-staging.php' => 'deploy/iwinds/provision-staging.php',
    'private/public/index.php' => 'public/index.php',
];
if (! is_dir($output) && ! mkdir($output, 0700, true)) {
    exit(1);
}

$temporary = tempnam($output, '.closure-');
if ($temporary === false) {
    exit(1);
}
try {
    $zip = new ZipArchive;
    if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo preparar el ZIP.');
    }
    $sums = [];
    foreach ($files as $destination => $source) {
        $contents = file_get_contents($root.'/'.$source);
        if ($contents === false || ! $zip->addFromString($destination, $contents)) {
            throw new RuntimeException('No se pudo incluir '.$source);
        }
        $sums[] = hash('sha256', $contents).'  '.$destination;
    }
    if (! $zip->addFromString('FILES', implode("\n", array_keys($files))."\n")
        || ! $zip->addFromString('SHA256SUMS', implode("\n", $sums)."\n")) {
        throw new RuntimeException('No se pudo escribir el inventario.');
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        if (! $zip->setMtimeIndex($index, 1577836800)) {
            throw new RuntimeException('No se pudo fijar la fecha del ZIP.');
        }
    }
    if (! $zip->close() || ! rename($temporary, $output.'/'.$filename)) {
        throw new RuntimeException('No se pudo publicar el ZIP local.');
    }
    $checksum = hash_file('sha256', $output.'/'.$filename).'  '.$filename."\n";
    if (file_put_contents($output.'/'.$filename.'.sha256', $checksum, LOCK_EX) !== strlen($checksum)) {
        throw new RuntimeException('No se pudo escribir el SHA-256 externo.');
    }
    echo $checksum;
} finally {
    if (is_file($temporary)) {
        unlink($temporary);
    }
}
