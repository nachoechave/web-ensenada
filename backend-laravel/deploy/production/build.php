<?php

declare(strict_types=1);
// Run after composer audit/check-platform-reqs. Inputs are source + a fresh --no-dev vendor.
[$script,$source,$dependencies,$output] = $argv;
$installed = json_decode(file_get_contents($dependencies.'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
$lock = json_decode(file_get_contents($source.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$actual = array_column($installed['packages'], 'version', 'name');
$expected = array_column($lock['packages'], 'version', 'name');
ksort($actual);
ksort($expected);
if ($installed['dev'] !== false || $actual !== $expected) {
    throw new RuntimeException('Vendor de producción no coincide con el lock.');
}
$vendorRoots = ['autoload.php', 'bin', 'composer', ...array_unique(array_map(static fn (string $name): string => explode('/', $name, 2)[0], array_keys($expected)))];
foreach (new DirectoryIterator($dependencies.'/vendor') as $entry) {
    if (! $entry->isDot() && ! in_array($entry->getFilename(), $vendorRoots, true)) {
        throw new RuntimeException('Vendor contiene archivos o paquetes adicionales.');
    }
}
$files = [];
$add = static function (string $path, string $name) use (&$files): void {
    if (is_link($path) || ! is_file($path) || preg_match('~(?:^|/)(?:\.env(?:\..*)?|\.git|auth\.json|\.iwinds-[^/]*)(?:/|$)|\.(?:sqlite3?|log|key|pem)$~i', $name)) {
        throw new RuntimeException('Archivo no permitido: '.$name);
    }
    $files[$name] = $path;
};
$tree = static function (string $path, string $prefix) use ($add): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $entry) {
        $relative = substr($entry->getPathname(), strlen($path) + 1);
        if (preg_match('~(?:^|/)(?:tests?|Tests|docs?|examples?|\.github)(?:/|$)~', $relative)) {
            continue;
        }
        $add($entry->getPathname(), $prefix.'/'.$relative);
    }
};
$private = 'private/backend-laravel-produccion';
foreach (['app', 'config', 'routes'] as $directory) {
    $tree($source.'/'.$directory, $private.'/'.$directory);
}
$tree($dependencies.'/vendor', $private.'/vendor');
foreach (['artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php', 'bootstrap/providers.php', 'public/index.php',
    'deploy/iwinds/schema-grants.sql', 'deploy/iwinds/schema-check.php', 'deploy/iwinds/platform.php'] as $file) {
    $add($source.'/'.$file, $private.'/'.$file);
}
$add($source.'/docker/site-image-policy.xml', $private.'/deploy/iwinds/policy.xml');
foreach (new DirectoryIterator($source.'/deploy/production') as $entry) {
    if (! $entry->isFile() || $entry->getFilename() === 'build.php') {
        continue;
    }
    $name = $entry->getFilename();
    $add($entry->getPathname(), $private.'/deploy/production/'.$name);
    if (in_array($name, ['ensenada-production.php', 'web.htaccess', 'root.fragment'], true)) {
        $add($entry->getPathname(), 'apache/'.$name);
    } elseif ($name === 'install.php') {
        $add($entry->getPathname(), 'install.php');
    } elseif ($name === 'README.md') {
        $add($entry->getPathname(), 'README.md');
    }
}
$angular = json_decode(file_get_contents($source.'/deploy/production/angular-release.json'), true, flags: JSON_THROW_ON_ERROR);
$add($source.'/deploy/production/angular-release.json', 'angular/release.json');
foreach ($angular['files'] as $name => $hash) {
    $path = $source.'/output/angular-production/browser/'.($name === 'index.html' ? 'index.csr.html' : $name);
    if (! hash_equals($hash, hash_file('sha256', $path))) {
        throw new RuntimeException('Build Angular diferente del manifiesto de versión.');
    }
    $add($path, 'angular/'.$name);
}
ksort($files);
$manifest = [];
$zip = new ZipArchive;
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Salida ya existente o no escribible.');
}
foreach ($files as $name => $path) {
    $zip->addFile($path, $name);
    $zip->setMtimeName($name, 315532800);
    $manifest[$name] = hash_file('sha256', $path);
}
$zip->addFromString('MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
$zip->setMtimeName('MANIFEST.json', 315532800);
$zip->close();
if ($zip->open($output, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
    throw new RuntimeException('ZIP inválido.');
}
foreach ($manifest as $name => $hash) {
    $stream = $zip->getStream($name);
    $ctx = hash_init('sha256');
    hash_update_stream($ctx, $stream);
    fclose($stream);
    if (! hash_equals($hash, hash_final($ctx))) {
        throw new RuntimeException('SHA de componente inválido.');
    }
}
$zip->close();
file_put_contents($output.'.sha256', hash_file('sha256', $output).'  '.basename($output)."\n");
echo count($expected).' dependencias de producción; '.count($manifest)." componentes verificados.\n";
