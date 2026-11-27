<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
$path = $argv[1] ?? '';
$raw = is_file($path) && ! is_link($path) ? file_get_contents($path) : '';
$ini = parse_ini_string($raw, true, INI_SCANNER_RAW);
$client = $ini['client'] ?? [];
if (! is_array($ini) || array_keys($ini) !== ['client'] || preg_match('/^\s*!/m', $raw)
    || array_diff(array_keys($client), ['host', 'port', 'user', 'password']) !== []
    || ! in_array($client['host'] ?? '', ['127.0.0.1', 'localhost'], true)
    || (int) ($client['port'] ?? 3306) !== 3306 || empty($client['user']) || empty($client['password'])) {
    fwrite(STDERR, "Archivo de backup requiere [client] local con host, user, password y port opcional; sin database ni inclusiones.\n");
    exit(1);
}
