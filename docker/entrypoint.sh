#!/bin/sh
# Entrypoint for app / worker / test containers.
#   WAIT_FOR_DB=true   wait for MySQL (default true when DB_CONNECTION=mysql)
#   RUN_MIGRATIONS=true  run `artisan migrate --force` (set on ONE service only: app)
#   APP_ENV=production   cache config/routes/views/events
set -eu
cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public storage/app/private bootstrap/cache

# Running as root only happens in dev on hosts where bind mounts don't honor the image's UID/GID
# build args (see docker-compose.override.yml). php-fpm's pool still runs workers as www-data
# (never customized away from the base image default), so hand the writable dirs to that user
# or every view/cache/session write 500s with "tempnam(): file created in the system's temporary
# directory". Production builds these as `app` at image build time and never run as root here.
if [ "$(id -u)" = "0" ]; then
  chown -R www-data:www-data storage bootstrap/cache
fi

if [ "${DB_CONNECTION:-}" = "mysql" ] && [ "${WAIT_FOR_DB:-true}" = "true" ]; then
  echo "entrypoint: waiting for database ${DB_HOST:-db}:${DB_PORT:-3306}"
  i=0
  until php -r '
    try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT")?:3306).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); }
    catch (Throwable $e) { exit(1); }' 2>/dev/null; do
    i=$((i + 1))
    [ "$i" -ge 60 ] && { echo "entrypoint: database not reachable" >&2; exit 1; }
    sleep 2
  done
fi

if [ ! -f vendor/autoload.php ] && command -v composer >/dev/null 2>&1; then
  echo "entrypoint: vendor/ missing, running composer install (dev)"
  composer install --prefer-dist --no-interaction
fi

if [ -f artisan ]; then
  [ -e public/storage ] || php artisan storage:link >/dev/null 2>&1 || true

  if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
  else
    php artisan config:clear >/dev/null 2>&1 || true
  fi

  if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
  fi
fi

exec "$@"
