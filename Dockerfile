# syntax=docker/dockerfile:1
#
# Production image only. Local development stays on Laravel Sail - see
# `php artisan sail:install`. Building this produces two things from the
# `build` stage below: a `app` (PHP-FPM) image and a `nginx` image, selected
# via `docker compose --file docker-compose.prod.yml build` (see that file's
# `target:` per service).

########################################################################
# 1. build - installs Composer + npm dependencies and compiles the
#    Vite/Inertia frontend. Nothing from this stage ships in the final
#    images below except the `vendor/` and `public/build/` it produces.
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
# 2. app - a slim PHP-FPM runtime with only the compiled application.
########################################################################
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache postgresql-libs \
    && curl -sSf https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql mbstring bcmath opcache pcntl \
    && rm /usr/local/bin/install-php-extensions

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY . .
COPY --from=build /app/vendor ./vendor
COPY --from=build /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

########################################################################
# 3. nginx - serves static files and proxies PHP requests to `app:9000`.
#    Built from the same compiled public/ as the app stage above.
########################################################################
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/public /var/www/html/public
