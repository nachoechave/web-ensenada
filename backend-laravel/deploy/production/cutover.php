<?php

// Generates a candidate and supports compare-and-swap install/restore. Never edits managed blocks.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);
function fail(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(1);
}
function regular(string $path): string
{
    if (is_link($path) || ! is_file($path) || ! is_readable($path)) {
        fail('Archivo regular legible requerido.');
    }

    return file_get_contents($path);
}
function replaceAtomic(string $path, string $content, string $expected, string $operation, ?array $receipt): void
{
    if (realpath(dirname($path)) !== dirname($path)) {
        fail('Directorio público no canónico.');
    }
    if (function_exists('posix_geteuid') && fileowner($path) !== posix_geteuid()) {
        fail('Ejecutar como el propietario actual de .htaccess.');
    }
    $lock = fopen($path.'.ensenada-lock', 'c');
    if ($lock === false || ! flock($lock, LOCK_EX)) {
        fail('No se pudo bloquear el cambio.');
    }
    if (! hash_equals($expected, hash('sha256', regular($path)))) {
        fail('El archivo cambió. Revisar antes de continuar.');
    }
    $stateDirectory = '/home/ensenada/backend-laravel-produccion/deploy-state';
    if (! is_dir($stateDirectory) && ! mkdir($stateDirectory, 0700)) {
        fail('No se pudo preparar el estado privado.');
    }
    if (realpath($stateDirectory) !== $stateDirectory) {
        fail('Estado privado no canónico.');
    }
    $statePath = $stateDirectory.'/cutover.json';
    $targetHash = hash('sha256', $content);
    if ($operation === 'apply') {
        if (($receipt['base'] ?? '') !== $expected || ($receipt['candidate'] ?? '') !== $targetHash) {
            fail('El candidato no corresponde al original vigente. Regenerar y revisar el diff.');
        }
        // Journal before rename: a crash cannot leave an unrecorded applied version.
        $state = ['base' => $expected, 'applied' => $targetHash];
        $stateTemp = $statePath.'.'.bin2hex(random_bytes(8));
        if (file_put_contents($stateTemp, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false
            || ! chmod($stateTemp, 0600) || ! rename($stateTemp, $statePath)) {
            fail('No se pudo registrar el cambio.');
        }
    } else {
        $state = json_decode(regular($statePath), true, flags: JSON_THROW_ON_ERROR);
        if (($state['applied'] ?? '') !== $expected || ($state['base'] ?? '') !== $targetHash) {
            fail('Rollback bloqueado: hay cambios posteriores o el respaldo no es el original aplicado.');
        }
    }
    $temp = $path.'.ensenada-'.bin2hex(random_bytes(8));
    $mode = fileperms($path) & 0777;
    if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content) || ! chmod($temp, $mode)
        || ! hash_equals($expected, hash('sha256', regular($path))) || ! rename($temp, $path)) {
        @unlink($temp);
        fail('No se pudo publicar de forma atómica.');
    }
    flock($lock, LOCK_UN);
    fclose($lock);
}
$op = $argv[1] ?? '';
if ($op === 'candidate' && in_array(count($argv), [4, 5], true)) {
    $original = regular($argv[2]);
    $output = $argv[3];
    if (file_exists($output) || is_link($output) || file_exists($output.'.receipt') || is_link($output.'.receipt')
        || str_contains($original, '# BEGIN ENSENADA PRODUCTION CUTOVER')) {
        fail('Candidato o bloque ya existente.');
    }
    $anchor = '# END mudanza turnos';
    if (substr_count($original, $anchor) !== 1 || ! str_contains($original, 'RewriteRule ^turnos(/(.*))?$ https://veterinaria.ensenada.gov.ar/$2 [R=301,L]')) {
        fail('No se encontró la redirección prioritaria esperada.');
    }
    $fragment = regular(__DIR__.'/root.fragment');
    $preview = $argv[4] ?? null;
    if ($preview !== null && ! filter_var($preview, FILTER_VALIDATE_IP)) {
        fail('IP de revisión inválida.');
    }
    $condition = $preview === null ? '' : 'RewriteCond %{REMOTE_ADDR} ^'.preg_quote($preview, '~').'$';
    $fragment = str_replace('# PREVIEW_CONDITION', $condition, $fragment);
    $fragment = str_replace('#PREVIEW_EXPRESSION', $preview === null ? '' : "&& %{REMOTE_ADDR} == '".$preview."'", $fragment);
    $position = strpos($original, $anchor) + strlen($anchor);
    $candidate = substr($original, 0, $position)."\n\n".$fragment."\n".substr($original, $position);
    $stream = fopen($output, 'xb');
    if ($stream === false || fwrite($stream, $candidate) !== strlen($candidate)) {
        fail('No se pudo crear el candidato.');
    }
    fclose($stream);
    $receipt = fopen($output.'.receipt', 'xb');
    if ($receipt === false || fwrite($receipt, json_encode(['base' => hash('sha256', $original), 'candidate' => hash('sha256', $candidate)], JSON_THROW_ON_ERROR)) === false) {
        fail('No se pudo guardar el comprobante.');
    }
    fclose($receipt);
    echo hash('sha256', $candidate)."  candidato generado; sin instalar\n";
    exit;
}
if (in_array($op, ['apply', 'rollback'], true) && count($argv) === 6 && $argv[5] === '--authorized') {
    $current = $argv[2];
    $source = $argv[3];
    $expected = $argv[4];
    if ($current !== '/home/ensenada/public_html/.htaccess') {
        fail('Destino fuera del dominio principal.');
    }
    if (! preg_match('/\A[a-f0-9]{64}\z/D', $expected)) {
        fail('SHA-256 actual obligatorio.');
    }
    $receipt = $op === 'apply' ? json_decode(regular($source.'.receipt'), true, flags: JSON_THROW_ON_ERROR) : null;
    replaceAtomic($current, regular($source), $expected, $op, $receipt);
    echo 'Cambio atómico aplicado; verificar HTTPS y servicios inmediatamente.'."\n";
    exit;
}
fail('Uso: candidate ORIGINAL SALIDA [IP_REVISION] | apply/rollback /home/ensenada/public_html/.htaccess FUENTE SHA_ACTUAL --authorized');
