ARG NEXTCLOUD_VERSION
ARG XDEBUG_VERSION

FROM nextcloud:${NEXTCLOUD_VERSION}

SHELL ["/bin/bash", "-c"]

RUN apt-get update \
    && read -ra PHPIZE_DEPS <<< "${PHPIZE_DEPS}" \
    && apt-get install --no-install-recommends -yq \
      git \
      sudo \
      vim \
      zip \
      "${PHPIZE_DEPS[@]}" \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod ssl \
    && mkdir /tls \
    && openssl req -new -x509 -days 365 -nodes \
      -keyout /tls/server.key \
      -out /tls/server.cert \
      -subj "/C=RO/ST=Bucharest/L=Bucharest/O=IT/CN=www.example.ro"

COPY solid/ /usr/src/nextcloud/apps/solid
COPY docker/xdebug.sh /xdebug.sh
COPY init.sh /
COPY init-live.sh /
COPY site.conf /etc/apache2/sites-enabled/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN composer install --working-dir=/usr/src/nextcloud/apps/solid --prefer-dist \
    && sh /xdebug.sh \
    && pecl install "xdebug-$(cat /xdebug.version)" \
    && docker-php-ext-enable xdebug \
    && NEXTCLOUD_ADMIN_PASSWORD='alice123' \
       NEXTCLOUD_ADMIN_USER='alice' \
       NEXTCLOUD_TRUSTED_DOMAINS='localhost server thirdparty nextcloud.local *.nextcloud.local' \
       NEXTCLOUD_UPDATE=1 \
       /entrypoint.sh 'echo' \
    && php /var/www/html/console.php maintenance:install --admin-user='alice' --admin-pass='alice123' \
    && php /var/www/html/console.php app:enable solid

WORKDIR /var/www/html
EXPOSE 443
