FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts

FROM php:8.4-cli
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    pkg-config \
    libzip-dev \
    libsqlite3-dev \
    libonig-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite mbstring intl zip bcmath gd pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY . /var/www/html

EXPOSE 8000
