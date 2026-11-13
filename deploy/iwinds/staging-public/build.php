<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 2 || ! extension_loaded('zip')) {
    exit("Uso: php build.php DIRECTORIO_DE_SALIDA\n");
}
$output = $argv[1];
$name = 'web-ensenada-iwinds-staging-publico.zip';
if (! is_dir($output) && ! mkdir($output, 0700, true)) {
    throw new RuntimeException('No se pudo crear el directorio de salida.');
}
$files = ['BASELINE.sha256', 'GUIA-IWINDS.md', 'install.sh', 'apache/htaccess.fragment',
    'private/app/Support/IwindsPublishedImages.php'];
foreach (file(__DIR__.'/BASELINE.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $files[] = explode('  ', $line, 2)[1];
}
sort($files);
$contents = [];
foreach ($files as $file) {
    $bytes = file_get_contents(__DIR__.'/'.$file);
    if ($bytes === false) {
        throw new RuntimeException('Falta el componente '.$file);
    }
    $contents[$file] = str_replace("\r\n", "\n", $bytes);
}
$contents['SHA256SUMS'] = '';
foreach ($files as $file) {
    $contents['SHA256SUMS'] .= hash('sha256', $contents[$file]).'  '.$file."\n";
}
$temporary = tempnam($output, '.staging-publico-');
if ($temporary === false) {
    throw new RuntimeException('No se pudo preparar el ZIP.');
}
try {
    $zip = new ZipArchive;
    if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo abrir el ZIP.');
    }
    foreach ($contents as $file => $bytes) {
        if (! $zip->addFromString($file, $bytes) || ! $zip->setMtimeName($file, 1577836800)
            || ! $zip->setExternalAttributesName($file, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
            throw new RuntimeException('No se pudo guardar '.$file);
        }
    }
    if (! $zip->close() || ! rename($temporary, "$output/$name")) {
        throw new RuntimeException('No se pudo publicar el ZIP.');
    }
    $checksum = hash_file('sha256', "$output/$name").'  '.$name."\n";
    if (file_put_contents("$output/$name.sha256", $checksum) !== strlen($checksum)) {
        throw new RuntimeException('No se pudo guardar el SHA-256.');
    }
    echo $checksum;
} finally {
    if (is_file($temporary)) {
        unlink($temporary);
    }
}
