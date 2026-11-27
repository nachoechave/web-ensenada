<?php

require '/opt/runtimevendor/autoload.php';
use Symfony\Component\Process\Process;

$zip = new ZipArchive;
if ($zip->open($argv[1]) !== true || ! $zip->extractTo('/tmp/package')) {
    exit(1);
}$zip->close();
$install = static function (string $op): Process {
    $p = new Process([PHP_BINARY, '/tmp/package/install.php', $op]);
    $p->run();

    return $p;
};
$assert = static function (bool $ok, string $label): void {
    if (! $ok) {
        throw new RuntimeException($label);
    }echo "OK $label\n";
};
$assert($install('private')->isSuccessful(), 'instalación privada');
$assert($install('private')->isSuccessful(), 'repetición privada idempotente');
$root = '/home/ensenada/backend-laravel-produccion';
$installed = json_decode(file_get_contents($root.'/vendor/composer/installed.json'), true);
$assert($installed['dev'] === false && count($installed['packages']) === 76, '76 dependencias sin desarrollo');
$assert(! file_exists($root.'/.env') && ! file_exists($root.'/.iwinds-production-enabled') && ! file_exists($root.'/bootstrap/cache/config.php'), 'sin secretos cachés ni habilitación');
$file = $root.'/routes/api.php';
$before = file_get_contents($file);
file_put_contents($file, $before."\n// changed\n");
$assert(! $install('private')->isSuccessful() && file_get_contents($file) === $before."\n// changed\n", 'rechaza sobrescritura');
file_put_contents($file, $before);
$public = '/home/ensenada/public_html/web-produccion';
mkdir($public, 0755, true);
copy('/source/output/v13-index.html', $public.'/index.html');
$release = json_decode(file_get_contents('/tmp/package/angular/release.json'), true);
foreach ($release['required'] as $name => $hash) {
    copy('/source/output/angular-test/browser/'.$name, $public.'/'.$name);
}
file_put_contents($public.'/.htaccess', "Require all denied\n");
$angular = hash_file('sha256', $public.'/index.html');
$assert($install('web')->isSuccessful(), 'instalación web');
$assert($install('web')->isSuccessful(), 'repetición web idempotente');
$assert(hash_file('sha256', $public.'/index.html') === $angular, 'Angular preservado');
$assert($install('rollback-web')->isSuccessful() && file_get_contents($public.'/.htaccess') === "Require all denied\n", 'rollback de protección');
$assert(hash_file('sha256', $public.'/index.html') === $angular && is_dir($root.'/vendor'), 'reversión conserva archivos');
$assert(! (new Process([PHP_BINARY, $root.'/deploy/production/init-env.php']))->run(), 'inicialización privada de claves');
$envHash = hash_file('sha256', $root.'/.env');
$p = new Process([PHP_BINARY, $root.'/deploy/production/init-env.php']);
$p->run();
$assert(! $p->isSuccessful() && hash_file('sha256', $root.'/.env') === $envHash, 'no reemplaza claves existentes');
$assert($install('angular')->isSuccessful(), 'actualización Angular de imágenes');
$assert($install('angular')->isSuccessful(), 'actualización Angular idempotente');
$assert($install('rollback-angular')->isSuccessful() && hash_file('sha256',$public.'/index.html') === $angular, 'rollback Angular exacto');
echo "OK 15 verificaciones del ZIP real\n";
