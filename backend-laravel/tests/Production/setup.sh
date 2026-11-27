#!/bin/sh
set -eu
umask 077
printf '\n127.0.0.1 www.ensenada.gov.ar ensenada.gov.ar prueba.ensenada.gov.ar tramites.ensenada.gov.ar\n' >> /etc/hosts
mkdir -p /home/ensenada/public_html/web-produccion /home/ensenada/public_html/prueba.ensenada.gov.ar
printf 'Require all denied\n' > /home/ensenada/public_html/web-produccion/.htaccess
php /source/tests/SharedDatabase/bootstrap.php
mkdir -p /home/ensenada/public_html/pdf /home/ensenada/public_html/hacienda /home/ensenada/public_html/.well-known/acme-challenge
printf 'historical-pdf' > /home/ensenada/public_html/pdf/historico.pdf
printf 'historical-hacienda' > /home/ensenada/public_html/hacienda/historico.pdf
printf 'acme' > /home/ensenada/public_html/.well-known/acme-challenge/fixture
printf '<?php echo "WORDPRESS-UNCHANGED";' > /home/ensenada/public_html/index.php
printf '<?php require $_SERVER["PATH_TRANSLATED"];' > /home/ensenada/handler.php
mkdir -p /home/ensenada/public_html/tramites.ensenada.gov.ar
printf 'tramites-unchanged' > /home/ensenada/public_html/tramites.ensenada.gov.ar/index.html
printf 'prueba-unchanged' > /home/ensenada/public_html/prueba.ensenada.gov.ar/index.html
cp /source/tests/Production/original.htaccess /home/ensenada/public_html/.htaccess
cp /source/tests/SharedDatabase/legacy-bridge.php /home/ensenada/public_html/prueba.ensenada.gov.ar/ensenada-api.php
cat > /home/ensenada/public_html/prueba.ensenada.gov.ar/.htaccess <<'APACHE'
AuthType Basic
AuthName "Synthetic staging fixture"
AuthUserFile /home/ensenada/basic.htpasswd
Require valid-user
RewriteEngine On
RewriteRule ^(?:api(?:/.*)?|uploads/.*)$ ensenada-api.php [END,E=ENSENADA_REQUEST_LINE:%{THE_REQUEST},E=ENSENADA_STAGING_ROUTE:1]
APACHE
printf 'staging:%s\n' "$(openssl passwd -apr1 synthetic-basic-only)" > /home/ensenada/basic.htpasswd
cp -R /angular/. /home/ensenada/public_html/web-produccion/
cp /angular/index.csr.html /home/ensenada/public_html/web-produccion/index.html
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -keyout /tmp/key.pem -out /tmp/cert.pem -subj /CN=www.ensenada.gov.ar -addext 'subjectAltName=DNS:www.ensenada.gov.ar,DNS:ensenada.gov.ar,DNS:prueba.ensenada.gov.ar,DNS:tramites.ensenada.gov.ar' >/dev/null 2>&1
chmod 644 /tmp/cert.pem
chown -R www-data:www-data /home/ensenada
exec apache2-foreground
