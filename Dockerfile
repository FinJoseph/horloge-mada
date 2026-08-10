# syntax=docker/dockerfile:1

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
COPY public/ public/
RUN npm run build

FROM dunglas/frankenphp:1-php8.4
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Render/K8s ne peuvent pas exec un binaire à file capabilities (no_new_privs).
# On écoute sur 8080 (port non privilégié) => cap_net_bind_service inutile.
RUN setcap -r /usr/local/bin/frankenphp

RUN install-php-extensions \
    bcmath \
    ctype \
    curl \
    dom \
    fileinfo \
    intl \
    mbstring \
    openssl \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    sqlite3 \
    tokenizer \
    xml \
    zip

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    APP_DEBUG=false

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chmod -R 775 storage bootstrap/cache \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views

COPY Caddyfile /etc/caddy/Caddyfile

EXPOSE 8080

ENTRYPOINT ["sh", "/app/docker-entrypoint.sh"]
