<?php

if (! is_file('/.dockerenv') || getenv('IW_FINAL_TEST') !== 'yes') {
    exit(1);
}

// Apache's signal and handling of raw requests are exercised separately in apache.sh.
$_SERVER['HTTPS'] = 'on';
$_SERVER['ENSENADA_REQUEST_LINE'] = $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' HTTP/1.1';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = '/home/ensenada/public_html/prueba.ensenada.gov.ar';
if (preg_match('~^/(?:api|uploads)(?:/|$)~', $path)) {
    unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_FORWARDED_HOST']);
    $_SERVER['REDIRECT_ENSENADA_STAGING_ROUTE'] = '1';
    require $root.'/ensenada-api.php';

    return;
}
if (preg_match('~^/(?:assets/)?[a-zA-Z0-9._-]+\.(js|css|ico|png|jpg|webp)$~', $path, $matches) && is_file($root.$path)) {
    header('Content-Type: '.match ($matches[1]) {
        'js' => 'text/javascript', 'css' => 'text/css', 'ico' => 'image/x-icon', 'jpg' => 'image/jpeg',
        default => 'image/'.$matches[1],
    });
    readfile($root.$path);

    return;
}
header('Content-Type: text/html');
readfile($root.'/index.html');
