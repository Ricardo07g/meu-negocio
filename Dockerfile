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
# Stage 2 — runtime PHP (FrankenPHP)
# ---------------------------------------------------------------------------
# FrankenPHP no lugar do `php artisan serve`: o servidor embutido do PHP atende um
# request por vez e entrega estaticos sem passar pelo Laravel — sem Cache-Control
# possivel. Aqui o Caddy serve os estaticos com cache e ETag, e atende em paralelo.
FROM dunglas/frankenphp:php8.3 AS app

# A imagem ja traz mbstring, xml, pdo_sqlite e opcache. Faltam estas — mesmo
# conjunto de antes. O install-php-extensions compila o gd com webp, exigido pela
# normalizacao de imagens (ADR-0015); o Dockerfile de dev faz isso na mao.
RUN install-php-extensions \
        pdo_mysql \
        zip \
        bcmath \
        gd \
        intl \
        pcntl \
        redis \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Por padrao o Caddy guarda estado em $XDG_DATA_HOME/caddy = /data/caddy — e /data
# e justamente o volume do Railway onde vive o database.sqlite. Tirar daqui evita
# misturar estado de servidor com o dado da aplicacao (e com o backup dele).
ENV XDG_DATA_HOME=/var/lib/frankenphp \
    XDG_CONFIG_HOME=/var/lib/frankenphp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencias PHP (camada cacheavel) — sem scripts/autoloader ainda (codigo nao copiado).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# Codigo + assets ja buildados.
COPY . .
COPY --from=assets /app/public/build ./public/build

# Configuracao do servidor (cache dos estaticos, porta, TLS desligado).
COPY docker/railway/Caddyfile /etc/frankenphp/Caddyfile

# Autoloader otimizado (dispara package:discover via post-autoload-dump).
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x docker/railway/entrypoint.sh

EXPOSE 8080
CMD ["sh", "docker/railway/entrypoint.sh"]
