FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader

FROM php:8.5-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libc-client-dev libkrb5-dev qpdf unzip \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo_pgsql zip intl gd bcmath opcache \
 && PHP_IMAP_KERBEROS=true PHP_IMAP_SSL=true pecl install imap \
 && docker-php-ext-enable imap \
 && apt-get clean && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY docker/php.ini /usr/local/etc/php/conf.d/pav-sign.ini
RUN mkdir -p storage/app/private/tmp storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]
