#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

exec frankenphp run --config /etc/caddy/Caddyfile
