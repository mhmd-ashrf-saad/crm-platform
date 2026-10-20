#!/bin/sh
#
# Sijilna CRM -- entrypoint for the `app` service (PHP-FPM container).
#
# Installed as /usr/local/bin/entrypoint.sh by deploy/docker/php.Dockerfile and
# used as the image ENTRYPOINT. It prepares a container for first boot and then
# hands PID 1 to php-fpm, so `docker compose stop` still delivers SIGTERM.
#
# Every step is idempotent: restarting the container re-runs this script and
# changes nothing that is already done. None of the steps caches configuration
# (no config:cache / route:cache) -- a cached config file silently wins over both
# .env and the shell environment, and an operator editing .env must not have to
# rebuild the image to be believed.
#
# The install path is the one scripts/provision-dev.sh uses, in the same order:
# unattended `krayin-crm:install --skip-env-check --skip-admin-creation
# --no-interaction`, then scripts/create-admin.php with the administrator taken
# from the environment. Nothing here invents a credential.
set -eu

APP_DIR=/var/www/html
PHP_USER=www-data

cd "$APP_DIR"

log() { printf '[entrypoint] %s\n' "$*"; }

# Run artisan as the same user php-fpm's workers run as. Anything artisan writes
# (storage/logs/laravel.log, storage/framework/views, bootstrap/cache) is then
# owned by www-data, so a worker can keep appending to it. Running these as root
# leaves root-owned log files that the workers cannot write, which surfaces later
# as a 500 with no error output anywhere.
run_artisan() {
    setpriv --reuid="$PHP_USER" --regid="$PHP_USER" --clear-groups php artisan "$@"
}

run_php() {
    setpriv --reuid="$PHP_USER" --regid="$PHP_USER" --clear-groups php "$@"
}

# ---------------------------------------------------------------- 0. APP_KEY
#
# .env is mounted read-only from the host, so nothing in this container can
# persist a generated key: `key:generate` would report success and leave the file
# empty, and every restart would produce a different key, silently invalidating
# sessions and every encrypted column. Fail loudly instead, with the exact
# command that fixes it.
app_key=""
if [ -f "$APP_DIR/.env" ]; then
    app_key="$(sed -n 's/^APP_KEY=//p' "$APP_DIR/.env" | tail -n 1 \
        | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tr -d '\015')"
fi
if [ -z "$app_key" ]; then
    app_key="${APP_KEY:-}"
fi
if [ -z "$app_key" ]; then
    log "FATAL: APP_KEY is empty in $APP_DIR/.env (and in the environment)."
    log "       Generate one on the host and put it in .env -- see deploy/README.md."
    log "       `php artisan key:generate --show` cannot be used there: it needs"
    log "       vendor/, which exists only inside this image. The key is 32 random"
    log "       bytes, base64-encoded, which is what Laravel's own generator emits:"
    log "         docker run --rm php:8.3-fpm-bookworm \\"
    log "           php -r 'echo \"base64:\".base64_encode(random_bytes(32));'"
    exit 1
fi
log "APP_KEY is set (not shown)"

# --------------------------------------------- 1. runtime directory ownership
#
# storage/ and bootstrap/cache are bind-mounted from the host checkout, so the
# image's ownership does not apply to them. Re-applying www-data's uid/gid here
# makes a restart fix an operator's forgotten `chown` instead of failing with a
# permission error on the first log line.
for dir in \
    storage \
    storage/app/public \
    storage/framework \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
do
    mkdir -p "$dir"
done
chown -R "$PHP_USER:$PHP_USER" storage bootstrap/cache
log "storage/ and bootstrap/cache/ are owned by $PHP_USER"

# ----------------------------------------------------- 2. wait for the database
#
# `depends_on: condition: service_healthy` already gates on MySQL, but a restart
# of a healthy-looking container or a MySQL that answers ping before it accepts
# logins still lands here. Bounded at 60 attempts x 2s.
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
attempt=1
while :; do
    if db_error="$(php -r '
        $host = $argv[1];
        $port = (int) $argv[2];
        $errno = 0;
        $errstr = "";
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($socket === false) {
            echo $errstr !== "" ? $errstr : ("error ".$errno);
            exit(1);
        }
        fclose($socket);
    ' "$DB_HOST" "$DB_PORT" 2>&1)"; then
        log "MySQL accepts TCP connections on $DB_HOST:$DB_PORT"
        break
    fi

    if [ "$attempt" -ge 60 ]; then
        log "FATAL: no TCP connection to MySQL at $DB_HOST:$DB_PORT after 60 attempts."
        log "       last error: $db_error"
        log "       Check DB_HOST/DB_PORT in .env and 'docker compose logs db'."
        exit 1
    fi

    log "waiting for MySQL at $DB_HOST:$DB_PORT (attempt $attempt/60): $db_error"
    attempt=$((attempt + 1))
    sleep 2
done

# ------------------------------------------------------- 3. package discovery
#
# Skipped by `composer install --no-scripts` in the image, because it needs .env
# and the application container only has one at run time.
log "running package:discover"
run_artisan package:discover --ansi

# ------------------------------------------------------------ 4. storage:link
if [ -e "$APP_DIR/public/storage" ] || [ -L "$APP_DIR/public/storage" ]; then
    log "public/storage already exists - skipping storage:link"
else
    log "creating the public/storage link"
    run_artisan storage:link
fi

# ---------------------------------------------------------------- 5. migrations
# Always runs: on a first boot it creates the schema that the installer below
# then rebuilds, and on every later boot it applies any migration added by a new
# image. --force is required because APP_ENV=production.
log "running migrations"
run_artisan migrate --force

# --------------------------------------------------- 6. first-boot installation
#
# storage/installed is the marker scripts/create-admin.php writes and the one
# scripts/provision-dev.sh uses to decide whether an instance exists. It lives in
# the bind-mounted storage/, so it survives image rebuilds and container
# recreations -- which is exactly the guard that stops the installer (whose
# internal step is migrate:fresh) from ever running against a live database.
if [ -f "$APP_DIR/storage/installed" ]; then
    log "storage/installed present - schema already installed, skipping the installer"
else
    log "no storage/installed marker: installing the schema (the installer runs migrate:fresh)"
    # APP_ENV=local for this one command, and not casually: krayin-crm:install
    # calls migrate:fresh, which refuses to run in the `production` environment
    # without --force, and the vendor command offers no way to forward --force.
    # The override affects only this process -- an exported variable wins over
    # .env in Laravel's immutable dotenv repository -- so the container and every
    # later command keep APP_ENV=production. Nothing here is reachable on a
    # second boot, because of the storage/installed guard above.
    APP_ENV=local run_artisan krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction

    if [ -n "${KRAYIN_ADMIN_EMAIL:-}" ] && [ -n "${KRAYIN_ADMIN_PASSWORD:-}" ]; then
        log "creating the administrator account"
        run_php scripts/create-admin.php
    else
        log "WARNING: administrator NOT created - KRAYIN_ADMIN_EMAIL and"
        log "         KRAYIN_ADMIN_PASSWORD are both required and neither was supplied."
        log "         The schema is installed, so the installer will not run again."
        log "         Create the account deliberately, on the host:"
        log "           docker compose -f deploy/docker-compose.yml exec app \\"
        log "             env KRAYIN_ADMIN_EMAIL=... KRAYIN_ADMIN_PASSWORD=... \\"
        log "             php scripts/create-admin.php"
    fi
fi

# -------------------------------------------------------------- 7. brand seeding
# The fork's own command. It only exists because packages/Sijilna/Brand is
# present; a tree without it boots normally.
if run_artisan help sijilna:brand >/dev/null 2>&1; then
    log "seeding the Sijilna brand configuration"
    run_artisan sijilna:brand
else
    log "sijilna:brand is not registered - skipping brand seeding"
fi

# ---------------------------------------------------------------- 8. php-fpm
log "starting php-fpm"
exec php-fpm
