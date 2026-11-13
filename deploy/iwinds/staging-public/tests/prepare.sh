#!/usr/bin/env bash
set -euo pipefail
[[ -f /.dockerenv && $IW_FINAL_TEST == yes ]]
source_root=/repo/backend-laravel
test_root=/repo/tmp/staging-publico/deploy/iwinds/staging-public/tests
home=/home/ensenada
backend=$home/backend-laravel
docroot=$home/public_html/prueba.ensenada.gov.ar
output=/repo/output/staging-publico
mkdir -m 700 -p "$home/iwinds-admin-period" "$home/iwinds-staging-publico"
unzip -oq "$source_root/output/iwinds-admin-period-release/web-ensenada-iwinds-admin-period.zip" -d "$home/iwinds-admin-period"
unzip -oq "$output/web-ensenada-iwinds-staging-publico.zip" -d "$home/iwinds-staging-publico"
unzip -p "$source_root/output/iwinds-cpanel-dml-release/web-ensenada-iwinds-cpanel-dml.zip" \
    payload/deploy/iwinds/schema-check.php > "$backend/deploy/iwinds/schema-check.php"
cat "$source_root/deploy/iwinds/htaccess.fragment" > "$docroot/.htaccess"
printf '\nAuthType Basic\nRequire valid-user\n' >> "$docroot/.htaccess"
bash "$home/iwinds-admin-period/install.sh" install
unzip -p "$source_root/output/iwinds-closure-release/web-ensenada-iwinds-closure.zip" \
    private/app/Support/IwindsStaging.php > "$backend/app/Support/IwindsStaging.php"
cd "$backend"
sed -i "s/'127.0.0.1'/'mysql-auth'/; s/'ensenada_web'/'ensenada_auth_test'/; s/'ensenada_web_app'/'auth_runtime'/" app/Support/IwindsStaging.php
php "$test_root/integration.php" seed
sed -i "s/'mysql-auth'/'127.0.0.1'/; s/'ensenada_auth_test'/'ensenada_web'/; s/'auth_runtime'/'ensenada_web_app'/" app/Support/IwindsStaging.php
period_before=$(sha256sum .iwinds-api-admin-probe)
bash "$home/iwinds-staging-publico/install.sh" install
bash "$home/iwinds-staging-publico/install.sh" install
[[ $(sha256sum .iwinds-api-admin-probe) == "$period_before" ]]
bash "$home/iwinds-staging-publico/install.sh" rollback
[[ $(sha256sum .iwinds-api-admin-probe) == "$period_before" ]]
bash "$home/iwinds-staging-publico/install.sh" install
sed -i "s/'127.0.0.1'/'mysql-auth'/; s/'ensenada_web'/'ensenada_auth_test'/; s/'ensenada_web_app'/'auth_runtime'/" app/Support/IwindsStaging.php
build=/repo/tmp/favicon-institucional/frontend/dist/frontend/browser
cp "$build"/index.csr.html "$docroot/index.html"
cp "$build"/*.js "$build"/*.css "$docroot/"
mkdir -p "$docroot/assets"
cp "$build"/assets/* "$docroot/assets/"
echo 'Paquete: instalación repetida, reversión y preservación del vencimiento verificados.'
