#!/bin/sh
set -eu
mkdir -m 0700 -p /var/lib/ensenada-news-storage/noticias
mkdir -m 0700 -p /var/lib/ensenada-site-storage/sitio
mkdir -m 0700 -p /var/lib/ensenada-hacienda-storage/hacienda
mkdir -m 0700 -p /var/lib/ensenada-proveedores-storage/proveedores
if [ "$DB_CONNECTION" = "sqlite" ]; then
    touch /tmp/ensenada-auth.sqlite
    php artisan migrate --no-interaction
    php artisan db:seed --no-interaction
    php docker/sqlite-news-setup.php
    php docker/sqlite-site-setup.php
    php docker/sqlite-hacienda-setup.php
else
    php docker/mysql-ready.php
fi
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
