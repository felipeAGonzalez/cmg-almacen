# syntax=docker/dockerfile:1

FROM node:22-alpine AS frontend

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.3-apache

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libcurl4-openssl-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        supervisor \
        unzip \
        zip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        exif \
        gd \
        mbstring \
        pcntl \
        pdo_mysql \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=frontend /var/www/html/public/build ./public/build
COPY .docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/php.ini /usr/local/etc/php/conf.d/cmg.ini
COPY .docker/supervisord.conf /etc/supervisor/conf.d/cmg.conf

RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl --fail --silent --show-error http://127.0.0.1/health >/dev/null || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
