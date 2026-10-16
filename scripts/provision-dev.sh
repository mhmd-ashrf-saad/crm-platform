#!/usr/bin/env bash
#
# provision-dev.sh - unattended, idempotent local setup for this Krayin CRM checkout.
#
# IDEMPOTENCY (the important contract): re-running this on an already-provisioned checkout is safe
# and changes no data. Every step creates only what is missing --
#   * the MySQL container is reused (and merely started) when it already exists,
#   * .env is only copied from .env.example when absent,
#   * on the CREATE path -- this run creates .env, or this run creates the container -- the script
#     owns the whole connection block and upserts DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE,
#     DB_USERNAME, DB_PASSWORD, APP_URL and a per-instance SESSION_COOKIE from its own values. It
#     never falls back to set-if-unset: .env.example ships a value for every one of those keys, so
#     set-if-unset would keep the example's placeholders while the container is created from the
#     script's values, and the application and the container would disagree about user, port and
#     database name,
#   * on the REUSE path -- .env and the container both already existed -- .env is not modified at
#     all. The container's real published state is read back with `docker inspect` and every
#     disagreement among DB_HOST, DB_PORT, DB_DATABASE and DB_USERNAME is reported as a warning,
#   * APP_KEY is generated only when it is empty,
#   * the schema installer -- which internally runs `migrate:fresh`, i.e. DROPS EVERY TABLE -- is
#     skipped whenever storage/installed exists,
#   * the admin account is created only on a fresh install -- no user rows yet, or the schema
#     installer is about to wipe and reseed them; an instance that already has users keeps its admin,
#   * the dev server is not started twice on a port that is already serving.
# Pass --reinstall to deliberately re-run the destructive installer (wipes the database).
#
# Usage: bash scripts/provision-dev.sh [--port 8000] [--reinstall] [--check-env]
#
# --check-env resolves and prints the resolved action and value for the whole connection block
# (DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL) plus the
# per-instance SESSION_COOKIE, then exits without writing anything; it is the dry run for the rules
# described above.
#
# Environment overrides (defaults in brackets) - optional, except the admin pair on a fresh install:
#   KRAYIN_APP_PORT           port for `artisan serve`                    [8000]
#   KRAYIN_DB_CONTAINER       MySQL container name                        [crm-platform-mysql]
#   KRAYIN_DB_VOLUME          NAMED docker volume for /var/lib/mysql      [crm-platform-mysql-data]
#   KRAYIN_DB_HOST_PORT       host port published for MySQL, bound to 127.0.0.1 only [3306]
#   KRAYIN_DB_NAME            database name                               [krayin]
#   KRAYIN_DB_USER            application database user                   [same as KRAYIN_DB_NAME]
#   KRAYIN_SESSION_COOKIE     session cookie name (create path only)      [krayin_crm_session_<slug of KRAYIN_DB_NAME>]
#   KRAYIN_DB_ROOT_PASSWORD   MySQL root password; generated if unset and persisted to .env
#   KRAYIN_APP_URL            explicit APP_URL for the create path  [http://127.0.0.1:$PORT]
#   KRAYIN_ADMIN_NAME         admin display name [Mohamed Ashraf]; passed to create-admin.php
#   KRAYIN_ADMIN_EMAIL        REQUIRED on a fresh install - admin login email, NO default
#   KRAYIN_ADMIN_PASSWORD     REQUIRED on a fresh install - admin password (>= 8 chars), NO default
#
set -euo pipefail

# Project root, derived from this script's own location (scripts/..), so the checkout works from
# any path -- this machine, a client machine, or after the tree has been moved.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

PORT="${KRAYIN_APP_PORT:-8000}"
REINSTALL=0
CHECK_ENV=0
DB_DATABASE_ACTION=""
RESOLVED_DB_DATABASE=""

while [ $# -gt 0 ]; do
    case "$1" in
        --port) PORT="${2:?--port needs a value}"; shift 2 ;;
        --reinstall) REINSTALL=1; shift ;;
        --check-env) CHECK_ENV=1; shift ;;
        -h|--help) awk 'NR > 1 { if ($0 !~ /^#/) exit; print }' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "provision-dev: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
done

DB_CONTAINER="${KRAYIN_DB_CONTAINER:-crm-platform-mysql}"
DB_VOLUME="${KRAYIN_DB_VOLUME:-crm-platform-mysql-data}"
DB_HOST_PORT="${KRAYIN_DB_HOST_PORT:-3306}"
DB_NAME="${KRAYIN_DB_NAME:-krayin}"
# The application user defaults to the database name, not to a fixed 'krayin': two installs that
# share one MySQL server must not both be granted the same user. With the default DB_NAME=krayin the
# user stays 'krayin', so nothing changes for the existing instance.
DB_USER="${KRAYIN_DB_USER:-$DB_NAME}"
ENV_FILE="$PROJECT_ROOT/.env"

# Per-instance session cookie name. Browsers scope cookies by host and ignore the port, so two
# installs served from 127.0.0.1:8000 and 127.0.0.1:8001 share one cookie namespace: logging into
# the second would silently log you out of the first. The default is derived from this instance's
# own database name, so separate databases get separate cookie names. config/session.php reads the
# name from env('SESSION_COOKIE'). Write-path only -- an existing instance keeps its cookie name.
session_slug() {
    printf '%s' "$1" | tr '[:upper:]' '[:lower:]' \
        | sed -e 's/[^a-z0-9]\+/_/g' -e 's/^_*//' -e 's/_*$//'
}
SESSION_COOKIE_DEFAULT="krayin_crm_session_$(session_slug "$DB_NAME")"
SESSION_COOKIE_VALUE="${KRAYIN_SESSION_COOKIE:-$SESSION_COOKIE_DEFAULT}"

step() { printf '\n==> %s\n' "$1"; }

# --------------------------------------------------------------- environment helpers
# Defined before any provisioning runs so `--check-env` can resolve the database name on its own.

read_env() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

upsert_env() {
    local key="$1" value="$2" esc
    esc="$(printf '%s' "$value" | sed -e 's/[\\&|]/\\&/g')"
    if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i -E "s|^${key}=.*|${key}=${esc}|" "$ENV_FILE"
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

# 1 when the container already exists, 0 when this run is the one that would create it.
container_exists() {
    if docker inspect "$DB_CONTAINER" >/dev/null 2>&1; then printf 1; else printf 0; fi
}

# The host port the container really publishes for 3306, straight from docker inspect. Empty when
# the container has no such mapping, or when docker cannot inspect it at all.
container_published_port() {
    docker inspect "$DB_CONTAINER" \
        --format '{{with (index .NetworkSettings.Ports "3306/tcp")}}{{(index . 0).HostPort}}{{end}}' \
        2>/dev/null | head -1 || true
}

# A MYSQL_* value the container was created with, e.g. MYSQL_DATABASE or MYSQL_USER. Empty when the
# container is not inspectable or does not carry the variable.
container_env() {
    docker inspect "$DB_CONTAINER" \
        --format '{{range .Config.Env}}{{println .}}{{end}}' 2>/dev/null \
        | grep -E "^$1=" | tail -1 | cut -d= -f2- || true
}

# Resolve the whole application connection block for this run.
#
# This is deliberately not a set-if-unset: .env.example ships a value for DB_CONNECTION, DB_HOST,
# DB_PORT, DB_DATABASE, DB_USERNAME and APP_URL, so set-if-unset would never write the script's
# value and the application would keep the example's placeholders while the container is created
# from the script's own values -- an application and a database that disagree about user, port and
# name. On the create path the script therefore owns the block; on the reuse path .env belongs to
# the running instance and is only read.
#
# Arguments: $1 = 1 when .env was created from .env.example in this run, $2 = container_exists.
# Everything it sets is a global on purpose: calling it through `$(...)` would run it in a subshell
# and lose every value.
#   CONNECTION_ACTION       create|reuse
#   RESOLVED_DB_CONNECTION  DB_CONNECTION to use
#   RESOLVED_DB_HOST / RESOLVED_DB_PORT / RESOLVED_DB_DATABASE / RESOLVED_DB_USERNAME
#   RESOLVED_DB_PASSWORD    the app-user password to use; empty means "generate one" on the create
#                           path (the caller generates and persists it)
#   RESOLVED_APP_URL        APP_URL to use
#   RESOLVED_SESSION_COOKIE SESSION_COOKIE to use; create path only writes it, reuse path reports
#                           whatever the live .env already has (usually unset)
#   CONNECTION_MISMATCHES   reuse path only: one human-readable line per disagreeing key
#   CONTAINER_REAL_HOST / _PORT / _DATABASE / _USERNAME
#                           reuse path only: the container's inspectable state, i.e. the source of
#                           truth the comparison is made against
#   DB_DATABASE_ACTION      upsert|keep|warn -- the DB_DATABASE half of the same decision, kept for
#                           the --check-env output
resolve_connection_block() {
    local env_created_flag="$1" container_exists_flag="$2" real_ip

    CONNECTION_MISMATCHES=()
    RESOLVED_DB_PASSWORD="$(read_env DB_PASSWORD || true)"

    if [ "$env_created_flag" -eq 1 ] || [ "$container_exists_flag" -ne 1 ]; then
        # CREATE path: this run creates .env, or it creates the container (or both), so it owns the
        # block and writes all of it.
        CONNECTION_ACTION=create
        RESOLVED_DB_CONNECTION=mysql
        RESOLVED_DB_HOST=127.0.0.1
        RESOLVED_DB_PORT="$DB_HOST_PORT"
        RESOLVED_DB_DATABASE="$DB_NAME"
        RESOLVED_DB_USERNAME="$DB_USER"
        RESOLVED_SESSION_COOKIE="$SESSION_COOKIE_VALUE"
        # An explicit KRAYIN_APP_URL wins; otherwise serve exactly the URL this run starts.
        RESOLVED_APP_URL="${KRAYIN_APP_URL:-http://127.0.0.1:$PORT}"
        DB_DATABASE_ACTION=upsert
        return 0
    fi

    # REUSE path: .env and the container both pre-existed, so this is a live instance. .env belongs
    # to it and is not rewritten; the container's real, inspectable state is the reference.
    CONNECTION_ACTION=reuse
    RESOLVED_DB_CONNECTION="$(read_env DB_CONNECTION || true)"
    RESOLVED_DB_HOST="$(read_env DB_HOST || true)"
    RESOLVED_DB_PORT="$(read_env DB_PORT || true)"
    RESOLVED_DB_DATABASE="$(read_env DB_DATABASE || true)"
    RESOLVED_DB_USERNAME="$(read_env DB_USERNAME || true)"
    RESOLVED_APP_URL="$(read_env APP_URL || true)"
    RESOLVED_SESSION_COOKIE="$(read_env SESSION_COOKIE || true)"

    CONTAINER_REAL_PORT="$(container_published_port)"
    CONTAINER_REAL_DATABASE="$(container_env MYSQL_DATABASE)"
    CONTAINER_REAL_DATABASE="${CONTAINER_REAL_DATABASE:-$DB_NAME}"
    CONTAINER_REAL_USERNAME="$(container_env MYSQL_USER)"
    CONTAINER_REAL_USERNAME="${CONTAINER_REAL_USERNAME:-$DB_USER}"
    real_ip="$(docker inspect "$DB_CONTAINER" \
        --format '{{with (index .NetworkSettings.Ports "3306/tcp")}}{{(index . 0).HostIp}}{{end}}' \
        2>/dev/null | head -1 || true)"
    case "$real_ip" in
        ""|0.0.0.0|::) CONTAINER_REAL_HOST=127.0.0.1 ;;
        *)            CONTAINER_REAL_HOST="$real_ip" ;;
    esac

    if [ -z "$CONTAINER_REAL_PORT" ]; then
        CONNECTION_MISMATCHES+=("DB_PORT: container '$DB_CONTAINER' publishes no host port for 3306, .env has '$RESOLVED_DB_PORT'")
    elif [ "$RESOLVED_DB_PORT" != "$CONTAINER_REAL_PORT" ]; then
        CONNECTION_MISMATCHES+=("DB_PORT: .env has '$RESOLVED_DB_PORT', container publishes '$CONTAINER_REAL_PORT'")
    fi
    [ "$RESOLVED_DB_HOST" = "$CONTAINER_REAL_HOST" ] || \
        CONNECTION_MISMATCHES+=("DB_HOST: .env has '$RESOLVED_DB_HOST', container is published on '$CONTAINER_REAL_HOST'")
    [ "$RESOLVED_DB_DATABASE" = "$CONTAINER_REAL_DATABASE" ] || \
        CONNECTION_MISMATCHES+=("DB_DATABASE: .env has '$RESOLVED_DB_DATABASE', container was created with MYSQL_DATABASE='$CONTAINER_REAL_DATABASE'")
    [ "$RESOLVED_DB_USERNAME" = "$CONTAINER_REAL_USERNAME" ] || \
        CONNECTION_MISMATCHES+=("DB_USERNAME: .env has '$RESOLVED_DB_USERNAME', container was created with MYSQL_USER='$CONTAINER_REAL_USERNAME'")

    if [ "$RESOLVED_DB_DATABASE" = "$CONTAINER_REAL_DATABASE" ]; then
        DB_DATABASE_ACTION=keep
    else
        DB_DATABASE_ACTION=warn
    fi
}

# keep/warn verdict for one key of the reuse-path comparison.
key_verdict() { if [ "$1" = "$2" ]; then printf keep; else printf warn; fi; }

# keep only when the container really publishes the port .env names.
port_verdict() { if [ -n "$2" ] && [ "$1" = "$2" ]; then printf keep; else printf warn; fi; }


if [ "$CHECK_ENV" -eq 1 ]; then
    if [ -f "$ENV_FILE" ]; then CHECK_ENV_CREATED=0; else CHECK_ENV_CREATED=1; fi
    CHECK_ENV_CONTAINER="$(container_exists)"
    resolve_connection_block "$CHECK_ENV_CREATED" "$CHECK_ENV_CONTAINER"
    printf 'check-env: project_root=%s\n' "$PROJECT_ROOT"
    printf 'check-env: env_file=%s .env_would_be_created_from_example=%s\n' "$ENV_FILE" "$CHECK_ENV_CREATED"
    printf 'check-env: container=%s exists=%s\n' "$DB_CONTAINER" "$CHECK_ENV_CONTAINER"
    printf 'check-env: KRAYIN_DB_NAME=%s KRAYIN_DB_USER=%s KRAYIN_DB_HOST_PORT=%s KRAYIN_APP_PORT=%s\n' \
        "$DB_NAME" "$DB_USER" "$DB_HOST_PORT" "$PORT"
    printf 'check-env: action=%s\n' "$CONNECTION_ACTION"
    if [ "$CONNECTION_ACTION" = "create" ]; then
        printf 'check-env: DB_CONNECTION=%s action=upsert\n' "$RESOLVED_DB_CONNECTION"
        printf 'check-env: DB_HOST=%s action=upsert\n' "$RESOLVED_DB_HOST"
        printf 'check-env: DB_PORT=%s action=upsert\n' "$RESOLVED_DB_PORT"
        printf 'check-env: DB_DATABASE=%s action=upsert\n' "$RESOLVED_DB_DATABASE"
        printf 'check-env: DB_USERNAME=%s action=upsert\n' "$RESOLVED_DB_USERNAME"
        printf 'check-env: SESSION_COOKIE=%s action=upsert\n' "$RESOLVED_SESSION_COOKIE"
        if [ -n "$RESOLVED_DB_PASSWORD" ]; then
            printf 'check-env: DB_PASSWORD=action=keep value=<set>\n'
        else
            printf 'check-env: DB_PASSWORD=action=generate value=<generated at run time>\n'
        fi
        printf 'check-env: APP_URL=%s action=upsert\n' "$RESOLVED_APP_URL"
    else
        printf 'check-env: container_real_host=%s real_port=%s real_database=%s real_username=%s\n' \
            "$CONTAINER_REAL_HOST" "${CONTAINER_REAL_PORT:-<none>}" "$CONTAINER_REAL_DATABASE" "$CONTAINER_REAL_USERNAME"
        printf 'check-env: DB_CONNECTION=%s action=keep\n' "$RESOLVED_DB_CONNECTION"
        printf 'check-env: DB_HOST=%s action=%s\n' "$RESOLVED_DB_HOST" "$(key_verdict "$RESOLVED_DB_HOST" "$CONTAINER_REAL_HOST")"
        printf 'check-env: DB_PORT=%s action=%s\n' "$RESOLVED_DB_PORT" "$(port_verdict "$RESOLVED_DB_PORT" "$CONTAINER_REAL_PORT")"
        printf 'check-env: DB_DATABASE=%s action=%s\n' "$RESOLVED_DB_DATABASE" "$(key_verdict "$RESOLVED_DB_DATABASE" "$CONTAINER_REAL_DATABASE")"
        printf 'check-env: DB_USERNAME=%s action=%s\n' "$RESOLVED_DB_USERNAME" "$(key_verdict "$RESOLVED_DB_USERNAME" "$CONTAINER_REAL_USERNAME")"
        printf 'check-env: SESSION_COOKIE=%s action=keep\n' "${RESOLVED_SESSION_COOKIE:-<unset - framework default from APP_NAME>}"
        if [ -n "$RESOLVED_DB_PASSWORD" ]; then
            printf 'check-env: DB_PASSWORD=action=keep value=<set>\n'
        else
            printf 'check-env: DB_PASSWORD=action=keep value=<unset>\n'
        fi
        printf 'check-env: APP_URL=%s action=keep\n' "$RESOLVED_APP_URL"
        if [ "${#CONNECTION_MISMATCHES[@]}" -gt 0 ]; then
            printf 'check-env: mismatch:\n'
            printf 'check-env:   %s\n' "${CONNECTION_MISMATCHES[@]}"
        fi
    fi
    printf 'check-env: DB_DATABASE_action=%s DB_DATABASE=%s\n' "$DB_DATABASE_ACTION" "$RESOLVED_DB_DATABASE"
    printf 'check-env: read-only - nothing was written\n'
    exit 0
fi

# ---------------------------------------------------------------- prerequisites
step "Checking prerequisites"

missing=()
have() { command -v "$1" >/dev/null 2>&1; }

if ! have php; then
    missing+=("php >= 8.3 (no 'php' on PATH)")
elif ! php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    missing+=("php >= 8.3 (found $(php -r 'echo PHP_VERSION;'))")
fi

# Extensions required by composer's platform requirements for the installed dependency set, plus
# pdo_mysql (Laravel's MySQL driver) and intl (Webkul\Core uses NumberFormatter for currency).
REQUIRED_EXT=(calendar ctype curl date dom fileinfo filter gd hash iconv json libxml mbstring
    openssl pcre phar pdo_mysql reflection session simplexml tokenizer xml xmlreader xmlwriter zip
    zlib intl)

if have php; then
    loaded="$(php -m)"
    for ext in "${REQUIRED_EXT[@]}"; do
        printf '%s\n' "$loaded" | grep -qix "$ext" || missing+=("php extension: $ext")
    done
fi

have composer || missing+=("composer")
have docker   || missing+=("docker")
have node     || missing+=("node")

if have docker && ! docker info >/dev/null 2>&1; then
    missing+=("docker daemon (docker is installed but not reachable - is it running?)")
fi

if [ "${#missing[@]}" -gt 0 ]; then
    echo "provision-dev: cannot continue, missing prerequisites:" >&2
    printf '  - %s\n' "${missing[@]}" >&2
    echo "Install the above and re-run." >&2
    exit 1
fi

echo "  php $(php -r 'echo PHP_VERSION;'), composer $(composer -V | awk '{print $3}'), docker $(docker -v | awk '{print $3}' | tr -d ,), node $(node -v)"
echo "  note: composer.json pins platform php to 8.3.30, so a newer runtime is fine. Plain 'composer"
echo "        check-platform-reqs' reports phpoffset/phpspreadsheet's '< 8.5' bound as failed here;"
echo "        that is a platform-pin artifact, not a broken checkout - see scripts/README.md."

# ------------------------------------------------------------------------- .env
step "Environment file"

ENV_CREATED=0
if [ -f "$ENV_FILE" ]; then
    echo "  .env exists - leaving it in place"
else
    cp "$PROJECT_ROOT/.env.example" "$ENV_FILE"
    ENV_CREATED=1
    echo "  created .env from .env.example"
fi

CONTAINER_EXISTS="$(container_exists)"
resolve_connection_block "$ENV_CREATED" "$CONTAINER_EXISTS"

if [ "$CONNECTION_ACTION" = "create" ]; then
    # On this path the script owns .env's connection block. .env.example ships a value for every key
    # below, so a set-if-unset would keep the example's placeholders while the container is created
    # from this run's values, and the application would disagree with the database about user, port
    # and name. A password is generated only when there is none to keep.
    if [ -z "$RESOLVED_DB_PASSWORD" ]; then
        RESOLVED_DB_PASSWORD="$(php -r 'echo bin2hex(random_bytes(16));')"
        echo "  generated a database password for user '$RESOLVED_DB_USERNAME' and stored it in .env (DB_PASSWORD)"
    fi
    upsert_env DB_CONNECTION "$RESOLVED_DB_CONNECTION"
    upsert_env DB_HOST "$RESOLVED_DB_HOST"
    upsert_env DB_PORT "$RESOLVED_DB_PORT"
    upsert_env DB_DATABASE "$RESOLVED_DB_DATABASE"
    upsert_env DB_USERNAME "$RESOLVED_DB_USERNAME"
    upsert_env DB_PASSWORD "$RESOLVED_DB_PASSWORD"
    upsert_env APP_URL "$RESOLVED_APP_URL"
    upsert_env SESSION_COOKIE "$RESOLVED_SESSION_COOKIE"
    echo "  connection block written from this run's values (the container is created from the same ones):"
    echo "    DB_CONNECTION=$RESOLVED_DB_CONNECTION DB_HOST=$RESOLVED_DB_HOST DB_PORT=$RESOLVED_DB_PORT"
    echo "    DB_DATABASE=$RESOLVED_DB_DATABASE DB_USERNAME=$RESOLVED_DB_USERNAME DB_PASSWORD=<set>"
    echo "    APP_URL=$RESOLVED_APP_URL"
    echo "    SESSION_COOKIE=$RESOLVED_SESSION_COOKIE (per-instance, so two installs on one host do not share a session)"
else
    # REUSE path: .env and the container both pre-existed, so this is a live instance. .env is left
    # byte-for-byte as it is; the container's real published state is what it is compared against,
    # and a disagreement is reported rather than silently rewritten.
    if [ "${#CONNECTION_MISMATCHES[@]}" -eq 0 ]; then
        echo "  .env agrees with container '$DB_CONTAINER' on DB_HOST, DB_PORT, DB_DATABASE and DB_USERNAME - untouched"
    else
        echo "provision-dev: warning: .env and container '$DB_CONTAINER' disagree:" >&2
        printf '  - %s\n' "${CONNECTION_MISMATCHES[@]}" >&2
        echo "  Leaving .env byte-for-byte untouched (existing instance). The application keeps using" >&2
        echo "  .env's values; change them deliberately, or rebuild the container, if the two are meant" >&2
        echo "  to match." >&2
    fi
fi

DB_PASSWORD="$(read_env DB_PASSWORD || true)"

# ----------------------------------------------------------------------- MySQL
step "MySQL container (named volume: $DB_VOLUME)"

if [ "$CONTAINER_EXISTS" -eq 1 ]; then
    echo "  container '$DB_CONTAINER' already exists - reusing it, not recreating"

    mounts="$(docker inspect "$DB_CONTAINER" --format '{{range .Mounts}}{{.Type}} {{.Name}} -> {{.Destination}}{{end}}')"
    if ! printf '%s' "$mounts" | grep -q "volume $DB_VOLUME -> /var/lib/mysql"; then
        echo "provision-dev: '$DB_CONTAINER' is not backed by the named volume '$DB_VOLUME':" >&2
        echo "  $mounts" >&2
        echo "  Refusing to touch it. Rebuild it deliberately if that is what you want:" >&2
        echo "    docker rm -f $DB_CONTAINER && docker run -d --name $DB_CONTAINER --restart unless-stopped \\" >&2
        echo "      -v $DB_VOLUME:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=... \\" >&2
        echo "      -e MYSQL_DATABASE=$DB_NAME -e MYSQL_USER=$DB_USER -e MYSQL_PASSWORD=... \\" >&2
        echo "      -p 127.0.0.1:$DB_HOST_PORT:3306 mysql:8.4" >&2
        exit 1
    fi

    if [ "$(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER")" != "true" ]; then
        echo "  container is stopped - starting it"
        docker start "$DB_CONTAINER" >/dev/null
    fi
else
    # The root password is fixed when the data directory is first initialised and cannot be read back
    # out of an existing container, so it is only resolved here, on the create path. Generating one
    # for a container that already exists would write a value into .env that does not work.
    if [ -n "${KRAYIN_DB_ROOT_PASSWORD:-}" ]; then
        DB_ROOT_PASSWORD="$KRAYIN_DB_ROOT_PASSWORD"
    elif [ -n "$(read_env DB_ROOT_PASSWORD)" ]; then
        DB_ROOT_PASSWORD="$(read_env DB_ROOT_PASSWORD)"
    else
        DB_ROOT_PASSWORD="$(php -r 'echo bin2hex(random_bytes(16));')"
        upsert_env DB_ROOT_PASSWORD "$DB_ROOT_PASSWORD"
        echo "  generated a MySQL root password and stored it in .env (DB_ROOT_PASSWORD); the app does not read it"
    fi

    echo "  creating container with a fresh named volume (first init takes a few seconds)"
    docker run -d --name "$DB_CONTAINER" --restart unless-stopped \
        -v "$DB_VOLUME:/var/lib/mysql" \
        -e MYSQL_ROOT_PASSWORD="$DB_ROOT_PASSWORD" \
        -e MYSQL_DATABASE="$DB_NAME" \
        -e MYSQL_USER="$DB_USER" \
        -e MYSQL_PASSWORD="$DB_PASSWORD" \
        -p "127.0.0.1:$DB_HOST_PORT:3306" mysql:8.4 >/dev/null
fi

echo -n "  waiting for MySQL to accept the application login"
ready=0
for _ in $(seq 1 60); do
    if docker exec -e MYSQL_PWD="$DB_PASSWORD" "$DB_CONTAINER" \
        mysql -u"$DB_USER" -N -e 'select 1' "$DB_NAME" >/dev/null 2>&1; then
        ready=1
        break
    fi
    printf '.'
    sleep 2
done
echo

if [ "$ready" -ne 1 ]; then
    echo "provision-dev: MySQL in '$DB_CONTAINER' did not accept the application login within 120s." >&2
    echo "  Recent container log:" >&2
    docker logs --tail 20 "$DB_CONTAINER" >&2 || true
    exit 1
fi
echo "  MySQL ready"

# ------------------------------------------------------------ admin account policy
# Whether this run may create the administrator is decided HERE, before the schema step:
# `krayin-crm:install` runs `migrate:fresh` and then seeds a placeholder `admin@example.com` row, so
# a count taken afterwards cannot tell a fresh install from a provisioned one. Re-running this
# script on an instance that already has users must never touch its admin account -- an earlier
# revision reset the password on every pass, silently locking the real owner out.
# The application's own database name wins when .env names one, exactly as for DB_PORT above: that
# is where the `users` table this decision is about actually lives, which is not necessarily the
# container's MYSQL_DATABASE.
APP_DB_NAME="$(read_env DB_DATABASE || true)"
APP_DB_NAME="${APP_DB_NAME:-$DB_NAME}"

db_value() {
    docker exec -e MYSQL_PWD="$DB_PASSWORD" "$DB_CONTAINER" \
        mysql -u"$DB_USER" -N -B -e "$1" "$APP_DB_NAME" 2>/dev/null | head -1
}

USERS_BEFORE="$(db_value 'select count(*) from users' || true)"
printf '%s' "$USERS_BEFORE" | grep -qE '^[0-9]+$' || USERS_BEFORE=0

# The admin account has to be created exactly when this run is the one that produces the `users`
# table: nothing there yet, or the destructive installer is about to wipe and reseed it.
if [ ! -f "$PROJECT_ROOT/storage/installed" ] || [ "$REINSTALL" -eq 1 ] || [ "$USERS_BEFORE" -eq 0 ]; then
    NEED_ADMIN=1
else
    NEED_ADMIN=0
fi

echo "  $USERS_BEFORE row(s) in '$APP_DB_NAME'.users before provisioning"

if [ "$NEED_ADMIN" -eq 1 ] && { [ -z "${KRAYIN_ADMIN_EMAIL:-}" ] || [ -z "${KRAYIN_ADMIN_PASSWORD:-}" ]; }; then
    cat >&2 <<'EOF'
provision-dev: this is a fresh install and no administrator credential was supplied.

  A client instance needs its own admin account, so this script has no default email and no default
  password and will not pick one for you. Both variables are required for a fresh install:
    KRAYIN_ADMIN_EMAIL     the admin's login email
    KRAYIN_ADMIN_PASSWORD  the admin's password (at least 8 characters)

  Re-run with them supplied (nothing has been created yet):
    KRAYIN_ADMIN_EMAIL='admin@client.test' KRAYIN_ADMIN_PASSWORD='<chosen-password>' \
      bash scripts/provision-dev.sh
EOF
    exit 1
fi

# -------------------------------------------------------------- application
step "PHP dependencies"
composer install --no-interaction --prefer-dist

step "Application key"
if [ -z "$(read_env APP_KEY)" ]; then
    php artisan key:generate --force
    echo "  generated APP_KEY"
else
    echo "  APP_KEY already set - not regenerating it (that would invalidate encrypted data)"
fi

php artisan config:clear >/dev/null 2>&1 || true
php artisan optimize:clear >/dev/null 2>&1 || true

step "Database schema and seed data"
if [ -f "$PROJECT_ROOT/storage/installed" ] && [ "$REINSTALL" -ne 1 ]; then
    echo "  storage/installed present - skipping 'krayin-crm:install' (it would run migrate:fresh)"
else
    if [ "$REINSTALL" -eq 1 ] && [ -f "$PROJECT_ROOT/storage/installed" ]; then
        echo "  --reinstall given: the installer runs migrate:fresh and WILL ERASE the current database"
    fi
    # The interactive path cannot be scripted: Laravel\Prompts prefills its own defaults and never
    # receives EOF, so the installer would block forever. Skipping the admin prompt and creating the
    # account below is the supported unattended path - see scripts/README.md.
    php artisan krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction
fi

step "Administrator account"
if [ "$NEED_ADMIN" -eq 1 ]; then
    php "$PROJECT_ROOT/scripts/create-admin.php"
else
    echo "  skipping admin creation: $USERS_BEFORE user row(s) already exist in '$APP_DB_NAME'.users"
    echo "  and the schema is already installed, so the admin account keeps its current password."
    echo "  To change it deliberately, run that step on its own with both variables set:"
    echo "    KRAYIN_ADMIN_EMAIL=... KRAYIN_ADMIN_PASSWORD=... php scripts/create-admin.php"
fi

step "Development server"
if ss -ltn 2>/dev/null | grep -q "[:.]${PORT}[[:space:]]"; then
    echo "  something is already listening on 127.0.0.1:$PORT - not starting a second server"
else
    mkdir -p "$PROJECT_ROOT/storage/logs"
    setsid nohup php artisan serve --host=127.0.0.1 --port="$PORT" \
        > "$PROJECT_ROOT/storage/logs/serve.log" 2>&1 < /dev/null &
    echo -n "  starting 'php artisan serve' on 127.0.0.1:$PORT"
    for _ in $(seq 1 15); do
        if ss -ltn 2>/dev/null | grep -q "[:.]${PORT}[[:space:]]"; then break; fi
        printf '.'
        sleep 1
    done
    echo
fi

if ss -ltn 2>/dev/null | grep -q "[:.]${PORT}[[:space:]]"; then
    # Report the process that owns the listening socket; anchoring the pgrep pattern avoids matching
    # a shell wrapper whose command line merely contains the same text.
    pid="$(pgrep -f "^php artisan serve --host=127\.0\.0\.1 --port=${PORT}$" | head -1 || true)"
    [ -n "$pid" ] || pid="$(ss -ltnpH 2>/dev/null | grep -F "127.0.0.1:${PORT}" | grep -o 'pid=[0-9]*' | head -1 | cut -d= -f2)"
    echo "  serving on http://127.0.0.1:$PORT (pid ${pid:-unknown}); logs: storage/logs/serve.log"
else
    echo "  warning: nothing is listening on 127.0.0.1:$PORT yet - check storage/logs/serve.log" >&2
fi

echo
ADMIN_EMAIL_ACTUAL="$(db_value 'select email from users order by id limit 1' || true)"
echo "provision-dev: done. Admin: ${ADMIN_EMAIL_ACTUAL:-none created}; change it only deliberately (scripts/README.md)."
echo "Log in at http://127.0.0.1:$PORT/admin/login (setup notes: scripts/README.md)."
