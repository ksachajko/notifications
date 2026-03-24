FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git unzip zip curl librabbitmq-dev libssl-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && pecl install amqp xdebug \
    && docker-php-ext-enable amqp xdebug \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

WORKDIR /app
