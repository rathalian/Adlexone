FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        nginx \
        unzip \
        libsqlite3-dev \
        libxml2-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
