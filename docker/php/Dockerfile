FROM php:8.4-fpm-alpine

# Extensions PHP nécessaires
RUN apk add --no-cache \
        libpq-dev libzip-dev libpng-dev libjpeg-turbo-dev \
        freetype-dev icu-dev oniguruma-dev libsodium-dev \
        git unzip curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql bcmath intl zip gd mbstring sodium opcache \
    && pecl install redis apcu \
    && docker-php-ext-enable redis apcu

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Configuration PHP (production-ready)
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

EXPOSE 9000
CMD ["php-fpm"]
