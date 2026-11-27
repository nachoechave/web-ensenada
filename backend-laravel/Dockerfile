FROM composer:2@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c AS composer

FROM php:8.3-cli-bookworm@sha256:6e25fbb304ba6641eb4d2a53e92bea8eeba8c0d0eabf6ca41d0f1ac3b21a9ef0 AS development
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev libxml2-dev libsqlite3-dev libjpeg62-turbo-dev libpng-dev libzip-dev imagemagick util-linux \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j2 mbstring pdo_sqlite pdo_mysql dom xml xmlwriter gd zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV COMPOSER_HOME=/tmp/ensenada-composer
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM development AS application
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist
COPY . .
COPY docker/news-images.ini /usr/local/etc/php/conf.d/ensenada-news-images.ini
COPY docker/site-image-policy.xml /etc/ImageMagick-6/policy.xml
COPY --from=flyway V1__portal_prensa.sql V2__hacienda_publicaciones.sql V3__jwt_token_version.sql V5__noticias_slug.sql V7__proveedores_documentos.sql V99__noticia_imagenes.sql /audit/flyway/
RUN --mount=type=bind,from=flyway,source=V4__portal_site_content.sql,target=/tmp/v4-site-content.sql \
    sed -n '1,/^);/p' /tmp/v4-site-content.sql > /audit/flyway/V4__portal_site_content.sql
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
RUN mkdir -m 0700 -p /var/lib/ensenada-news-input /var/lib/ensenada-site-scratch
CMD ["sh", "docker/local-env.sh", "sh", "docker/local-server.sh"]

FROM development AS iwinds-builder
WORKDIR /bundle/private/backend-laravel
COPY composer.json composer.lock artisan ./
COPY app app
COPY bootstrap/app.php bootstrap/providers.php bootstrap/
COPY config config
COPY routes routes
COPY public/index.php public/index.php
COPY deploy/iwinds/README.md deploy/iwinds/production.env.example deploy/iwinds/platform.php deploy/iwinds/schema-check.php deploy/iwinds/schema-grants.sql deploy/iwinds/
COPY docker/site-image-policy.xml deploy/iwinds/policy.xml
COPY deploy/iwinds/ensenada-api.php deploy/iwinds/htaccess.fragment /bundle/apache/
COPY deploy/iwinds/build-package.php /tmp/build-package.php
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    storage/app/private/noticias storage/app/private/sitio storage/app/private/hacienda \
    storage/app/private/proveedores storage/app/private/ensenada-site-scratch \
    && composer validate --no-check-publish \
    && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-plugins --no-scripts \
    && composer check-platform-reqs --no-dev \
    && composer audit --locked --no-dev \
    && mkdir /result && php /tmp/build-package.php

FROM scratch AS iwinds-package
COPY --from=iwinds-builder /result/ /
