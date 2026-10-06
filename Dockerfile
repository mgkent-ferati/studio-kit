FROM dunglas/frankenphp:1-php8.4-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg unzip git \
    && rm -rf /var/lib/apt/lists/*
RUN install-php-extensions pdo_pgsql intl zip opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-studio-kit.ini

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=":80"
WORKDIR /app

FROM base AS ci
COPY . /app
RUN composer install --no-interaction --no-progress
