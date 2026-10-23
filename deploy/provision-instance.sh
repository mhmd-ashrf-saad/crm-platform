#!/usr/bin/env bash
#
# Sijilna CRM -- provision an additional client instance on the homelab
# container stack, unattended and idempotently.
#
# WHAT THIS IS FOR
#   deploy/README.md's "First deploy" is five hand-run steps. They work, and
#   they are the reason a second instance used to be a copy-paste exercise with
#   four opportunities to drift: an .env copied twice instead of symlinked, a
#   port that collides with crm.mohamedlabs.com, an APP_KEY regenerated on a
#   re-run (which invalidates every encrypted column and every session), and an
#   administrator created a second time. This script is that section, ordered,
#   with a refusal in front of each of those four.
#
# WHAT IT DOES, IN ORDER
#   1. Validate every input and derive the instance identity. Nothing is written
#      before this finishes; a missing input is a refusal, not a half-built
#      instance.
#   2. Refuse to take over a compose project, container name, volume name or
#      host port that already belongs to somebody else (see check_collisions).
#   3. Clone the checkout, or reuse the one already at the instance directory.
#      A reused checkout is never fetched, checked out or cleaned: it may be
#      running.
#   4. Write .env from deploy/.env.deploy.example -- only when it does not exist
#      yet -- with a fresh APP_KEY, fresh database passwords, APP_ENV=production,
#      APP_DEBUG=false and APP_URL for the instance hostname, then point
#      deploy/.env at it and hand it to uid 33 at mode 640.
#   5. docker compose up -d --build, then health-gate the loopback port.
#   6. Prove the instance came up in production mode with APP_DEBUG off, and
#      that an error page does not render a stack trace (crm-5lh), then report
#      every value it resolved.
#
# INPUTS ARE ENVIRONMENT VARIABLES, NEVER ARGV
#   A password in argv is in `ps`, in /proc/<pid>/cmdline and in the shell
#   history of whoever ran it. Only two non-secret flags are accepted. See
#   --help for the variable list; missing required ones are collected and
#   reported together.
#
# WHAT IT NEVER DOES
#   * `docker volume prune`, `docker system prune`, `docker compose down -v`,
#     `rm -rf` of a volume or of the instance directory. The data volume is the
#     only copy of the client's database.
#   * regenerate APP_KEY, rewrite an existing .env, or recreate the
#     administrator once storage/installed exists. All three destroy data that
#     cannot be recovered from this script.
#   * touch /opt/sijilna (the live instance) unless SIJILNA_ALLOW_MAIN_INSTANCE=1.
#   * create DNS records or Cloudflare Tunnel rules. That is the operator's step
#     and it is written down in deploy/README.md -> "Second instance".
#
# USAGE (the crm-test instance this was written to prove):
#
#   sudo SIJILNA_INSTANCE_HOSTNAME=crm-test.mohamedlabs.com \
#        SIJILNA_INSTANCE_DIR=/opt/sijilna-test/app \
#        SIJILNA_PROJECT=sijilna-test \
#        SIJILNA_WEB_PORT=3103 \
#        SIJILNA_DB_NAME=sijilna_test \
#        SIJILNA_DB_USER=sijilna_test \
#        KRAYIN_ADMIN_EMAIL='admin@client.test' \
#        KRAYIN_ADMIN_PASSWORD='<chosen>' \
#        bash deploy/provision-instance.sh
#
#   bash deploy/provision-instance.sh --dry-run    # resolve and check, write nothing
#
# Bash and not POSIX sh, matching deploy/deploy.sh: arrays, [[ ]], (( )).
set -euo pipefail

PROGRAM="${0##*/}"
DRY_RUN=0

# ------------------------------------------------------------------- inputs
#
# Read once, here, so that a typo in a variable name is visible in one place
# instead of being silently empty three hundred lines later.
INSTANCE_HOSTNAME="${SIJILNA_INSTANCE_HOSTNAME:-}"
INSTANCE_DIR="${SIJILNA_INSTANCE_DIR:-}"
PROJECT="${SIJILNA_PROJECT:-}"
WEB_PORT="${SIJILNA_WEB_PORT:-}"
DB_NAME="${SIJILNA_DB_NAME:-}"
DB_USER="${SIJILNA_DB_USER:-}"
DB_PASSWORD="${SIJILNA_DB_PASSWORD:-}"
DB_ROOT_PASSWORD="${SIJILNA_DB_ROOT_PASSWORD:-}"
ADMIN_EMAIL="${KRAYIN_ADMIN_EMAIL:-}"
ADMIN_PASSWORD="${KRAYIN_ADMIN_PASSWORD:-}"
REPO_URL="${SIJILNA_REPO_URL:-git@github.com:mhmd-ashrf-saad/crm-platform.git}"
GIT_REF="${SIJILNA_GIT_REF:-main}"

# Bounded health gate. A first boot builds the image (measured at 6-8 minutes on
# the homelab when composer install has to re-run), then runs the installer
# against an empty database, so 90 x 4s = 6 minutes of patience after `up -d`.
HEALTH_ATTEMPTS="${SIJILNA_HEALTH_ATTEMPTS:-90}"
HEALTH_INTERVAL="${SIJILNA_HEALTH_INTERVAL:-4}"

# A second stack costs roughly 470 MB RSS (measured on the homelab, see
# deploy/README.md -> Memory budget). Below this much free memory the box has to
# swap, and the first thing swap pressure kills is whatever the OOM killer picks
# -- which may well be the live instance's PHP-FPM workers. Warned, not refused:
# the operator may know better, and this script must not be the thing that
# decides a deployment is impossible.
MIN_FREE_MB="${SIJILNA_MIN_FREE_MB:-1500}"

# The live homelab instance's own identity, and the paths it owns. Overridable
# only by saying so out loud.
ALLOW_MAIN_INSTANCE="${SIJILNA_ALLOW_MAIN_INSTANCE:-0}"

# Prepare and stop: everything up to and including a hardened .env, then exit
# without building or starting anything. The operator's inspection checkpoint --
# and the only way to review exactly what the stack would be created from before
# it exists.
STOP_AFTER_ENV="${SIJILNA_STOP_AFTER_ENV:-0}"

# Everything below is derived in resolve_identity().
DB_CONTAINER=""
APP_CONTAINER=""
WEB_CONTAINER=""
IMAGE=""
DB_VOLUME=""
SESSION_COOKIE=""
ENV_FILE=""
COMPOSE_FILE=""
TEMPLATE_FILE=""
ENV_OWNER_UID=""
ADMIN_ALREADY_ACTIVE=0

# Collected by require_env() and reported together, so one run lists every
# missing variable instead of one per attempt.
MISSING=()

# ------------------------------------------------------------------- output
log() { printf '[provision] %s\n' "$*" >&2; }
warn() { printf '[provision] WARNING: %s\n' "$*" >&2; }
die() {
    printf '[provision] REFUSED: %s\n' "$*" >&2
    exit 1
}

# Two columns, no secrets. Used for the resolution report at the end (and in
# --dry-run, where it is the whole output).
row() {
    printf '  %-20s %s\n' "$1" "$2" >&2
}

usage() {
    cat <<'USAGE'
usage: provision-instance.sh [--dry-run] [--help]

Provisions one Sijilna CRM instance on the homelab container stack: its own
compose project, containers, image tag, database volume and loopback port.

  (no argument)  provision the instance described by the environment
  --dry-run      resolve and check everything, write and start nothing
  --help         this text

Required environment variables (nothing is accepted as an argument, because a
password in argv is a password in `ps`):

  SIJILNA_INSTANCE_HOSTNAME   public hostname, e.g. crm-test.mohamedlabs.com
                              (no scheme, no path, no port)
  SIJILNA_INSTANCE_DIR        absolute checkout path, e.g. /opt/sijilna-test/app
  SIJILNA_PROJECT             compose project, e.g. sijilna-test -- also the
                              prefix of every container and the volume
  SIJILNA_WEB_PORT            loopback port published by nginx, e.g. 3103
  SIJILNA_DB_NAME             database name, [A-Za-z0-9_]+
  SIJILNA_DB_USER             database user, [A-Za-z0-9_]+
  KRAYIN_ADMIN_EMAIL          first-boot administrator email
  KRAYIN_ADMIN_PASSWORD       first-boot administrator password
                              (the last two are required only while the instance
                              has no storage/installed marker and no recorded pair)

Optional environment variables:

  SIJILNA_DB_PASSWORD          otherwise a fresh 48-hex-character secret
  SIJILNA_DB_ROOT_PASSWORD     otherwise a fresh 48-hex-character secret
  SIJILNA_REPO_URL             default git@github.com:mhmd-ashrf-saad/crm-platform.git
  SIJILNA_GIT_REF              branch or tag for a fresh clone, default main
  SIJILNA_HEALTH_ATTEMPTS      default 90
  SIJILNA_HEALTH_INTERVAL      seconds between probes, default 4
  SIJILNA_MIN_FREE_MB          free-memory warning threshold, default 1500
  SIJILNA_STOP_AFTER_ENV       set to 1 to write and harden .env, then stop
                               before building or starting anything
  SIJILNA_ALLOW_MAIN_INSTANCE  set to 1 to allow SIJILNA_PROJECT=sijilna and
                               /opt/sijilna, i.e. the live instance itself

Re-running is safe: an existing checkout is reused untouched, an existing .env
is never rewritten (so APP_KEY and the database passwords survive), and the
administrator is created only while storage/installed is absent.
USAGE
}

while (($# > 0)); do
    case "$1" in
        --dry-run) DRY_RUN=1 ;;
        -h | --help) usage; exit 0 ;;
        *)
            printf '%s: unknown argument: %s\n' "$PROGRAM" "$1" >&2
            usage >&2
            exit 2
            ;;
    esac
    shift
done

# ---------------------------------------------------------------- utilities

# Reads one KEY=VALUE out of an .env without sourcing it: .env is a data file,
# and a provisioning script has no business executing it. Last occurrence wins,
# matching dotenv; surrounding quotes and a CR from a Windows edit are stripped.
env_value() {
    local file="$1" key="$2" line
    [ -f "$file" ] || return 1
    line="$(grep -E "^[[:space:]]*(export[[:space:]]+)?${key}=" "$file" 2>/dev/null | tail -n 1)" || return 1
    [ -n "$line" ] || return 1
    line="${line#*=}"
    printf '%s\n' "$line" |
        sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tr -d '\r'
}

# Every value written into .env passes through here, and this is the last gate
# in front of all of them: a CR or an LF inside a value ends its KEY= line and
# starts another one, and both of our .env readers are last-wins (docker
# compose's --env-file interpreter, and PHP's immutable dotenv behind env_file),
# so the injected line is the value the instance actually boots with.
#
# The live defect this closes: KRAYIN_ADMIN_EMAIL was the one caller-supplied
# value in the write path with no charset gate, and
#     KRAYIN_ADMIN_EMAIL=$'admin@client.test\nAPP_DEBUG=true'
# turned debug mode ON for the first boot. That boot then created
# storage/installed, so the .env gate refused the instance for the rest of its
# life -- installed, publicly reachable, with stack traces on. The email now has
# its own strict shape check (validate_admin_email); this one exists so the class
# cannot come back through some future value nobody thought to gate.
assert_env_value_is_single_line() {
    local key="$1" value="$2"
    case "$value" in
        *[[:cntrl:]]*)
            die "refusing to write $key into .env: its value contains a control character
    (newline, carriage return, tab or similar). One '\\n' in a value injects a second
    KEY=VALUE line, and .env is last-wins for both docker compose and PHP, so the
    injected line is what the instance would boot with. Nothing was written."
            ;;
    esac
}

# Replaces KEY=VALUE in place, or appends it. awk with -v instead of sed so a
# value containing / & or . cannot turn into a runaway substitution; the temp
# file is written next to the target, so the rename cannot cross filesystems.
set_env_value() {
    local file="$1" key="$2" value="$3" tmp
    [ -f "$file" ] || die "internal error: $file does not exist"
    assert_env_value_is_single_line "$key" "$value"
    tmp="$(mktemp "${file}.XXXXXX")"
    if ! awk -v key="$key" -v val="$value" '
        BEGIN { done = 0 }
        index($0, key "=") == 1 && !done { print key "=" val; done = 1; next }
        { print }
        END { if (!done) print key "=" val }
    ' "$file" >"$tmp"; then
        rm -f "$tmp"
        die "could not rewrite $file"
    fi
    chmod --reference="$file" "$tmp" 2>/dev/null || true
    mv -f "$tmp" "$file"
}

# 32 random bytes, base64, with Laravel's own prefix: byte-for-byte what
# `php artisan key:generate --show` emits, which cannot be used here because it
# needs vendor/ and vendor/ is not committed.
generate_app_key() {
    local raw
    raw="$(openssl rand -base64 32 | tr -d '\r\n')"
    ((${#raw} >= 40)) || die "openssl produced no usable key material"
    printf 'base64:%s\n' "$raw"
}

# Hex, not base64: a password lands in .env, in `docker compose config` output
# and in a MySQL CLI, and hex cannot be mangled by any of them.
generate_secret() {
    openssl rand -hex 24
}

# ------------------------------------------------------------- validation

require_env() {
    local name="$1" value="$2"
    if [ -z "$value" ]; then
        MISSING+=("$name")
    fi
}

validate_identity() {
    if ((${#MISSING[@]} > 0)); then
        die "$(
            printf 'missing required environment variable(s): %s\n' "${MISSING[*]}"
            printf '  values are read from the environment only -- see %s --help\n' "$PROGRAM"
        )"
    fi

    # COMPOSE_PROJECT_NAME outranks BOTH the compose file's `name:` and the
    # SIJILNA_PROJECT that --env-file supplies, so an export left over in the
    # operator's shell silently moves this stack onto another project -- and on
    # this host that project is `sijilna`, whose `sijilna_default` network and
    # `db` service alias belong to the live instance. Container names stay
    # interpolated, so nothing live is renamed; the network and the database are
    # what collide. compose() pins `-p "$PROJECT"` regardless, and this refuses
    # to run at all while the variable is set: a silent override of a
    # security-relevant value is not something to pass quietly.
    if [ -n "${COMPOSE_PROJECT_NAME:-}" ]; then
        die "COMPOSE_PROJECT_NAME is set in the environment ('$COMPOSE_PROJECT_NAME').
    It overrides both SIJILNA_PROJECT and deploy/docker-compose.yml's name:, so this run
    would not start the project it just validated. On this host it would attach the new
    containers to the live instance's network. Unset it and re-run:
      unset COMPOSE_PROJECT_NAME"
    fi

    # A compose project name is [a-z0-9][a-z0-9_-]*, and every container, image
    # and volume name is this string plus a suffix, so an invalid character here
    # would fail much later, inside compose, with a less obvious message.
    [[ "$PROJECT" =~ ^[a-z0-9][a-z0-9_-]*$ ]] ||
        die "SIJILNA_PROJECT must match ^[a-z0-9][a-z0-9_-]*\$ (compose project name rules); got '$PROJECT'"

    # MySQL identifiers: no dashes, so no quoting anywhere in a dump, a grant or
    # a `mysql -u` invocation ever has to be right.
    for val in "$DB_NAME" "$DB_USER"; do
        [[ "$val" =~ ^[A-Za-z0-9_]+$ ]] ||
            die "SIJILNA_DB_NAME and SIJILNA_DB_USER must match ^[A-Za-z0-9_]+\$; got '$val'"
    done

    case "$WEB_PORT" in
        '' | *[!0-9]*) die "SIJILNA_WEB_PORT must be a number; got '$WEB_PORT'" ;;
    esac
    if [ "$WEB_PORT" -lt 1 ] || [ "$WEB_PORT" -gt 65535 ]; then
        die "SIJILNA_WEB_PORT must be between 1 and 65535; got '$WEB_PORT'"
    fi

    [[ "$INSTANCE_HOSTNAME" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]] ||
        die "SIJILNA_INSTANCE_HOSTNAME must be a bare hostname (no scheme, no path, no port); got '$INSTANCE_HOSTNAME'"

    case "$INSTANCE_DIR" in
        /*) ;;
        *) die "SIJILNA_INSTANCE_DIR must be an absolute path; got '$INSTANCE_DIR'" ;;
    esac
    if [ "$INSTANCE_DIR" = "/" ] || [ "${INSTANCE_DIR%/}" != "$INSTANCE_DIR" ] || [[ "$INSTANCE_DIR" == *..* ]]; then
        die "SIJILNA_INSTANCE_DIR must be an absolute path without a trailing slash or '..'; got '$INSTANCE_DIR'"
    fi

    # The write path, value by value, so that no input reaches .env ungated:
    # APP_KEY (generated), APP_ENV/APP_DEBUG/APP_FORCE_HTTPS (literals here),
    # APP_URL (INSTANCE_HOSTNAME, checked above), DB_DATABASE/DB_USERNAME
    # (checked above), DB_PASSWORD/DB_ROOT_PASSWORD/KRAYIN_ADMIN_PASSWORD
    # (validate_secret_charset), SESSION_COOKIE and the SIJILNA_* identity block
    # (derived from the checked PROJECT and WEB_PORT), and KRAYIN_ADMIN_EMAIL --
    # the only free-form one, which validate_admin_email owns. Anything added
    # later is covered by assert_env_value_is_single_line inside set_env_value.
    validate_admin_email "$ADMIN_EMAIL"

    # The live instance is not a special case of "another instance": its volume
    # holds the demo data and its containers are what deploy.sh rolls back onto.
    # Provisioning over it would be indistinguishable from a reset.
    if [ "$ALLOW_MAIN_INSTANCE" != "1" ]; then
        if [ "$PROJECT" = "sijilna" ]; then
            die "SIJILNA_PROJECT=sijilna is the live homelab instance's compose project.
    Set SIJILNA_ALLOW_MAIN_INSTANCE=1 only if you really are re-provisioning
    https://crm.mohamedlabs.com in place, not adding a second instance."
        fi
        case "$INSTANCE_DIR" in
            /opt/sijilna | /opt/sijilna/*)
                die "SIJILNA_INSTANCE_DIR is inside /opt/sijilna, which belongs to the live instance.
    Use a separate directory (e.g. /opt/sijilna-test/app), or set
    SIJILNA_ALLOW_MAIN_INSTANCE=1 deliberately."
                ;;
        esac
    fi

    # Every secret here travels through three parsers that disagree about
    # quoting: the compose --env-file reader, docker's env_file reader and PHP's
    # immutable dotenv. A '$', a quote, a backslash or a leading '#' in a
    # password is read differently by at least one of them, and the failure is
    # the worst kind -- MySQL initialises on one value and the app connects with
    # another, so the stack builds, starts, and never becomes healthy. Generated
    # secrets are hex for exactly this reason; a supplied one is checked.
    validate_secret_charset SIJILNA_DB_PASSWORD "$DB_PASSWORD"
    validate_secret_charset SIJILNA_DB_ROOT_PASSWORD "$DB_ROOT_PASSWORD"
    validate_secret_charset KRAYIN_ADMIN_PASSWORD "$ADMIN_PASSWORD"
}

# KRAYIN_ADMIN_EMAIL is the one write-path value an operator types freely, so it
# gets the strictest shape check rather than the "does it contain an @" this
# script started with. Two jobs: the address has to be one that
# scripts/create-admin.php's FILTER_VALIDATE_EMAIL accepts (a refusal there is a
# container that restart-loops after MySQL is already initialised), and it must
# be unable to carry a newline, '$', '#', a quote or a backslash into .env --
# see assert_env_value_is_single_line for what a second line inside .env buys an
# attacker. `admin@localhost` and `a@b..c` are refused here because PHP's filter
# refuses them; refusing early names the variable, refusing late names a log.
validate_admin_email() {
    local value="$1"
    [ -n "$value" ] || return 0
    ((${#value} <= 254)) || die "KRAYIN_ADMIN_EMAIL is ${#value} characters long; the maximum is 254"
    [[ "$value" =~ ^[A-Za-z0-9][A-Za-z0-9._%+-]*@[A-Za-z0-9][A-Za-z0-9.-]*\.[A-Za-z]{2,}$ ]] ||
        die "KRAYIN_ADMIN_EMAIL is not a plain email address: '$value'
    Allowed: letters, digits and . _ % + - before the @; letters, digits, - and . after
    it; and a dotted domain with a 2+ letter last label. That set excludes exactly the
    characters .env, docker compose and PHP read differently -- a newline, \$, #, a quote
    or a backslash -- and it matches what scripts/create-admin.php will accept."
}

validate_secret_charset() {
    local name="$1" value="$2"
    [ -n "$value" ] || return 0
    [[ "$value" =~ ^[A-Za-z0-9._~!@%^+=-]+$ ]] ||
        die "$name contains a character that .env, docker compose and PHP do not agree on
    (quotes, \$, #, spaces and backslashes all break in one of them).
    Use letters, digits and ._~!@%^+=- -- or omit it and let this script generate
    a 48-hex-character secret, which is what it does by default."
    ((${#value} >= 8)) ||
        die "$name must be at least 8 characters"
}

resolve_identity() {
    DB_CONTAINER="$PROJECT-db"
    APP_CONTAINER="$PROJECT-app"
    WEB_CONTAINER="$PROJECT-web"
    IMAGE="$PROJECT-app"
    DB_VOLUME="$PROJECT-db-data"
    # Browsers scope cookies by host, not by port, so two instances answered on
    # two ports of the same host would overwrite each other's session cookie.
    SESSION_COOKIE="$(printf '%s' "$PROJECT" | tr -- '-' '_')_session"

    ENV_FILE="$INSTANCE_DIR/.env"
    COMPOSE_FILE="$INSTANCE_DIR/deploy/docker-compose.yml"
    TEMPLATE_FILE="$INSTANCE_DIR/deploy/.env.deploy.example"

    # Who owns the .env inode on the host. The deploy user must be able to read
    # it (docker compose interpolates deploy/.env as that user) and uid 33 must
    # be able to read it (the installer does file(base_path('.env'))). Under
    # sudo, "the deploy user" is the human who typed sudo, not root.
    if [ "$(id -u)" -eq 0 ]; then
        ENV_OWNER_UID="${SUDO_UID:-0}"
    else
        ENV_OWNER_UID="$(id -u)"
    fi
}

preflight_binaries() {
    local bin
    local -a missing=()
    # ss is not optional: check_host_port uses it to see a listener that is not a
    # container (a hand-started nginx, a stray process). Without it that check
    # reads as "nothing is listening", which fails OPEN -- the one thing a safety
    # check must never do.
    for bin in git docker curl openssl ss; do
        command -v "$bin" >/dev/null 2>&1 || missing+=("$bin")
    done
    ((${#missing[@]} == 0)) || die "missing required command(s): ${missing[*]}"
    docker compose version >/dev/null 2>&1 ||
        die "'docker compose' is not available to $(id -un) -- this is the compose v2 plugin, not docker-compose v1"
    docker info >/dev/null 2>&1 || die "the docker daemon is not reachable by $(id -un)"
}

warn_memory() {
    local avail_kb avail_mb
    [ -r /proc/meminfo ] || return 0
    avail_kb="$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo 2>/dev/null || true)"
    case "$avail_kb" in
        '' | *[!0-9]*) return 0 ;;
    esac
    avail_mb=$((avail_kb / 1024))
    if [ "$avail_mb" -lt "$MIN_FREE_MB" ]; then
        warn "only ${avail_mb} MB of memory is available and a second stack costs about 470 MB."
        warn "On this 4 GB box that means swap, and swap pressure is what makes the OOM killer"
        warn "choose a victim -- possibly the live instance's PHP-FPM workers. Lower"
        warn "pm.max_children in deploy/docker/php-fpm.conf, or use a bigger host."
    fi
}

# ------------------------------------------------------------- collisions
#
# The point of this section is to prove ownership rather than to look for a
# running container. A container that exists but is stopped still owns its name
# (compose refuses to create a second one), a volume exists whether or not
# anything runs, and a host port can be held by a process that is not docker at
# all. Each check therefore asks "who owns this?" -- the compose project label --
# and only accepts the answer "this project".

compose_project_of_container() {
    local name="$1"
    docker container inspect "$name" >/dev/null 2>&1 || return 1
    docker container inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' \
        "$name" 2>/dev/null || true
    return 0
}

# The checkout a container was created from. compose records it on the container
# but not on a volume, which is why only containers can be checked against it.
compose_dir_of_container() {
    docker container inspect -f \
        '{{index .Config.Labels "com.docker.compose.project.working_dir"}}' \
        "$1" 2>/dev/null || true
}

check_container_name() {
    local name="$1" owner owner_dir want_dir
    if ! owner="$(compose_project_of_container "$name")"; then
        return 0
    fi
    if [ "$owner" != "$PROJECT" ]; then
        die "container name '$name' is already owned by compose project '${owner:-<not a compose project>}'.
    Compose would refuse to create it, and adopting it would take over somebody
    else's data. Choose another SIJILNA_PROJECT."
    fi
    # Same project name is not the same instance. Two checkouts that picked the
    # same SIJILNA_PROJECT would share one container set, one network and one
    # volume -- and compose resolves that by refusing at `up` time, minutes after
    # the build, or (worse on an older compose) by adopting them. The working
    # directory label is the piece of evidence that tells them apart.
    owner_dir="$(compose_dir_of_container "$name")"
    want_dir="$(cd "$INSTANCE_DIR/deploy" 2>/dev/null && pwd -P || printf '%s' "$INSTANCE_DIR/deploy")"
    if [ -n "$owner_dir" ] && [ "$owner_dir" != "$want_dir" ]; then
        die "container '$name' belongs to compose project '$PROJECT' but was created from
    $owner_dir
    and this run is for
    $want_dir
    Two checkouts must not share one SIJILNA_PROJECT: they would share one set of
    containers and one database volume. Choose another SIJILNA_PROJECT for the
    new instance."
    fi
    log "collision check: container '$name' already exists and is owned by this project -- reusing it"
}

check_volume_name() {
    local name="$1" owner
    if ! docker volume inspect "$name" >/dev/null 2>&1; then
        return 0
    fi
    owner="$(docker volume inspect -f '{{index .Labels "com.docker.compose.project"}}' \
        "$name" 2>/dev/null || true)"
    if [ "$owner" = "$PROJECT" ]; then
        log "collision check: volume '$name' already exists and is owned by this project -- reusing it"
        return 0
    fi
    die "volume '$name' already exists and belongs to '${owner:-<not a compose project>}'.
    Two instances sharing one volume share one MySQL data directory. Choose
    another SIJILNA_PROJECT (or rename the volume deliberately, by hand)."
}

port_is_listening() {
    local port="$1"
    # A check that cannot run must not answer "nobody holds this port".
    command -v ss >/dev/null 2>&1 ||
        die "cannot check whether loopback port $port is free: 'ss' is not installed.
    Refusing to skip a safety check and start a stack on a port something else may hold.
    Install iproute2 (which provides ss) and re-run."
    ss -ltnH 2>/dev/null | awk '{print $4}' | grep -Eq "[:.]${port}\$"
}

check_host_port() {
    local port="$1" owners line name owner
    # Containers first: `--filter publish=` matches a published host port, and
    # the label says which compose project published it.
    owners="$(docker ps --filter "publish=$port" \
        --format '{{.Names}}|{{.Label "com.docker.compose.project"}}' 2>/dev/null || true)"
    if [ -n "$owners" ]; then
        while IFS= read -r line; do
            [ -n "$line" ] || continue
            name="${line%%|*}"
            owner="${line#*|}"
            if [ "$owner" = "$PROJECT" ]; then
                log "collision check: loopback port $port is already published by $name, which belongs to this project -- reusing it"
                return 0
            fi
            die "loopback port $port is already published by container '$name' of compose project '${owner:-<not a compose project>}'.
    Two stacks cannot publish the same host port. Choose another SIJILNA_WEB_PORT."
        done <<<"$owners"
    fi
    # Then anything else on the host. A listener owned by no container of this
    # project makes `up` fail minutes later, after the build.
    if port_is_listening "$port"; then
        die "loopback port $port is already listening on this host and no container of project '$PROJECT' publishes it.
    Something outside this compose project holds it -- find it with
    'ss -ltnp | grep :$port' and choose another SIJILNA_WEB_PORT."
    fi
    return 0
}

check_collisions() {
    local name
    for name in "$DB_CONTAINER" "$APP_CONTAINER" "$WEB_CONTAINER"; do
        check_container_name "$name"
    done
    check_volume_name "$DB_VOLUME"
    check_host_port "$WEB_PORT"
    log "collision check: project '$PROJECT' owns its container names, volume '$DB_VOLUME', image '${IMAGE}:latest' and loopback port $WEB_PORT"
}

# ------------------------------------------------------------- the checkout

ensure_checkout() {
    if [ -d "$INSTANCE_DIR/.git" ]; then
        log "checkout: reusing the existing git checkout at $INSTANCE_DIR (not fetching, not checking out)"
        if [ -n "$GIT_REF" ]; then
            local current
            current="$(git -C "$INSTANCE_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || true)"
            if [ -n "$current" ] && [ "$current" != "$GIT_REF" ] && [ "$current" != "HEAD" ]; then
                warn "the checkout is on '$current', not '$GIT_REF'. Leaving it alone: it may be running."
            fi
        fi
    else
        if [ -e "$INSTANCE_DIR" ] && [ -n "$(ls -A "$INSTANCE_DIR" 2>/dev/null || true)" ]; then
            die "$INSTANCE_DIR exists, is not empty, and is not a git checkout -- refusing to write into it"
        fi
        log "checkout: cloning $REPO_URL ($GIT_REF) into $INSTANCE_DIR"
        mkdir -p "$(dirname "$INSTANCE_DIR")" || die "cannot create the parent of $INSTANCE_DIR"
        if ! git clone --branch "$GIT_REF" -- "$REPO_URL" "$INSTANCE_DIR"; then
            die "git clone failed. A private checkout needs a deploy key: see the git core.sshCommand
    note in deploy/README.md, or pass SIJILNA_REPO_URL with a reachable remote."
        fi
        # A clone made under sudo would be root-owned, and the next non-root
        # `git fetch` in it would stop on "dubious ownership". Hand it to the
        # human who invoked sudo, once, at creation time.
        if [ "$(id -u)" -eq 0 ] && [ "${ENV_OWNER_UID}" != "0" ]; then
            chown -R "${ENV_OWNER_UID}" "$INSTANCE_DIR" 2>/dev/null ||
                warn "could not chown the checkout to uid ${ENV_OWNER_UID}"
        fi
    fi

    [ -f "$COMPOSE_FILE" ] ||
        die "$COMPOSE_FILE is missing -- this checkout predates the parameterized compose stack"
    [ -f "$TEMPLATE_FILE" ] ||
        die "$TEMPLATE_FILE is missing -- the env template must come from the checkout"
}

# ------------------------------------------------------------------- the .env

# The pair is needed while the entrypoint can still run the installer. Both
# markers are checked: storage/installed is the authoritative one, and a
# recorded pair in .env covers the case where a previous run wrote .env and then
# failed before the containers ever started.
compute_admin_requirement() {
    local recorded_email recorded_password
    if [ -f "$INSTANCE_DIR/storage/installed" ]; then
        ADMIN_ALREADY_ACTIVE=1
        log "state: storage/installed exists -- this instance is installed, the administrator will not be recreated"
        return 0
    fi
    recorded_email="$(env_value "$ENV_FILE" KRAYIN_ADMIN_EMAIL || true)"
    recorded_password="$(env_value "$ENV_FILE" KRAYIN_ADMIN_PASSWORD || true)"
    if [ -n "$recorded_email" ] && [ -n "$recorded_password" ]; then
        ADMIN_ALREADY_ACTIVE=1
        log "state: .env already records the first-boot administrator pair -- it will be used on the next boot"
    fi
    return 0
}

# An existing .env is the instance's memory: APP_KEY decrypts the encrypted
# columns, and the database passwords are the ones MySQL was initialised with
# inside a data volume that already exists. So it is never rewritten -- it is
# checked against what the operator asked for, and a disagreement is a refusal.
adopt_env_file() {
    local recorded

    recorded="$(env_value "$ENV_FILE" APP_KEY || true)"
    [ -n "$recorded" ] ||
        die "$ENV_FILE exists but has no APP_KEY. Refusing to generate one: a new key makes every
    encrypted column and every session in the existing database unreadable. Restore
    the key from a backup, or start from an empty instance directory."

    recorded="$(env_value "$ENV_FILE" APP_ENV || true)"
    [ "$recorded" = "production" ] ||
        die "$ENV_FILE sets APP_ENV='${recorded:-<empty>}'. This script only provisions production instances;
    fix it with 'APP_ENV=production' (crm-5lh), or provision a development checkout with
    scripts/provision-dev.sh instead."

    recorded="$(env_value "$ENV_FILE" APP_DEBUG || true)"
    case "$recorded" in
        false | False | FALSE) ;;
        *)
            die "$ENV_FILE sets APP_DEBUG='${recorded:-<empty>}', which renders stack traces on every error
    page (crm-5lh). Refusing to start it. Fix it with:
      sed -i 's|^APP_DEBUG=.*|APP_DEBUG=false|' $ENV_FILE"
            ;;
    esac

    # Not one of the seven identity keys, but the README's mixed-content reasoning
    # depends on it: behind the Tunnel nginx receives plain HTTP, so without this
    # flag Laravel writes http:// asset URLs into every page and the browser
    # blocks them. An operator-crafted .env must not be able to boot that.
    recorded="$(env_value "$ENV_FILE" APP_FORCE_HTTPS || true)"
    case "$recorded" in
        true | True | TRUE | 1) ;;
        *)
            die "$ENV_FILE sets APP_FORCE_HTTPS='${recorded:-<empty>}'. The Tunnel forwards plain HTTP, so
    without this flag Laravel generates http:// asset URLs and the browser blocks them as
    mixed content. Refusing to start it. Fix it with:
      sed -i 's|^APP_FORCE_HTTPS=.*|APP_FORCE_HTTPS=true|' $ENV_FILE
    and append 'APP_FORCE_HTTPS=true' first if the key is absent entirely."
            ;;
    esac

    assert_recorded APP_URL "https://$INSTANCE_HOSTNAME" ""
    assert_recorded DB_DATABASE "$DB_NAME" ""
    assert_recorded DB_USERNAME "$DB_USER" ""
    assert_recorded SIJILNA_PROJECT "$PROJECT" "sijilna"
    assert_recorded SIJILNA_DB_CONTAINER "$DB_CONTAINER" "sijilna-db"
    assert_recorded SIJILNA_APP_CONTAINER "$APP_CONTAINER" "sijilna-app"
    assert_recorded SIJILNA_WEB_CONTAINER "$WEB_CONTAINER" "sijilna-web"
    assert_recorded SIJILNA_IMAGE "$IMAGE" "sijilna-app"
    assert_recorded SIJILNA_DB_VOLUME "$DB_VOLUME" "sijilna-db-data"
    assert_recorded SIJILNA_WEB_PORT "$WEB_PORT" "3102"

    DB_PASSWORD="$(env_value "$ENV_FILE" DB_PASSWORD || true)"
    DB_ROOT_PASSWORD="$(env_value "$ENV_FILE" DB_ROOT_PASSWORD || true)"
    [ -n "$DB_PASSWORD" ] ||
        die "$ENV_FILE has an empty DB_PASSWORD. MySQL inside the existing volume was initialised with
    a password; an empty one cannot connect to it."
    [ -n "$DB_ROOT_PASSWORD" ] ||
        die "$ENV_FILE has an empty DB_ROOT_PASSWORD, and the db service requires it."

    log "env: reusing $ENV_FILE unchanged (APP_KEY, database passwords and the identity are as recorded)"
}

# Compares a requested identity value with the one the .env records, or with the
# compose default when the key is absent. An .env written before
# deploy/provision-instance.sh existed has none of the SIJILNA_* keys, so the
# default is what it actually resolves to -- and for any project other than
# sijilna that is a mismatch, loudly.
assert_recorded() {
    local key="$1" wanted="$2" default="$3" recorded
    recorded="$(env_value "$ENV_FILE" "$key" || true)"
    if [ -z "$recorded" ]; then
        recorded="$default"
    fi
    if [ -z "$recorded" ] || [ "$recorded" != "$wanted" ]; then
        die "$ENV_FILE records $key='${recorded:-<empty>}' but this run resolved '$wanted'.
    Refusing to guess which one is real: the container names, volume and port it
    would start are not the ones that already exist. Fix the .env by hand, or
    choose the identity the instance already has."
    fi
}

write_env_file() {
    local tmp="$ENV_FILE.new" template_recorded

    [ -f "$TEMPLATE_FILE" ] || die "missing $TEMPLATE_FILE"

    if [ -n "$DB_PASSWORD" ]; then
        log "env: DB_PASSWORD supplied in the environment"
    else
        DB_PASSWORD="$(generate_secret)"
        log "env: generated DB_PASSWORD (48 hex characters, not shown)"
    fi
    if [ -n "$DB_ROOT_PASSWORD" ]; then
        log "env: DB_ROOT_PASSWORD supplied in the environment"
    else
        DB_ROOT_PASSWORD="$(generate_secret)"
        log "env: generated DB_ROOT_PASSWORD (48 hex characters, not shown)"
    fi

    # 077 while the file is being built: between cp and chmod it already holds
    # the generated passwords.
    (umask 077 && cp -- "$TEMPLATE_FILE" "$tmp") || die "cannot write $tmp"

    set_env_value "$tmp" APP_KEY "$(generate_app_key)"
    # Re-stated rather than trusted: these three are the crm-5lh contract, and a
    # future edit to the template must not be able to relax them silently.
    set_env_value "$tmp" APP_ENV "production"
    set_env_value "$tmp" APP_DEBUG "false"
    set_env_value "$tmp" APP_URL "https://$INSTANCE_HOSTNAME"
    set_env_value "$tmp" APP_FORCE_HTTPS "true"
    set_env_value "$tmp" DB_DATABASE "$DB_NAME"
    set_env_value "$tmp" DB_USERNAME "$DB_USER"
    set_env_value "$tmp" DB_PASSWORD "$DB_PASSWORD"
    set_env_value "$tmp" DB_ROOT_PASSWORD "$DB_ROOT_PASSWORD"
    set_env_value "$tmp" SESSION_COOKIE "$SESSION_COOKIE"
    set_env_value "$tmp" KRAYIN_ADMIN_EMAIL "$ADMIN_EMAIL"
    set_env_value "$tmp" KRAYIN_ADMIN_PASSWORD "$ADMIN_PASSWORD"

    template_recorded="$(env_value "$tmp" APP_KEY || true)"
    case "$template_recorded" in
        base64:*) ;;
        *) die "internal error: the written .env does not carry a base64: APP_KEY" ;;
    esac

    # The instance identity, explicit instead of implied by the compose
    # defaults. Written as a block so `grep SIJILNA_ $ENV_FILE` answers "which
    # instance is this?" in one line each. Written with cat and not
    # set_env_value because these keys may be absent from the template -- so the
    # line-boundary gate lives in validate_identity instead: every value below is
    # PROJECT plus a fixed suffix, or the numeric WEB_PORT, and PROJECT matched
    # ^[a-z0-9][a-z0-9_-]*$ before this function ran.
    cat >>"$tmp" <<EOF

# --- instance identity ------------------------------------------------------
# Written by deploy/provision-instance.sh. deploy/docker-compose.yml
# interpolates these into the compose project, the three container names, the
# image tag, the data volume name and the published loopback port. They are what
# makes this instance distinct from the homelab's sijilna stack; every other
# instance of Sijilna on this host needs a different set.
SIJILNA_PROJECT=$PROJECT
SIJILNA_DB_CONTAINER=$DB_CONTAINER
SIJILNA_APP_CONTAINER=$APP_CONTAINER
SIJILNA_WEB_CONTAINER=$WEB_CONTAINER
SIJILNA_IMAGE=$IMAGE
SIJILNA_DB_VOLUME=$DB_VOLUME
SIJILNA_WEB_PORT=$WEB_PORT
EOF

    mv -f "$tmp" "$ENV_FILE" || die "cannot move $tmp into place"
    log "env: wrote $ENV_FILE from $TEMPLATE_FILE"
}

ensure_compose_env_link() {
    local link="$INSTANCE_DIR/deploy/.env" target
    if [ -L "$link" ]; then
        target="$(readlink "$link")"
        if [ "$target" = "../.env" ]; then
            log "env: deploy/.env already points at ../.env"
            return 0
        fi
        die "deploy/.env points at '$target', expected '../.env'. compose would interpolate a
    different file from the one the app container mounts."
    fi
    if [ -e "$link" ]; then
        die "deploy/.env is a regular file. Two copies of one env file is how DB_USERNAME drifts;
    replace it with a symlink: mv $link $link.old && ln -s ../.env $link"
    fi
    ln -s ../.env "$link" || die "cannot create $link"
    log "env: linked deploy/.env -> ../.env (one file, two names)"
}

harden_env_file() {
    local gid mode
    chmod 640 "$ENV_FILE" || die "cannot set mode 640 on $ENV_FILE"
    # Group 33 (www-data) must read it: the installer reads the file directly
    # with PHP's file(), and that failure is a container restart loop with a
    # healthy database. The owner must be the deploy user, because `docker
    # compose` interpolates deploy/.env as that user.
    if ! chown "${ENV_OWNER_UID}:33" "$ENV_FILE" 2>/dev/null && ! chgrp 33 "$ENV_FILE" 2>/dev/null; then
        die "cannot give $ENV_FILE to group 33 (www-data). Without that the app container restart-loops on
    'file(/var/www/html/.env): Failed to open stream: Permission denied'.
    Run this script under sudo, or fix it by hand:
      sudo chown ${ENV_OWNER_UID}:33 $ENV_FILE && sudo chmod 640 $ENV_FILE"
    fi
    gid="$(stat -c %g "$ENV_FILE")"
    [ "$gid" = "33" ] || die "internal error: $ENV_FILE has group $gid, expected 33"
    mode="$(stat -c %a "$ENV_FILE")"
    [ "$mode" = "640" ] || die "internal error: $ENV_FILE has mode $mode, expected 640"
    log "env: $ENV_FILE is uid ${ENV_OWNER_UID} gid 33 mode 640"
}

# storage/ and bootstrap/cache are bind-mounted from the host, so the image's
# ownership does not apply to them. The entrypoint re-applies this on every
# start, which is why a failure here is a warning rather than a refusal.
prepare_storage() {
    local dir
    for dir in storage bootstrap/cache; do
        mkdir -p "$INSTANCE_DIR/$dir" 2>/dev/null || true
    done
    if [ "$(id -u)" -eq 0 ]; then
        chown -R 33:33 "$INSTANCE_DIR/storage" "$INSTANCE_DIR/bootstrap/cache" 2>/dev/null ||
            warn "could not chown storage/ and bootstrap/cache/ to 33:33 -- the entrypoint retries on start"
    else
        log "storage: owned by the entrypoint's own chown step at container start"
    fi
}

# ------------------------------------------------------------------- docker

compose() {
    # `-p` is not decoration. Project resolution is: -p, then COMPOSE_PROJECT_NAME,
    # then the file's `name:`, then the directory name -- so without this flag a
    # stray COMPOSE_PROJECT_NAME in the operator's shell silently starts a
    # different project than the one every collision check above validated.
    # "$PROJECT" is the checked value; the file's name: is only its fallback.
    docker compose --env-file "$ENV_FILE" -p "$PROJECT" -f "$COMPOSE_FILE" "$@"
}

containers_ok() {
    local c state health
    for c in "$DB_CONTAINER" "$APP_CONTAINER" "$WEB_CONTAINER"; do
        state="$(docker inspect -f '{{.State.Status}}' "$c" 2>/dev/null)" || state="missing"
        if [ "$state" != "running" ]; then
            return 1
        fi
        health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{end}}' \
            "$c" 2>/dev/null)" || health=""
        # No healthcheck reports nothing; "starting" is not healthy. Both
        # "running" and "healthy" are required.
        if [ -n "$health" ] && [ "$health" != "healthy" ]; then
            return 1
        fi
    done
    return 0
}

wait_healthy() {
    local attempt code url="http://127.0.0.1:$WEB_PORT/admin/login"
    for ((attempt = 1; attempt <= HEALTH_ATTEMPTS; attempt++)); do
        code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "$url" 2>/dev/null)" || code="000"
        if [ "$code" = "200" ] && containers_ok; then
            log "health: $url returned 200 and all three containers are running (probe $attempt/$HEALTH_ATTEMPTS)"
            return 0
        fi
        if [ "$attempt" -lt "$HEALTH_ATTEMPTS" ]; then
            sleep "$HEALTH_INTERVAL"
        fi
    done
    log "health: FAILED after $HEALTH_ATTEMPTS probes (last HTTP $code from $url)" >&2
    log "health: the last 60 lines of the app container follow" >&2
    compose logs --tail=60 app >&2 || true
    die "the instance did not answer on http://127.0.0.1:$WEB_PORT/admin/login.
    The stack was left running on purpose: read the log above. Do not reach for
    'down -v' -- the volume is the client's data. A first boot that never became
    healthy is almost always APP_KEY (empty), .env ownership (uid 33 cannot read
    it) or DB_PASSWORD (MySQL was already initialised with another one)."
}

# crm-5lh: a provisioned instance must never be debuggable in public. Two
# independent checks, because they fail differently: the .env gate catches a
# configuration mistake before anything starts, and this one catches a value the
# application actually resolved, which is what a stack trace depends on.
assert_no_debug_in_running_app() {
    local env_out debug_out body

    env_out="$(compose exec -T app setpriv --reuid=www-data --regid=www-data \
        --clear-groups php artisan env --no-ansi 2>/dev/null || true)"
    case "$env_out" in
        *production*) log "crm-5lh: the running application reports APP_ENV=production" ;;
        '') warn "crm-5lh: 'artisan env' produced no output -- cannot confirm the environment from inside the container" ;;
        *) die "crm-5lh: the running application reports an environment other than production:
    $env_out" ;;
    esac

    debug_out="$(compose exec -T app setpriv --reuid=www-data --regid=www-data \
        --clear-groups php artisan about --only=environment --no-ansi 2>/dev/null |
        grep -i 'debug mode' || true)"
    if [ -z "$debug_out" ]; then
        warn "crm-5lh: could not read 'Debug Mode' from artisan about -- relying on the .env gate"
    else
        case "$debug_out" in
            *OFF* | *off* | *False* | *false*) log "crm-5lh: the running application reports APP_DEBUG off" ;;
            *) die "crm-5lh: the running application still has debug mode on:
    $debug_out
    Every error page on this instance would render a stack trace. Fix APP_DEBUG in
    $ENV_FILE and 'docker compose restart app'." ;;
        esac
    fi

    # And the thing the flag is supposed to guarantee: an error page with no
    # trace on it. Ignition's debug 404 carries "Whoops" and a stack trace; the
    # production 404 is a bare "Not Found".
    body="$(curl -s --max-time 10 "http://127.0.0.1:$WEB_PORT/provision-probe-$$" 2>/dev/null || true)"
    if printf '%s' "$body" | grep -qiE 'whoops|ignition|stack ?trace|vendor/laravel/framework'; then
        die "crm-5lh: the 404 page renders a debug stack trace, so debug mode is on in the running stack."
    fi
    log "crm-5lh: the error page for a missing route carries no stack trace"
}

# Move the app container onto the image that was just built.
#
# `up -d` alone does not always do it on this host: Docker 29 runs the containerd
# image store, and a rebuilt local image has no manifest-list digest for compose
# to compare against, so compose can report "Running" while the container still
# executes the previous code. Same defect and same fix as deploy/deploy.sh's
# ensure_app_on_latest. Harmless on a first boot, load-bearing on a re-run.
ensure_app_on_built_image() {
    local running target
    running="$(docker inspect -f '{{.Image}}' "$APP_CONTAINER" 2>/dev/null || true)"
    target="$(docker image inspect -f '{{.Id}}' "${IMAGE}:latest" 2>/dev/null || true)"
    if [ -z "$running" ] || [ -z "$target" ] || [ "$running" = "$target" ]; then
        return 0
    fi
    log "compose: $APP_CONTAINER is not on ${IMAGE}:latest yet -- recreating it"
    compose up -d --force-recreate --no-deps app || die "could not move $APP_CONTAINER onto ${IMAGE}:latest"
}

# -------------------------------------------------------------------- report

report() {
    local admin_line key_line env_state debug_state force_https_state
    case "$ADMIN_ALREADY_ACTIVE" in
        1) admin_line="already created -- not touched" ;;
        *) admin_line="created on first boot from .env (not shown)" ;;
    esac
    if [ -f "$ENV_FILE" ]; then
        # Only the prefix of the key, and only because it identifies the file: a
        # full APP_KEY in a log is a credential leak.
        key_line="set ($(env_value "$ENV_FILE" APP_KEY 2>/dev/null | cut -c1-11 || true)... not shown)"
        env_state="$(env_value "$ENV_FILE" APP_ENV || true)"
        debug_state="$(env_value "$ENV_FILE" APP_DEBUG || true)"
        force_https_state="$(env_value "$ENV_FILE" APP_FORCE_HTTPS || true)"
    else
        key_line="to be generated on first write (32 random bytes, base64, not shown)"
        env_state="production (to be written)"
        debug_state="false (to be written)"
        force_https_state="true (to be written)"
    fi

    {
        printf '\n[provision] resolved instance\n'
        row hostname "$INSTANCE_HOSTNAME"
        row checkout "$INSTANCE_DIR"
        row "compose project" "$PROJECT"
        row containers "$DB_CONTAINER $APP_CONTAINER $WEB_CONTAINER"
        row image "${IMAGE}:latest"
        row "data volume" "$DB_VOLUME"
        row "loopback port" "127.0.0.1:$WEB_PORT (nginx; TLS ends at the tunnel)"
        row database "$DB_NAME (user $DB_USER, password not shown)"
        row APP_URL "https://$INSTANCE_HOSTNAME"
        row APP_ENV "$env_state"
        row APP_DEBUG "$debug_state"
        row APP_FORCE_HTTPS "$force_https_state"
        row SESSION_COOKIE "$SESSION_COOKIE"
        row APP_KEY "$key_line"
        row administrator "$admin_line"
        row "env file" "$ENV_FILE (uid ${ENV_OWNER_UID} gid 33 mode 640)"
        printf '\n[provision] next steps (not done by this script, on purpose)\n'
        printf '  1. DNS: a proxied CNAME %s -> <tunnel-uuid>.cfargotunnel.com in the zone.\n' \
            "${INSTANCE_HOSTNAME%%.*}"
        printf '  2. Tunnel: an ingress rule ABOVE the catch-all in the Cloudflare Tunnel\n'
        printf '     config: %s -> http://127.0.0.1:%s\n' "$INSTANCE_HOSTNAME" "$WEB_PORT"
        printf '     Full snippet: deploy/README.md -> Second instance.\n'
        printf '  3. Verify: curl -s -o /dev/null -w %%{http_code} https://%s/admin/login\n' "$INSTANCE_HOSTNAME"
        printf '  4. Remove KRAYIN_ADMIN_EMAIL / KRAYIN_ADMIN_PASSWORD from %s once the\n' "$ENV_FILE"
        printf '     instance has booted: the pair has done its job.\n'
        printf '  5. Backups and a deploy pipeline are per-instance host work; see\n'
        printf '     deploy/README.md -> Second instance -> What is NOT automated.\n\n'
    } >&2
}

# ---------------------------------------------------------------------- main

main() {
    require_env SIJILNA_INSTANCE_HOSTNAME "$INSTANCE_HOSTNAME"
    require_env SIJILNA_INSTANCE_DIR "$INSTANCE_DIR"
    require_env SIJILNA_PROJECT "$PROJECT"
    require_env SIJILNA_WEB_PORT "$WEB_PORT"
    require_env SIJILNA_DB_NAME "$DB_NAME"
    require_env SIJILNA_DB_USER "$DB_USER"
    # The administrator pair is required only while the instance still has to be
    # installed; on a re-run it would be a credential passed for no reason.
    resolve_identity
    compute_admin_requirement
    if [ "$ADMIN_ALREADY_ACTIVE" -eq 0 ]; then
        require_env KRAYIN_ADMIN_EMAIL "$ADMIN_EMAIL"
        require_env KRAYIN_ADMIN_PASSWORD "$ADMIN_PASSWORD"
    fi
    validate_identity

    preflight_binaries
    warn_memory
    check_collisions

    # An existing .env is checked before anything else is touched -- including in
    # --dry-run, because the identity it records and the crm-5lh keys inside it
    # are exactly what a dry run is for. It is read, never rewritten: APP_KEY and
    # the database passwords are the ones the existing data volume was created
    # with.
    if [ -f "$ENV_FILE" ]; then
        adopt_env_file
    fi

    if [ "$DRY_RUN" -eq 1 ]; then
        log "dry run: inputs validated, collisions and the existing .env checked; nothing was written, cloned or started"
        report
        return 0
    fi

    ensure_checkout

    if [ ! -f "$ENV_FILE" ]; then
        write_env_file
    fi
    ensure_compose_env_link
    harden_env_file
    prepare_storage

    if [ "$STOP_AFTER_ENV" = "1" ]; then
        log "stop after env: .env is written and hardened, nothing was built or started"
        report
        return 0
    fi

    log "compose: up -d --build (project $PROJECT, image ${IMAGE}:latest)"
    compose up -d --build || die "docker compose up -d --build failed; nothing was destroyed, re-run once the cause is fixed"
    ensure_app_on_built_image
    wait_healthy
    assert_no_debug_in_running_app
    report
    log "done: https://$INSTANCE_HOSTNAME is served from 127.0.0.1:$WEB_PORT by project $PROJECT"
}

main "$@"
