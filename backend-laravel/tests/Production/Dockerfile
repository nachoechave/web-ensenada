FROM ensenada-auth-stage2-app:local AS cli
FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libzip-dev libpng-dev libjpeg62-turbo-dev libsqlite3-dev imagemagick util-linux openssl curl \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql pdo_sqlite mbstring gd zip \
    && a2enmod rewrite ssl headers actions \
    && rm -rf /var/lib/apt/lists/*
COPY --from=cli /usr/local/bin/composer /usr/local/bin/composer
COPY --from=cli /app/vendor /opt/devvendor
COPY --from=mariadb:10.11.19 /usr/bin/mariadb-dump /usr/local/bin/mariadb-dump
COPY output/dependencies/vendor /opt/runtimevendor
COPY tests/Production/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/site-image-policy.xml /etc/ImageMagick-6/policy.xml
RUN printf 'memory_limit=256M\nupload_max_filesize=32M\npost_max_size=40M\nmax_execution_time=120\n' > /usr/local/etc/php/conf.d/production-test.ini
WORKDIR /source
RUN mkdir -p /opt/cpanel/ea-php83/root/usr/bin && ln -s /usr/local/bin/php /opt/cpanel/ea-php83/root/usr/bin/php
ENTRYPOINT ["sh","/source/tests/Production/setup.sh"]
