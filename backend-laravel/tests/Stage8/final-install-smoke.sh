#!/usr/bin/env bash
set -euo pipefail

[[ -f /.dockerenv && ! -e /home/ensenada/backend-laravel ]]
artifacts=/repo/backend-laravel/output
home=/home/ensenada
app=$home/backend-laravel
web=$home/public_html/prueba.ensenada.gov.ar
work=$(mktemp -d)
cd "$work"
unzip -q "$artifacts/iwinds-release/web-ensenada-iwinds-staging.zip" -d base
unzip -q "$artifacts/iwinds-stage8c-release/web-ensenada-iwinds-stage8c.zip" -d stage8c
unzip -q "$artifacts/iwinds-closure-release/web-ensenada-iwinds-closure.zip" -d closure
unzip -q "$artifacts/iwinds-final-release/web-ensenada-iwinds-final.zip" -d final
release=$work/final
mkdir -p "$web" /opt/cpanel/ea-php83/root/usr/bin
ln -s "$(command -v php)" /opt/cpanel/ea-php83/root/usr/bin/php
mv base/private/backend-laravel "$app"
cp -R stage8c/payload/. "$app/"
cp -R closure/private/. "$app/"
cp closure/apache/ensenada-api.php "$web/"
cp closure/apache/htaccess.fragment "$web/.htaccess"
printf 'Angular anterior sintético\n' > "$web/index.html"
printf 'Bundle previo sintético\n' > "$web/main-previous.js"
sha256sum "$web/index.html" "$web/main-previous.js" "$web/.htaccess" "$web/ensenada-api.php" > previous.sha
export APP_ENV=staging APP_DEBUG=false APP_URL=https://prueba.ensenada.gov.ar
export DB_CONNECTION=mysql DB_HOST=localhost DB_DATABASE=ensenada_web DB_USERNAME=ensenada_web_app
export DB_COLLATION=utf8mb4_unicode_520_ci DB_PASSWORD=synthetic-unused-no-network
export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export AUTH_JWT_SECRET=synthetic-install-test-unused-not-a-real-secret LOG_CHANNEL=stderr
cd "$app"
php artisan config:cache
php artisan route:cache
if php "$release/routes.php" check; then exit 1; fi
cp -R "$release/photos/private/." .
find "$release/photos/private" -type f -printf '%P\n' | sort | xargs -d '\n' stat -c '%i %y %n' > "$work/private.before"

touch .iwinds-api-enabled
if bash "$release/install.sh"; then exit 1; fi
rm .iwinds-api-enabled
bundle=$(basename "$release"/photos/angular/main-*.js)
printf 'colisión\n' > "$web/$bundle"
if bash "$release/install.sh"; then exit 1; fi
sha256sum -c "$work/previous.sha"
rm "$web/$bundle"

bash "$release/install.sh"
php "$release/routes.php" check
cmp "$web/index.html" "$release/photos/angular/index.html"
stat -c '%i %y %n' "$web/index.html" "$web/$bundle" bootstrap/cache/routes-v7.php > "$work/first.stat"
bash "$release/install.sh"
stat -c '%i %y %n' "$web/index.html" "$web/$bundle" bootstrap/cache/routes-v7.php > "$work/second.stat"
diff "$work/first.stat" "$work/second.stat"
find "$release/photos/private" -type f -printf '%P\n' | sort | xargs -d '\n' stat -c '%i %y %n' > "$work/private.after"
diff "$work/private.before" "$work/private.after"
bash "$release/rollback.sh"
bash "$release/rollback.sh"
sha256sum -c "$work/previous.sha"
php "$release/routes.php" check
echo 'Instalación, colisión, bloqueo por marcador, idempotencia y reversión aprobados.'
