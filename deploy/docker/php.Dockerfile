# Sijilna CRM -- production PHP-FPM image.
#
# BUILD CONTEXT IS THE REPOSITORY ROOT, not this directory: deploy/docker-compose.yml
# passes `context: ..`, so every path below is relative to the checkout root and
# .dockerignore decides what is sent. Build it from the checkout root with:
#
#     docker compose -f deploy/docker-compose.yml build app
#
# The front-end assets are already committed under public/build and
# public/{admin,installer,webform}/build, so this image has no Node step.
#
FROM php:8.3-fpm-bookworm

# Composer refuses to run as root without this, and a build is always root.
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

# --- OS libraries + PHP extensions -------------------------------------------
#
# What the official 8.3 image already ships (verified with
# `docker run --rm php:8.3-fpm-bookworm php -m`): Core, ctype, curl, date, dom,
# fileinfo, filter, hash, iconv, json, libxml, mbstring, mysqlnd, openssl, pcre,
# PDO, pdo_sqlite, Phar, posix, random, readline, Reflection, session,
# SimpleXML, sodium, SPL, sqlite3, standard, tokenizer, xml, xmlreader,
# xmlwriter, Zend OPcache, zlib. So curl, mbstring and the dom/xml family need
# nothing. What is missing and this app needs:
#
#   bcmath    -- money/decimal arithmetic in Webkul\Core
#   calendar  -- HARD requirement of khaled.alshamaa/ar-php (composer.lock), which
#                drives the Arabic/Hijri helpers; `composer install` fails
#                outright without it
#   gd        -- mpdf and phpspreadsheet (PDF exports, Excel import/export)
#   intl      -- NumberFormatter for currency formatting in Webkul\Core
#   pdo_mysql -- the MySQL driver; the base image ships mysqlnd but no PDO driver
#   zip       -- phpspreadsheet and webklex/php-imap
#
# The -dev packages below are kept installed on purpose: purging libicu-dev with
# --auto-remove also removes the runtime libicu that the freshly built intl.so
# links against. The extra ~40 MB is the price of not shipping a broken image.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        unzip; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        calendar \
        gd \
        intl \
        pdo_mysql \
        zip; \
    rm -rf /var/lib/apt/lists/*

# Composer 2, from the official image, so the host needs no composer install.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Application --------------------------------------------------------------
WORKDIR /var/www/html

# The whole context, filtered by .dockerignore: vendor/, .env, storage/ runtime
# state and the tooling directories are excluded there.
COPY . .

# --no-dev drops Pest, Pint, Debugbar, Faker and the package generator. Nothing on
# a request path or in the installer's seeders uses a require-dev package, and
# composer.lock already pins the whole platform, so the production dependency set
# is fully determined by the lock file.
#
# --no-scripts because package discovery is deferred to the entrypoint: it needs
# .env, which only exists as a bind mount at run time. The entrypoint runs
# `php artisan package:discover` after the database is reachable.
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

# Runtime directories. storage/ and bootstrap/cache are bind-mounted from the
# host in production, so these are the fallback for a container run without the
# mounts (and they are what makes the paths exist before the first chown).
#
# config/ is chowned because krayin-crm:install runs `vendor:publish --force` for
# CoreServiceProvider during the first-boot install, and the entrypoint runs
# artisan as www-data. The three files it copies (concord.php, cors.php,
# sanctum.php) are already committed and identical, but a publish that cannot
# write is noise in the log and a trap for the next person.
#
# `public` itself is chowned, not its contents: the entrypoint runs artisan as
# www-data and `storage:link` has to create public/storage inside this directory.
# Copying up ~35 MB of public assets to change their owner would double the layer.
RUN set -eux; \
    mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache config; \
    chown www-data:www-data public

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY deploy/docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-pool.conf

# Copied, then chmodded inside the image: this checkout is on an NTFS mount where
# every file reports mode 0777 and git's filemode is off, so the executable bit
# cannot travel through git.
COPY deploy/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

# The base image's CMD is `php-fpm`; the entrypoint execs it so it stays PID 1
# and receives SIGTERM from `docker compose stop`.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
