ARG NEXTCLOUD_VERSION
FROM nextcloud:${NEXTCLOUD_VERSION}

RUN apt-get update && apt-get install -yq \
      git \
      sudo \
      vim \
      zip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod ssl \
    && mkdir /tls \
    && openssl req -new -x509 -days 365 -nodes \
      -keyout /tls/server.key \
      -out /tls/server.cert \
      -subj "/C=RO/ST=Bucharest/L=Bucharest/O=IT/CN=www.example.ro"

COPY solid/ /usr/src/nextcloud/apps/solid
COPY init.sh /
COPY init-live.sh /
COPY site.conf /etc/apache2/sites-enabled/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer install --working-dir=/usr/src/nextcloud/apps/solid --no-dev --prefer-dist \
    && rm  /usr/local/bin/composer

WORKDIR /var/www/html
EXPOSE 443
