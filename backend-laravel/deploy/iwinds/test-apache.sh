#!/bin/sh
# Run only inside a fresh, disposable php:8.3-apache-bookworm container.
set -eu
[ -f /.dockerenv ] && [ ! -e /tmp/iwinds-apache ]
base=/tmp/iwinds-apache
public=$base/public_html/prueba.ensenada.gov.ar
private=$base/backend-laravel
mkdir -p "$public/uploads/hacienda" "$public/uploads/proveedores" "$public/uploads/otro" "$private/public" "$private/app/Support"
cp /candidate/htaccess.fragment "$public/.htaccess"
cat >> "$public/.htaccess" <<'EOF'
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.html [END]
EOF
cp /candidate/ensenada-api.php "$public/"
cp /candidate/IwindsPublicProbe.php "$private/app/Support/"
printf 'angular-index' > "$public/index.html"
printf 'angular-asset' > "$public/main.js"
printf 'not-public' > "$public/uploads/hacienda/example.pdf"
printf 'not-public' > "$public/uploads/proveedores/example.pdf"
printf 'not-public' > "$public/uploads/otro/example.pdf"
printf 'not-public' > "$private/.env"
cat > "$private/public/index.php" <<'PHP'
<?php
header('Content-Type: application/json');
header('X-Probe-Fixture-Entry: reached');
$uri = $_SERVER['REQUEST_URI'];
$mode = ENSENADA_IWINDS_STAGING_MODE;
foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'PHP_AUTH_USER', 'PHP_AUTH_PW'] as $field) {
    if (isset($_SERVER[$field])) {
        http_response_code(500);
        exit('Apache credentials reached the application');
    }
}
if (getenv('PROBE_TEST_LARAVEL') === 'true' && $uri !== '/api/test') {
    $headers = ["Host: localhost:18443", 'X-Forwarded-Proto: https', 'Accept: application/json'];
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $headers[] = 'Origin: '.($_SERVER['HTTP_ORIGIN'] === 'https://prueba.ensenada.gov.ar' ? 'https://localhost:18443' : $_SERVER['HTTP_ORIGIN']);
    }
    foreach (['HTTP_SEC_FETCH_SITE' => 'Sec-Fetch-Site', 'CONTENT_TYPE' => 'Content-Type', 'HTTP_COOKIE' => 'Cookie'] as $key => $name) {
        if (isset($_SERVER[$key])) {
            $headers[] = $name.': '.$_SERVER[$key];
        }
    }
    $context = stream_context_create(['http' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'header' => implode("\r\n", $headers),
        'content' => file_get_contents('php://input'),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 5,
    ]]);
    $body = file_get_contents('http://app:8000'.$uri, context: $context);
    http_response_code($body === false ? 502 : (int) explode(' ', $http_response_header[0])[1]);
    foreach ($http_response_header ?? [] as $header) {
        if (str_starts_with(strtolower($header), 'set-cookie:')) {
            header($header, false);
        }
    }
    echo $body;
    exit;
}
if (in_array($uri, ['/uploads/hacienda/example.pdf', '/uploads/proveedores/example.pdf'], true)) {
    http_response_code(404);
}
echo json_encode([
    'uri' => $uri,
    'method' => $_SERVER['REQUEST_METHOD'],
    'mode' => $mode,
    'cookie' => $_COOKIE['example'] ?? null,
    'body' => file_get_contents('php://input'),
], JSON_UNESCAPED_SLASHES);
PHP

a2enmod rewrite ssl >/dev/null
host=prueba.ensenada.gov.ar
password=$(openssl rand -hex 16)
printf 'probe:%s\n' "$(printf '%s\n' "$password" | openssl passwd -apr1 -stdin)" > "$base/htpasswd"
openssl req -new -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=$host" \
    -addext "subjectAltName=DNS:$host" -addext 'basicConstraints=critical,CA:FALSE' \
    -keyout "$base/key" -out "$base/cert" 2>/dev/null
cat > /etc/apache2/sites-enabled/iwinds-test.conf <<EOF
ServerName $host
<VirtualHost *:443>
    ServerName $host
    DocumentRoot "$public"
    SetEnv PROBE_TEST_LARAVEL ${PROBE_TEST_LARAVEL:-false}
    SSLEngine on
    SSLCertificateFile "$base/cert"
    SSLCertificateKeyFile "$base/key"
    <Directory "$public">
        Options SymLinksIfOwnerMatch
        AllowOverride FileInfo
        AuthType Basic
        AuthName "Disposable staging"
        AuthBasicProvider file
        AuthUserFile "$base/htpasswd"
        Require valid-user
    </Directory>
</VirtualHost>
EOF
apache2ctl -t
apache2ctl -DFOREGROUND &
apache=$!
trap 'kill "$apache" 2>/dev/null || :' EXIT
base_url=https://$host
raw_request() {
    curl --silent --show-error --noproxy '*' --path-as-is --resolve "$host:443:127.0.0.1" --cacert "$base/cert" "$@"
}
request() {
    raw_request --user "probe:$password" "$@"
}
status() {
    request -D "$base/headers" -o "$base/body" -w '%{http_code}' "$@"
}
expect() {
    expected=$1
    shift
    result=$(status "$@")
    if [ "$result" != "$expected" ]; then
        printf 'Expected %s, got %s for %s\n' "$expected" "$result" "$*" >&2
        cat "$base/body" >&2
        exit 1
    fi
}
blocked() {
    expect "$@"
    ! grep -qi '^X-Probe-Fixture-Entry:' "$base/headers"
}
i=0
until request "$base_url/" >/dev/null 2>&1; do
    i=$((i + 1))
    [ "$i" -lt 30 ]
    sleep 1
done

[ "$(request "$base_url/")" = angular-index ]
[ "$(request "$base_url/hacienda/42")" = angular-index ]
[ "$(request "$base_url/main.js")" = angular-asset ]
blocked 503 "$base_url/api/auth/me"
grep -q 'API no disponible' "$base/body"
blocked 404 "$base_url/ensenada-api.php?path=/api/auth/me"
blocked 404 "$base_url/.env"
blocked 404 "$base_url/backend-laravel/vendor/autoload.php"
probe="$private/.iwinds-api-public-probe"
full="$private/.iwinds-api-enabled"
expires=$(( $(date +%s) + 600 ))
printf '%s\n' "$expires" > "$probe"
[ "$(raw_request -o /dev/null -w '%{http_code}' "$base_url/api/site-content")" = 401 ]
[ "$(raw_request -o /dev/null -w '%{http_code}' "$base_url/")" = 401 ]
for route in /api/noticias '/api/noticias?page=0&size=2' /api/noticias/destacadas /api/noticias/1 /api/noticias/noticia-1 /api/hacienda /api/proveedores/documentos /api/site-content; do
    expect 200 "$base_url$route"
    grep -qi '^X-Probe-Fixture-Entry: reached' "$base/headers"
    grep -qi '^Cache-Control: no-store' "$base/headers"
    expect 200 -I "$base_url$route"
done
if [ "${PROBE_TEST_LARAVEL:-false}" = true ]; then
    expect 200 "$base_url/api/hacienda/301"
    grep -q '"id":301' "$base/body"
    expect 200 "$base_url/api/site-content"
    grep -q '"titulo":"Portada sintética"' "$base/body"
    expect 200 "$base_url/api/noticias?page=0&size=2"
    grep -q '"size":2' "$base/body"
    for route in /api/noticias/no-existe-prueba /api/noticias/borrador-privado /api/noticias/archivo-privado /api/hacienda/302 /api/hacienda/303; do
        expect 404 "$base_url$route"
        grep -qi '^X-Probe-Fixture-Entry: reached' "$base/headers"
    done
else
    expect 200 "$base_url/api/hacienda/301"
fi
for verb in POST PUT PATCH DELETE OPTIONS; do
    blocked 404 -X "$verb" "$base_url/api/site-content"
    blocked 404 -X "$verb" "$base_url/api/auth/login"
done
for route in /api/auth /api/auth/me /api/auth/login /api/admin /api/admin/noticias /api/admin/site-content /api/admin/usuarios /api/otro /uploads /uploads/otro/example.pdf /uploads/hacienda/example.pdf /uploads/proveedores/example.pdf /uploads/sitio/example.png /uploads/noticias/previous.jpg; do
    blocked 404 "$base_url$route"
    blocked 404 -I "$base_url$route"
done
for route in /api/noticias/ /api//noticias /api/%6eoticias /%61pi/noticias /api/noticias/../auth/me /api/noticias/%2e%2e/auth/me /api/../index.html /api/noticias%2f..%2fauth%2fme /api/%2561uth/me /api/noticias%5C..%5Cauth /api/noticias%3banything /api/noticias%3fpath /API/noticias /ensenada-api.php/api/noticias /%65nsenada-api.php /api/noticias?_method=POST /api/noticias?page=0\&page=1 '/api/noticias?page%5B%5D=0' /api/site-content?next=/api/auth/me; do
    blocked 404 "$base_url$route"
done
for header in X-HTTP-Method-Override X-HTTP-Method X-Method-Override X-Original-Method X-Original-URL X-Original-URI X-Rewrite-URL X-Forwarded-Prefix X-Forwarded-URI; do
    blocked 404 -H "$header: /api/auth/login" "$base_url/api/site-content"
done
blocked 404 -X GET --data 'x=y' "$base_url/api/site-content"
blocked 404 -X GET -H 'Transfer-Encoding: chunked' --data 'x=y' "$base_url/api/site-content"
blocked 404 --request-target "https://$host/api/site-content" "$base_url/"
blocked 404 -H 'Host: localhost' -H "X-Forwarded-Host: $host" "$base_url/api/site-content"
expect 200 -H 'X-Forwarded-Proto: http' -H 'Forwarded: host=other.invalid;proto=http' "$base_url/api/site-content"
image=/uploads/noticias/00000000-0000-4000-8000-000000000001.png
blocked 404 "$base_url$image"
mkdir -p "$public/uploads/noticias"
printf 'not-public' > "$public$image"
printf '%s synthetic-image\n' "$expires" > "$probe"
blocked 200 "$base_url$image"
[ "$(sha256sum "$base/body" | cut -d ' ' -f 1)" = 194c89992426f1265d380ac6df595d54fa6cec6ef07329d5ccfebdf60e1af2a9 ]
grep -qi '^Content-Type: image/png' "$base/headers"
blocked 200 -I "$base_url$image"
blocked 404 "$base_url$image?path=/uploads/hacienda/example.pdf"
touch "$full"
blocked 503 "$base_url/api/site-content"
mv "$full" "$base/unused-full"
for marker in "$(date +%s)" "$(( $(date +%s) + 3700 ))" invalid; do
    printf '%s\n' "$marker" > "$probe"
    blocked 503 "$base_url/api/site-content"
done
mv "$probe" "$base/unused-probe"
ln -s "$base/unused-probe" "$probe"
blocked 503 "$base_url/api/site-content"
mv "$probe" "$base/probe-link"
blocked 503 "$base_url/api/site-content"
admin="$private/.iwinds-api-admin-probe"
receipt="$private/.iwinds-staging-admin-rotation"
printf '%s\n' "$expires" > "$full"
blocked 503 "$base_url/api/site-content"
mv "$full" "$base/unsigned-full"
activate() {
    php -r 'require "/candidate/IwindsPublicProbe.php";
        $proof = str_repeat("a", 64);
        file_put_contents($argv[1], "1 1 $proof\n");
        file_put_contents($argv[2], App\Support\IwindsPublicProbe::privilegedMarker($argv[3], (int) $argv[4], $proof));' "$receipt" "$1" "$2" "$3"
}
activate "$admin" admin "$expires"
blocked 404 -X POST -H 'X-HTTP-Method-Override: PUT' "$base_url/api/auth/login"
blocked 404 -X POST -d '_method=DELETE' "$base_url/api/auth/login"
blocked 404 "$base_url/api/auth/login?_method=POST"
[ "$(raw_request -o /dev/null -w '%{http_code}' -H 'Remote-User: probe' "$base_url/api/auth/me")" = 401 ]
[ "$(raw_request -o /dev/null -w '%{http_code}' -H 'Authorization: Bearer fake' "$base_url/api/auth/me")" = 401 ]
if [ "${PROBE_TEST_LARAVEL:-false}" = true ]; then
    cookie="$base/cookie"
    expect 401 "$base_url/api/auth/me"
    ! grep -qi '^WWW-Authenticate:' "$base/headers"
    expect 403 -H 'Origin: https://outside.invalid' -H 'Sec-Fetch-Site: cross-site' -d '{}' -H 'Content-Type: application/json' "$base_url/api/auth/login"
    expect 200 -c "$cookie" -H "Origin: $base_url" -H 'Sec-Fetch-Site: same-origin' -H 'Content-Type: application/json' \
        -d '{"email":"prensa@example.test","password":"Synthetic-password-1234"}' "$base_url/api/auth/login"
    grep -qi '^Set-Cookie: WEB_ENSENADA_SESSION=.*secure.*httponly.*samesite=strict' "$base/headers"
    expect 200 -b "$cookie" "$base_url/api/auth/me"
    grep -q '"roles":\["PRENSA"\]' "$base/body"
    expect 403 -b "$cookie" "$base_url/api/admin/usuarios"
    expect 200 -b "$cookie" "$base_url/api/admin/noticias"
    expect 204 -b "$cookie" -H "Origin: $base_url" -H 'Sec-Fetch-Site: same-origin' -X POST "$base_url/api/auth/logout"
    expect 401 -b "$cookie" "$base_url/api/auth/me"
fi
mv "$admin" "$base/admin-retired"
activate "$admin" admin "$(date +%s)"
blocked 503 "$base_url/api/auth/me"
mv "$admin" "$base/admin-expired"
activate "$full" full "$expires"
expect 200 -b 'example=synthetic' -d 'field=value' "$base_url/api/test"
grep -q '"cookie":"synthetic","body":"field=value"' "$base/body"
grep -q '"mode":"full"' "$base/body"
if [ "${PROBE_TEST_LARAVEL:-false}" = true ]; then
    expect 404 "$base_url/uploads/hacienda/example.pdf"
    expect 404 "$base_url/uploads/proveedores/example.pdf"
    expect 404 "$base_url/uploads/hacienda/00000000-0000-4000-8000-000000000454.pdf"
else
    expect 404 "$base_url/uploads/hacienda/example.pdf"
    expect 404 "$base_url/uploads/proveedores/example.pdf"
    expect 200 "$base_url/uploads/noticias/example.jpg"
    expect 200 "$base_url/uploads/sitio/example.png"
fi
mv "$full" "$base/full-retired"
blocked 503 "$base_url/api/site-content"
printf 'Apache HTTPS: Basic, cookie, CSRF, roles, revocación, ventanas, descargas y reversión verificados\n'
