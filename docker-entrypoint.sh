#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env

    if [ -z "$APP_KEY" ]; then
        php artisan key:generate --force
    fi
fi

# Render génère APP_KEY comme un secret base64 de 256 bits SANS le préfixe
# "base64:" exigé par Laravel (ex: "B0jrphAPOY7pg92AN0c9MN4yecczLMdwnx4OkA1KFUk=").
# Sans le préfixe, Laravel refuse la clé (Wrong key length) => 500 sur /up
# => le healthcheck Render échoue => déploiement "Timed Out".
case "$APP_KEY" in
    base64:*) ;;
    *)
        if [ "${#APP_KEY}" -gt 32 ]; then
            APP_KEY="base64:${APP_KEY}"
            export APP_KEY
        fi
        ;;
esac

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan storage:link --no-interaction || true

exec frankenphp run --config /etc/caddy/Caddyfile
