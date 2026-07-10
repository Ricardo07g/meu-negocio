FROM php:8.3-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        bcmath \
        gd \
        intl \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Alinha o UID/GID do www-data ao do host (toleramos GID ja existente, ex.: macOS staff=20)
RUN ( groupmod -g ${GID} www-data 2>/dev/null || true ) \
    && ( usermod -u ${UID} -g ${GID} www-data 2>/dev/null || true )

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

USER www-data

EXPOSE 9000
