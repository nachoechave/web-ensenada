#!/bin/sh
set -eu
umask 077
printf '\n127.0.0.1 www.ensenada.gov.ar ensenada.gov.ar prueba.ensenada.gov.ar tramites.ensenada.gov.ar\n' >> /etc/hosts
root=/home/ensenada/backend-laravel-produccion
mkdir -p "$root" /home/ensenada/backend-laravel /home/ensenada/public_html/web-produccion
cp -R /source/app /source/config /source/routes /source/public /source/deploy "$root/"
mkdir -p "$root/bootstrap"
cp /source/bootstrap/app.php /source/bootstrap/providers.php "$root/bootstrap/"
cp /source/artisan /source/composer.json /source/composer.lock "$root/"
cp -R /opt/runtimevendor "$root/vendor"
mkdir -p "$root/bootstrap/cache" "$root/storage/logs" "$root/storage/framework/views" "$root/storage/framework/cache/data"
for name in noticias sitio hacienda proveedores ensenada-site-scratch ensenada-news-input; do mkdir -m 700 -p "$root/storage/app/private/$name"; done
php /source/tests/Production/environment.php
php "$root/deploy/production/control.php" attest-isolation
cp /source/docker/site-image-policy.xml "$root/deploy/iwinds/policy.xml"
php "$root/deploy/production/provision.php" content --defaults-file=/tmp/prod-setup.cnf --file=/source/tests/Fixtures/site-content.json
printf '%s\n' 'Synthetic-Production-Test-123!' | php "$root/deploy/production/provision.php" admin --defaults-file=/tmp/prod-setup.cnf --email=admin@example.test --name=Administrador
php /source/tests/Production/seed.php
php "$root/deploy/iwinds/schema-check.php"
php "$root/deploy/production/control.php" check
php "$root/artisan" config:cache
php "$root/artisan" route:cache
php "$root/deploy/production/control.php" enable --approved-data-and-cutover
mkdir -p /home/ensenada/public_html/pdf /home/ensenada/public_html/hacienda /home/ensenada/public_html/doc /home/ensenada/public_html/servicios /home/ensenada/public_html/.well-known/acme-challenge
printf 'historical-pdf' > /home/ensenada/public_html/pdf/historico.pdf
printf 'historical-hacienda' > /home/ensenada/public_html/hacienda/historico.pdf
printf 'acme' > /home/ensenada/public_html/.well-known/acme-challenge/fixture
printf '<?php echo "WORDPRESS-UNCHANGED";' > /home/ensenada/public_html/index.php
printf '<?php require $_SERVER["PATH_TRANSLATED"];' > /home/ensenada/handler.php
for host in prueba tramites; do mkdir -p "/home/ensenada/public_html/$host.ensenada.gov.ar"; printf "$host-unchanged" > "/home/ensenada/public_html/$host.ensenada.gov.ar/index.html"; done
cp /source/tests/Production/original.htaccess /home/ensenada/public_html/.htaccess
cp /source/deploy/production/ensenada-production.php /home/ensenada/public_html/web-produccion/
cp /source/deploy/production/web.htaccess /home/ensenada/public_html/web-produccion/.htaccess
test -f /angular/index.csr.html
cp -R /angular/. /home/ensenada/public_html/web-produccion/
cp /angular/index.csr.html /home/ensenada/public_html/web-produccion/index.html
php /source/deploy/production/cutover.php candidate /home/ensenada/public_html/.htaccess /tmp/candidate.htaccess
php /source/deploy/production/cutover.php apply /home/ensenada/public_html/.htaccess /tmp/candidate.htaccess "$(sha256sum /home/ensenada/public_html/.htaccess | cut -d ' ' -f1)" --authorized
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -keyout /tmp/key.pem -out /tmp/cert.pem -subj /CN=www.ensenada.gov.ar >/dev/null 2>&1
chown -R www-data:www-data /home/ensenada
exec apache2-foreground
