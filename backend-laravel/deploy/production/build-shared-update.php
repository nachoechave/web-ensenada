<?php

declare(strict_types=1);
[$self,$source,$baselineZip,$output] = $argv;
if (hash_file('sha256', $baselineZip) !== '3d9399393618022b89371cf6a98c08476f4406ea1c92bdc7e4915bc29624d68b') {
    throw new RuntimeException('Paquete anterior diferente.');
}
$base = new ZipArchive;
$base->open($baselineZip);
$prior = json_decode($base->getFromName('MANIFEST.json'), true, flags: JSON_THROW_ON_ERROR);
$base->close();
$prefix = 'private/backend-laravel-produccion/';
$baseline = [];
foreach ($prior as $name => $hash) {
    if (str_starts_with($name, $prefix)) {
        $baseline[substr($name, strlen($prefix))] = $hash;
    }
}
$names = ['app/Support/IwindsProduction.php', 'app/Support/ProductionPermissions.php', 'app/Support/IwindsStaging.php', 'app/Support/IwindsPublicProbe.php',
    'app/Support/IwindsPublishedImages.php', 'app/Support/SharedDatabaseLease.php', 'app/Support/SharedDatabaseAudit.php', 'app/Support/SharedDatabaseTransfer.php',
    'deploy/production/control.php', 'deploy/production/handover.php', 'deploy/production/ensenada-production.php', 'deploy/production/recovery-bridge.php',
    'deploy/production/environment.example', 'deploy/production/grants-production.sql', 'deploy/production/schema-init-production.sql', 'deploy/production/provision.php',
    'deploy/production/schema-reference.json', 'deploy/production/backup.sh', 'deploy/production/README.md', 'deploy/production/SHARED-DATABASE.md',
    'deploy/production/verify-restore.php', 'deploy/production/restore-local.ps1', 'deploy/production/validate-backup-config.php', 'deploy/production/acceptance.php', 'deploy/production/VALIDATION.md'];
$files = [];
$changes = [];
foreach ($names as $name) {
    $path = $source.'/'.$name;
    if (! is_file($path) || is_link($path)) {
        throw new RuntimeException('Falta componente: '.$name);
    }$sha = hash_file('sha256', $path);
    $changes[$name] = ['old' => $baseline[$name] ?? null, 'new' => $sha];
    $files['private/'.$name] = $path;
}
$update = ['id' => hash('sha256', json_encode($changes, JSON_THROW_ON_ERROR)), 'baseline_sha256' => hash_file('sha256', $baselineZip), 'baseline' => $baseline, 'changes' => $changes,
    'web_baseline' => ['ensenada-production.php' => $prior['apache/ensenada-production.php'], 'web.htaccess' => $prior['apache/web.htaccess']]];
$files['update.php'] = $source.'/deploy/production/update-shared.php';
$files['README.md'] = $source.'/deploy/production/SHARED-DATABASE.md';
$files['apache/ensenada-production.php'] = $source.'/deploy/production/ensenada-production.php';
$files['apache/web.htaccess'] = $source.'/deploy/production/web.htaccess';
ksort($files);
$manifest = [];
$zip = new ZipArchive;
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Salida existente.');
}
foreach ($files as $name => $path) {
    $zip->addFile($path, $name);
    $zip->setMtimeName($name, 315532800);
    $manifest[$name] = hash_file('sha256', $path);
}
$bytes = json_encode($update, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
$zip->addFromString('UPDATE.json', $bytes);
$zip->setMtimeName('UPDATE.json', 315532800);
$manifest['UPDATE.json'] = hash('sha256', $bytes);
$zip->addFromString('MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
$zip->setMtimeName('MANIFEST.json', 315532800);
$zip->close();
$zip->open($output, ZipArchive::RDONLY | ZipArchive::CHECKCONS);
foreach ($manifest as $name => $sha) {
    if (hash('sha256', $zip->getFromName($name)) !== $sha) {
        throw new RuntimeException('Integridad ZIP fallida.');
    }
}$zip->close();
file_put_contents($output.'.sha256', hash_file('sha256', $output).'  '.basename($output)."\n");
echo count($changes).' componentes privados; sin vendor, .env, datos ni cambios Angular.\n';
