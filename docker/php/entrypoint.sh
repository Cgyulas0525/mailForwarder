#!/bin/sh
set -e
cd /var/www

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache storage/app/private
chown -R www-data:www-data storage bootstrap/cache || true

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ -z "${APP_KEY:-}" ]; then
  unset APP_KEY
  if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --ansi
  fi
fi

exec "$@"
