#!/usr/bin/env bash
set -euo pipefail
[[ -f /.dockerenv && ! -e /tmp/staging-public-apache ]]
release=/repo/tmp/staging-publico/deploy/iwinds/staging-public
root=/tmp/staging-public-apache
home=$root/home
backend=$home/backend-laravel
docroot=$home/public_html/prueba.ensenada.gov.ar
host=prueba.ensenada.gov.ar
mkdir -p "$backend/app/Support" "$backend/public" "$docroot/uploads/hacienda"
cp "$release"/apache/* "$docroot/"
mv "$docroot/htaccess.fragment" "$docroot/.htaccess"
cp "$release/private/app/Support/IwindsPublicProbe.php" "$backend/app/Support/"
printf '<h1>Angular fixture</h1>' > "$docroot/index.html"
printf 'no publicar' > "$docroot/uploads/hacienda/example.pdf"
cat >>"$docroot/.htaccess" <<'EOF'
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.html [END]
EOF
cat >"$backend/public/index.php" <<'PHP'
<?php
header('Content-Type: application/json');
echo json_encode(['mode' => ENSENADA_IWINDS_STAGING_MODE, 'uri' => $_SERVER['REQUEST_URI']]);
PHP

openssl req -new -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=$host" \
    -addext "subjectAltName=DNS:$host" -keyout "$root/key" -out "$root/cert" 2>/dev/null
a2enmod rewrite ssl >/dev/null
cat >/etc/apache2/sites-enabled/staging-public.conf <<EOF
ServerName $host
<VirtualHost *:443>
    ServerName $host
    DocumentRoot "$docroot"
    SSLEngine on
    SSLCertificateFile "$root/cert"
    SSLCertificateKeyFile "$root/key"
    <Directory "$docroot">
        Options SymLinksIfOwnerMatch
        AllowOverride FileInfo
        Require all granted
    </Directory>
</VirtualHost>
EOF
apache2ctl -t
apache2ctl -DFOREGROUND >/tmp/staging-apache.log 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || :' EXIT
request() {
    curl -sS --noproxy '*' --path-as-is --resolve "$host:443:127.0.0.1" --cacert "$root/cert" "$@"
}
for _ in {1..30}; do
    if request "https://$host/" >/dev/null 2>&1; then break; fi
    sleep .1
done
assert_http() {
    local expected=$1 path=$2 actual
    shift 2
    actual=$(request -o "$root/body" -w '%{http_code}' "$@" "https://$host$path")
    if [[ $actual != "$expected" ]]; then
        cat "$root/body" /tmp/staging-apache.log >&2
        printf 'Se esperaba %s, Apache respondió %s en %s\n' "$expected" "$actual" "$path" >&2
        exit 1
    fi
}
assert_http 503 /api/site-content
cat >"$root/mark.php" <<'PHP'
<?php
require $argv[1].'/app/Support/IwindsPublicProbe.php';
use App\Support\IwindsPublicProbe as Probe;
$proof = str_repeat('a', 64);
file_put_contents($argv[1].'/'.Probe::ROTATION, "1 1 $proof\n");
file_put_contents($argv[1].'/'.Probe::WEB_MARKER, Probe::webMarker($proof));
chmod($argv[1].'/'.Probe::WEB_MARKER, 0600);
file_put_contents($argv[1].'/'.Probe::ADMIN_MARKER, Probe::extendedAdminMarker(time(), time() + 86400, $proof));
chmod($argv[1].'/'.Probe::ADMIN_MARKER, 0600);
PHP
php "$root/mark.php" "$backend"
chown www-data:www-data "$backend/.iwinds-staging-public-web" "$backend/.iwinds-api-admin-probe"
assert_http 200 /api/site-content
grep -Fq '"mode":"web"' "$root/body"
assert_http 200 /api/admin/usuarios
grep -Fq '"mode":"web-admin"' "$root/body"
for path in / /noticias /noticias/sintetica /areas /intendencia /hacienda /proveedores /admin/login; do
    assert_http 200 "$path"
    grep -Fq 'Angular fixture' "$root/body"
done
assert_http 404 /ensenada-api.php
assert_http 404 /ensenada-api.php/api/site-content
assert_http 404 /%65nsenada-api.php
assert_http 404 /.env
assert_http 404 /api/%61dmin/usuarios
assert_http 404 /api/site-content -H 'X-Forwarded-For: 192.0.2.4'
assert_http 404 /api/site-content -H 'Host: ensenada.gov.ar'
assert_http 404 /api/admin/usuarios -H 'X-HTTP-Method-Override: DELETE'
assert_http 404 /api/admin/usuarios -H 'Remote-User: synthetic'
assert_http 404 /api/site-content -X POST -H "Origin: https://$host" -H 'Sec-Fetch-Site: same-origin'
assert_http 403 /api/auth/login -X POST -H 'Origin: https://ajeno.example.test' -H 'Sec-Fetch-Site: cross-site'
assert_http 403 /api/auth/login -X POST -H "Origin: https://$host"
assert_http 200 /api/auth/login -X POST -H "Origin: https://$host" -H 'Sec-Fetch-Site: same-origin'
assert_http 404 /uploads/hacienda/example.pdf
assert_http 404 /api/admin/usuarios -H 'Authorization: Bearer synthetic'
rm "$backend/.iwinds-api-admin-probe"
assert_http 404 /api/admin/usuarios
assert_http 200 /api/site-content
echo 'Apache HTTPS: frontera de reescritura, anfitrión, métodos, origen, archivos físicos y cierre verificados.'
