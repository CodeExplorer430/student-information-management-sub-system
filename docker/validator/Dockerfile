FROM php:8.4-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install --yes --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libonig-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        libmariadb-dev-compat \
        libmariadb-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        curl \
        dom \
        gd \
        mbstring \
        mysqli \
        pdo_mysql \
        pdo_sqlite \
        simplexml \
        xml \
        xmlwriter \
        zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/validator/entrypoint.sh /usr/local/bin/validator-entrypoint

RUN chmod +x /usr/local/bin/validator-entrypoint

WORKDIR /workspace

ENTRYPOINT ["validator-entrypoint"]
