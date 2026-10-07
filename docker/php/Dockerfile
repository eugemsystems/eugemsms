# syntax=docker/dockerfile:1
# Targets: dev (bind-mounted source, dev deps, xdebug-free), production (non-root, opcache, no dev deps), web (nginx + built public/).
ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ---------- base: PHP-FPM + every extension the app / Pest suite needs ----------
FROM php:${PHP_VERSION}-fpm-alpine AS base
ARG UID=1000
ARG GID=1000
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
# pdo_mysql: MySQL 8.4 (target DB). pdo_sqlite: phpunit.xml uses sqlite :memory:.
# redis: phpredis (pecl) so QUEUE/CACHE/SESSION=redis needs no Composer package. intl/mbstring/bcmath: Laravel, money maths. gd+exif+zip: images/uploads/archives. pcntl: queue:work signals. opcache: prod.
RUN install-php-extensions pdo_mysql pdo_sqlite mbstring bcmath intl gd exif zip pcntl opcache redis \
    && apk add --no-cache supervisor tini curl git unzip mysql-client \
    && addgroup -g ${GID} app && adduser -D -u ${UID} -G app app \
    && mkdir -p /var/www/html /var/log/supervisor /var/run/supervisor && chown -R app:app /var/www/html /var/log/supervisor /var/run/supervisor
WORKDIR /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---------- composer, production deps ----------
FROM base AS vendor
COPY composer.json composer.lock ./
COPY Modules ./Modules
# merge-plugin reads Modules/*/composer.json, so Modules/ must be present.
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------- node: build Vite assets ----------
FROM node:${NODE_VERSION}-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Needs network (Bunny font fetch in vite.config.js). vendor/ not required by the build itself.
RUN npm run build

# ---------- dev ----------
FROM base AS dev
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-app.ini
# `worker` runs supervisord from this stage too (docker-compose.override.yml builds it with target: dev).
COPY docker/supervisor /etc/supervisor
# Source is bind-mounted in dev; entrypoint runs `composer install` if vendor/ is missing. Composer stays in this image.
USER app
ENTRYPOINT ["/sbin/tini", "--", "entrypoint.sh"]
CMD ["php-fpm"]

# ---------- production ----------
FROM base AS production
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY --chown=app:app . .
COPY --from=vendor --chown=app:app /var/www/html/vendor ./vendor
COPY --from=assets --chown=app:app /app/public/build ./public/build
COPY docker/supervisor /etc/supervisor
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public storage/app/private bootstrap/cache \
    && chown -R app:app storage bootstrap/cache && rm -f /usr/bin/composer
USER app
ENTRYPOINT ["/sbin/tini", "--", "entrypoint.sh"]
CMD ["php-fpm"]
HEALTHCHECK --interval=15s --timeout=3s --retries=5 CMD php -r 'exit(@fsockopen("127.0.0.1",9000)?0:1);'

# ---------- web: nginx serving built public/ ----------
FROM nginxinc/nginx-unprivileged:alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public
EXPOSE 8080
