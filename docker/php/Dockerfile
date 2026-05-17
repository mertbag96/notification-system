FROM php:8.3-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        zip \
        unzip \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        bcmath \
        zip \
        mbstring \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
