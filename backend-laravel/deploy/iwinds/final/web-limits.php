<?php

declare(strict_types=1);

$name = basename(__FILE__);
if (! preg_match('/\Aiwinds-limits-[a-f0-9]{32}\.php\z/D', $name)
    || ($_SERVER['REQUEST_URI'] ?? '') !== '/'.$name
    || ($_SERVER['HTTPS'] ?? '') !== 'on'
    || ($_SERVER['HTTP_HOST'] ?? '') !== 'prueba.ensenada.gov.ar'
    || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
    || ($_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? '') === ''
    || time() >= filemtime(__FILE__) + 1800) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

echo json_encode([
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'file_uploads' => (bool) ini_get('file_uploads'),
], JSON_THROW_ON_ERROR);
