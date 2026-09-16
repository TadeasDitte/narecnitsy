# syntax=docker/dockerfile:1
#
# Production image only. Local development stays on Laravel Sail - see
# `php artisan sail:install`. One image comes out of this: `app`, an
# all-in-one container running nginx + php-fpm together (via supervisord)
# on port 80. The same image is reused for the queue/hyperliquid-stream/
# migrate services in docker-compose.prod.yml - they just override the
# command to run a single artisan process instead of supervisord.

########################################################################
# 1. build - installs Composer + npm dependencies and compiles the
#    Vite/Inertia frontend. Nothing from this stage ships in the final
#    image except the `vendor/` and `public/build/` it produces.
########################################################################
FROM php:8.4-cli-alpine AS build

RUN apk add --no-cache nodejs npm git unzip $PHPIZE_DEPS postgresql-dev oniguruma-dev libzip-dev \
    && curl -sSf https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql mbstring bcmath zip \
    && rm /usr/local/bin/install-php-extensions

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# All app source is needed here (not just composer.json/package.json):
# the Vite build shells out to `artisan wayfinder:generate`, which reads
# routes/config, so the full app has to be present before `npm run build`.
COPY . .

RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

# A throwaway key is enough to let artisan boot the app below - this .env
# never leaves this stage. package:discover has to run against the actual
# --no-dev package set, since a stale bootstrap/cache/packages.php from a
# dev install would reference packages (e.g. laravel/boost) that aren't
# installed here.
RUN cp .env.example .env && php artisan key:generate --ansi
RUN php artisan package:discover --ansi

RUN npm ci
RUN npm run build

########################################################################
# 2. app - nginx + php-fpm in one container, run under supervisord.
########################################################################
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache postgresql-libs nginx supervisor \
    && curl -sSf https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql mbstring bcmath opcache pcntl \
    && rm /usr/local/bin/install-php-extensions \
    && mkdir -p /run/nginx

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY . .
COPY --from=build /app/vendor ./vendor
COPY --from=build /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

# Stays root: nginx's master process needs to bind port 80, and php-fpm's
# master process needs to be root to drop its workers to www-data itself
# (both happen automatically - supervisord just launches them).
ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]

EXPOSE 80
