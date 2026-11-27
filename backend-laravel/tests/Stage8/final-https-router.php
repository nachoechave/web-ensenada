<?php

// Test router for a synthetic staging copy on a Docker-internal TLS network.
if (! is_file('/.dockerenv') || getenv('IW_FINAL_TEST') !== 'yes') {
    exit(1);
}
if (($_SERVER['PHP_AUTH_USER'] ?? '') !== 'synthetic-basic' || ($_SERVER['PHP_AUTH_PW'] ?? '') !== 'synthetic-password') {
    header('WWW-Authenticate: Basic realm="Synthetic staging"');
    http_response_code(401);
    exit;
}
$_SERVER['REMOTE_USER'] = 'synthetic-basic';
$_SERVER['HTTPS'] = 'on';
$_SERVER['ENSENADA_REQUEST_LINE'] = $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' HTTP/1.1';
$path = $_SERVER['REQUEST_URI'];
$root = '/home/ensenada/public_html/prueba.ensenada.gov.ar';
if (preg_match('~^/(?:api|uploads)/~', $path)) {
    require $root.'/ensenada-api.php';
} elseif (preg_match('~^/iwinds-limits-[a-f0-9]{32}\.php$~', $path) && is_file($root.$path)) {
    require $root.$path;
} elseif (preg_match('~^/[a-zA-Z0-9_-]+\.(js|css)$~', $path, $matches) && is_file($root.$path)) {
    header('Content-Type: text/'.($matches[1] === 'css' ? 'css' : 'javascript'));
    readfile($root.$path);
} else {
    header('Content-Type: text/html');
    readfile($root.'/index.html');
}
