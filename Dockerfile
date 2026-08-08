# Dockerfile de PRODUCAO (usado pelo Railway).
# O ambiente de desenvolvimento continua em docker/php/Dockerfile + docker-compose.yml.
# Multi-stage: (1) Node builda os assets do Vite; (2) imagem PHP roda a aplicacao.

# ---------------------------------------------------------------------------
# Stage 1 — assets (Vite / Tailwind)
# ---------------------------------------------------------------------------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — runtime PHP
# ---------------------------------------------------------------------------
FROM php:8.3-cli AS app

# Extensoes PHP (mesmo conjunto do ambiente de dev, sem o redis — v1 usa driver database).
RUN apt-get update && apt-get install -y \
        git curl zip unzip \
        libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
        libonig-dev libxml2-dev libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_mysql mbstring xml zip bcmath gd intl pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencias PHP (camada cacheavel) — sem scripts/autoloader ainda (codigo nao copiado).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# Codigo + assets ja buildados.
COPY . .
COPY --from=assets /app/public/build ./public/build

# Autoloader otimizado (dispara package:discover via post-autoload-dump).
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x docker/railway/entrypoint.sh

EXPOSE 8080
CMD ["sh", "docker/railway/entrypoint.sh"]
