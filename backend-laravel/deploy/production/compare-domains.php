<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
// Accept the existing text inventory: one URL or host per line, with optional status/redirect columns.
// Snapshot format is TSV: URL, status, redirect. Never follows redirects to another system.
$op = $argv[1] ?? '';
$file = $argv[2] ?? '';
if (! in_array($op, ['snapshot', 'compare'], true) || ! is_file($file)) {
    fwrite(STDERR, "Uso: compare-domains.php snapshot INVENTARIO | compare BASELINE_TSV\n");
    exit(1);
}
$failed = false;
$count = 0;
foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) {
        continue;
    }
    if (! preg_match('~(?:https://)?([a-z0-9-]+(?:\.[a-z0-9-]+)*\.ensenada\.gov\.ar|ensenada\.gov\.ar)(?=[:/\s]|$)~i', $line, $parts)) {
        continue;
    }
    $url = 'https://'.strtolower($parts[1]).'/';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    $actual = $url."\t".sprintf('%03d', $code)."\t".$redirect;
    if ($op === 'snapshot') {
        echo $actual."\n";
    } else {
        if ($actual !== $line) {
            echo 'CAMBIO '.$actual."\n";
            $failed = true;
        } else {
            echo 'IGUAL '.$parts[1]."\n";
        }
    }
    $count++;
}
if ($count === 0) {
    fwrite(STDERR, "Inventario sin dominios reconocibles.\n");
    exit(1);
}
exit($failed ? 1 : 0);
