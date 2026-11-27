<?php

// Runs without Laravel, Composer, credentials or a database connection.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__, 2);
$lock = json_decode(file_get_contents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$required = [...array_keys($lock['platform']), 'ext-gd'];
foreach ($lock['packages'] as $package) {
    $required = [...$required, ...array_keys($package['require'] ?? [])];
}
$extensions = array_unique(array_filter($required, fn (string $name): bool => str_starts_with($name, 'ext-')));
sort($extensions);

$failed = false;
$check = static function (bool $ok, string $label) use (&$failed): void {
    echo ($ok ? 'OK    ' : 'FALTA ').$label.PHP_EOL;
    $failed = $failed || ! $ok;
};
$check(PHP_VERSION_ID >= 80300 && PHP_VERSION_ID < 80400, 'PHP 8.3 ('.PHP_VERSION.')');
foreach ($extensions as $extension) {
    $check(extension_loaded(substr($extension, 4)), $extension);
}
$check(function_exists('imagetypes') && (imagetypes() & (IMG_JPG | IMG_PNG)) === (IMG_JPG | IMG_PNG), 'GD con JPEG y PNG');
foreach (['link', 'fsync', 'proc_open', 'flock', 'set_time_limit'] as $function) {
    $check(function_exists($function), $function);
}
$check(is_executable('/usr/bin/prlimit'), '/usr/bin/prlimit');
$check(is_executable('/usr/bin/convert'), '/usr/bin/convert');
$policy = __DIR__.'/policy.xml';
if (is_file($policy) && is_executable('/usr/bin/prlimit') && is_executable('/usr/bin/convert') && function_exists('proc_open')) {
    $process = proc_open(['/usr/bin/prlimit', '--as=536870912', '--cpu=5', '--', '/usr/bin/convert', '-list', 'policy'],
        [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'a']], $pipes, __DIR__, ['MAGICK_CONFIGURE_PATH' => __DIR__, 'LC_ALL' => 'C']);
    if (is_resource($process)) {
        $listing = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $check(proc_close($process) === 0 && str_contains($listing, $policy), 'ImageMagick carga la política privada del paquete');
    } else {
        $check(false, 'ImageMagick carga la política privada del paquete');
    }
} else {
    $check(false, 'ImageMagick carga la política privada del paquete');
}
$memory = ini_parse_quantity((string) ini_get('memory_limit'));
$check($memory === -1 || $memory >= 256 * 1024 * 1024, 'memory_limit para el perfil medido (256 MiB)');
$check(ini_parse_quantity((string) ini_get('upload_max_filesize')) >= 30 * 1024 * 1024, 'upload_max_filesize para el contrato institucional (30 MiB)');
$check(ini_parse_quantity((string) ini_get('post_max_size')) > 30 * 1024 * 1024, 'post_max_size con margen multipart');

exit($failed ? 1 : 0);
