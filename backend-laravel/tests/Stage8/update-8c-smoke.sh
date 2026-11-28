#!/bin/sh
set -eu
[ -f /.dockerenv ] && [ ! -e /home/ensenada/backend-laravel ]
umask 077
mkdir -p /tmp/old /tmp/update /tmp/backup /home/ensenada
unzip -q /old/web-ensenada-iwinds-staging.zip -d /tmp/old
mv /tmp/old/private/backend-laravel /home/ensenada/backend-laravel
(cd /release && sha256sum -c web-ensenada-iwinds-stage8c.zip.sha256)
unzip -q /release/web-ensenada-iwinds-stage8c.zip -d /tmp/update
(cd /tmp/update && sha256sum -c SHA256SUMS)

cd /home/ensenada/backend-laravel
export APP_ENV=staging APP_DEBUG=false APP_URL=https://prueba.ensenada.gov.ar
export DB_CONNECTION=mysql DB_HOST=localhost DB_DATABASE=ensenada_web DB_USERNAME=ensenada_web_app
export DB_COLLATION=utf8mb4_unicode_520_ci DB_PASSWORD=synthetic-update-check
export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export AUTH_JWT_SECRET=synthetic-update-check-only-not-a-real-key
export LOG_CHANNEL=stderr
unset NEWS_DB_USERNAME NEWS_DB_PASSWORD
[ ! -e .env ] && [ ! -e .iwinds-api-enabled ]
if php artisan route:list >/tmp/old-refusal 2>&1; then exit 1; fi
grep -q 'Configuración iWinds de staging incompleta' /tmp/old-refusal

while IFS= read -r rel; do
    if [ -f "$rel" ]; then
        mkdir -p "/tmp/backup/$(dirname "$rel")"
        cp -p "$rel" "/tmp/backup/$rel"
    fi
    install -m 600 "/tmp/update/payload/$rel" "$rel.stage8c"
    mv "$rel.stage8c" "$rel"
    cmp "/tmp/update/payload/$rel" "$rel"
done < /tmp/update/FILES

php artisan config:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan route:list --path=api/noticias
php -r '$db=(require "bootstrap/cache/config.php")["database"]; exit($db["default"] === "mysql" && !isset($db["connections"]["news"]) ? 0 : 1);'
[ ! -e .iwinds-api-enabled ] && [ ! -e .env ]
php artisan route:clear
php artisan config:clear

while IFS= read -r rel; do
    if [ -f "/tmp/backup/$rel" ]; then
        cp -p "/tmp/backup/$rel" "$rel.stage8c-rollback"
        mv "$rel.stage8c-rollback" "$rel"
        cmp "/tmp/backup/$rel" "$rel"
    else
        mkdir -p "/tmp/retired/$(dirname "$rel")"
        mv "$rel" "/tmp/retired/$rel"
    fi
done < /tmp/update/FILES
if php artisan route:list >/tmp/rollback-refusal 2>&1; then exit 1; fi
grep -q 'Configuración iWinds de staging incompleta' /tmp/rollback-refusal
echo 'Paquete, actualización sin lector, cachés y reversión privada verificados sin red'
