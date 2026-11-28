#!/usr/bin/env bash
set -euo pipefail
[[ -f /.dockerenv && ! -e /home/ensenada/backend-laravel && $IW_FINAL_TEST == yes ]]
artifacts=/repo/backend-laravel/output
home=/home/ensenada
app=$home/backend-laravel
web=$home/public_html/prueba.ensenada.gov.ar
release=$home/iwinds-final
mkdir -p "$web" /opt/cpanel/ea-php83/root/usr/bin
cd /tmp
unzip -q "$artifacts/iwinds-release/web-ensenada-iwinds-staging.zip" -d base
unzip -q "$artifacts/iwinds-stage8c-release/web-ensenada-iwinds-stage8c.zip" -d stage8c
unzip -q "$artifacts/iwinds-closure-release/web-ensenada-iwinds-closure.zip" -d closure
unzip -q "$artifacts/iwinds-final-release/web-ensenada-iwinds-final.zip" -d "$release"
mv base/private/backend-laravel "$app"
cp -R stage8c/payload/. "$app/"
cp -R closure/private/. "$app/"
cp -R "$release/photos/private/." "$app/"
cp "$app/deploy/iwinds/policy.xml" /etc/ImageMagick-6/policy.xml
cp closure/apache/ensenada-api.php "$web/"
printf 'Angular anterior sintético\n' > "$web/index.html"
cat > /opt/cpanel/ea-php83/root/usr/bin/php <<'SH'
#!/bin/sh
exec /usr/local/bin/php -d curl.cainfo=/tmp/staging-ca.crt "$@"
SH
chmod 755 /opt/cpanel/ea-php83/root/usr/bin/php
cd "$app"
# The unmodified staging checks target the hosting account. This copy only
# replaces the database host, database and account with the Docker fixtures.
sed -i "s/'127.0.0.1'/'mysql-auth'/; s/'ensenada_web'/'ensenada_auth_test'/; s/'ensenada_web_app'/'auth_runtime'/" app/Support/IwindsStaging.php
export APP_ENV=staging APP_DEBUG=false APP_URL=https://prueba.ensenada.gov.ar
export DB_CONNECTION=mysql DB_HOST=mysql-auth DB_DATABASE=ensenada_auth_test DB_USERNAME=auth_runtime
export DB_COLLATION=utf8mb4_unicode_520_ci DB_PASSWORD=synthetic-runtime-only-1234
export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export AUTH_JWT_SECRET=synthetic-final-https-fixture-not-a-real-secret LOG_CHANNEL=stderr
php artisan config:cache
bash "$release/install.sh"
exec php -d upload_max_filesize=32M -d post_max_size=40M -d memory_limit=256M -S 0.0.0.0:18081 -t "$web" /repo/backend-laravel/tests/Stage8/final-https-router.php
