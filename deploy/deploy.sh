#!/usr/bin/env bash
#
# Sijilna CRM -- pull-based deploy for the homelab.
#
# WHAT RUNS THIS
#   sijilna-deploy.timer, as user `mohamed`, every three minutes. It runs an
#   INSTALLED copy of this file at /opt/sijilna/deploy.sh -- not the copy inside
#   the checkout -- because the checkout is what this script rewrites. After it
#   checks out an older tag, `deploy/deploy.sh` may not exist at all (every tag
#   published before this file landed is like that), and nothing should be able
#   to delete the thing that is driving the deploy. The installed copy is
#   refreshed from the checkout at the end of a successful deploy (see
#   refresh_runner), so `deploy/deploy.sh` in the repo stays the source of truth.
#
# WHAT IT DOES, IN ORDER
#   1. flock: one run at a time; a second run exits immediately, it does not
#      queue. A queue would pile up 20-minute deploys behind one another.
#   2. git fetch --tags, then take the deploy-* tag sitting on the
#      furthest-progressed commit of origin/main. Nothing newer than the state
#      file -> exit 0 without writing a log line. That is the 3-minute path and
#      it must stay free.
#   3. Refuse to run on a dirty checkout. It never discards work.
#   4. mysqldump inside sijilna-db, before anything is touched. No dump, no
#      deploy: a failed deploy that needs a restore is not the moment to
#      discover the dump is missing.
#   5. git checkout the tag, rebuild the image only when the image inputs
#      changed, docker compose up -d.
#   6. Health gate: HTTP 200 from HEALTH_URL and all three containers running,
#      polled, bounded.
#   7. On failure after step 5: go back to the previous ref, put the previous
#      image back by its ID, restore the pre-deploy dump if this deploy applied
#      new migrations, restart, verify, exit non-zero, one Telegram alert.
#
# WHAT IT NEVER DOES
#   * `docker compose down -v`, `docker system prune`, `docker volume prune`,
#     `rm -rf` on a volume. The `sijilna-db-data` volume is the only copy of the
#     data; a rollback moves code, never storage.
#   * put a credential in argv, in the log, or in the journal. See `mask`.
#   * discard local changes. A dirty checkout is a hard stop.
#
# WHAT IT REFUSES BEFORE IT CHANGES ANYTHING
#   * A `SIJILNA_*` key in the live .env, or one of the seven instance-identity
#     keys exported into its own environment. Those keys are how a SECOND
#     instance names its stack; on the live one they re-resolve the project,
#     the containers, the volume and the port, so `up -d` would stand up an
#     empty stack beside the real one. Nothing is fetched, dumped, built or
#     restarted first, and there is no flag that skips the check -- on purpose.
#   * An unpinned compose project: every compose invocation below carries
#     `-p sijilna`, so neither COMPOSE_PROJECT_NAME nor the file's `name:` can
#     move the stack somewhere else.
#
# MANUAL USE
#   /opt/sijilna/deploy.sh                            # what the timer runs
#   /opt/sijilna/deploy.sh --redeploy                 # deploy the newest tag again
#   FORCE_BUILD=1 /opt/sijilna/deploy.sh --redeploy   # and rebuild the image
#
# Bash and not POSIX sh (the container entrypoint is sh): this uses arrays,
# `[[ ]]`, `(( ))` and process substitution, and the homelab's /bin/bash is 5.2.
set -euo pipefail

# --------------------------------------------------------------- configuration
#
# Every operational value is overridable from the environment. That is not
# decoration: the drills in deploy/README.md force a health-gate failure with
# HEALTH_URL/HEALTH_ATTEMPTS, and the systemd unit changes a cadence or a URL
# without a code change.
APP_DIR="${SIJILNA_APP_DIR:-/opt/sijilna/app}"
STATE_DIR="${SIJILNA_STATE_DIR:-/opt/sijilna}"
RUNNER="${SIJILNA_RUNNER:-$STATE_DIR/deploy.sh}"
STATE_FILE="$STATE_DIR/deployed-tag"
LAST_ALERT_FILE="$STATE_DIR/last-alert"
LOCK_FILE="$STATE_DIR/deploy.lock"
DUMP_DIR="$STATE_DIR/backups"
RUN_DIR="$STATE_DIR/.run"
SSH_KEY="$STATE_DIR/.ssh/sijilna-deploy"
LOG_FILE="${SIJILNA_LOG_FILE:-/var/log/sijilna-deploy.log}"
ALERT_ENV="${SIJILNA_ALERT_ENV:-/opt/homelab/backup.env}"

COMPOSE_FILE="$APP_DIR/deploy/docker-compose.yml"
# The compose project the live instance IS. Every compose call is run as
# `docker compose -p "$INSTANCE_PROJECT" ...`, because project resolution is
# ordered -p, COMPOSE_PROJECT_NAME, the file's `name:`, the directory name: an
# exported COMPOSE_PROJECT_NAME would otherwise win and act on a different
# project, whose `<project>_default` network has a `db` alias pointing at a
# different database.
INSTANCE_PROJECT="sijilna"
# The seven keys deploy/docker-compose.yml interpolates every name a second
# instance would collide on. Their ABSENCE is what selects the live values, so
# their presence in the live .env is the outage this script guards against.
INSTANCE_IDENTITY_KEYS=(
    SIJILNA_PROJECT
    SIJILNA_DB_CONTAINER
    SIJILNA_APP_CONTAINER
    SIJILNA_WEB_CONTAINER
    SIJILNA_IMAGE
    SIJILNA_DB_VOLUME
    SIJILNA_WEB_PORT
)
MAIN_REF="refs/remotes/origin/main"
TAG_PREFIX="deploy-"

DB_CONTAINER="sijilna-db"
APP_CONTAINER="sijilna-app"
IMAGE="sijilna-app"
# The tag a rollback returns to. See the comment where it is written in main():
# on this host the image store is containerd's, and a rebuild that moves
# `sijilna-app:latest` garbage-collects the image it replaced, so holding the
# previous image by ID alone does not survive long enough to roll back to.
PREV_IMAGE_REF="$IMAGE:pre-deploy"
CONTAINERS="sijilna-db sijilna-app sijilna-web"

HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:3102/admin/login}"
HEALTH_ATTEMPTS="${HEALTH_ATTEMPTS:-60}"
HEALTH_INTERVAL="${HEALTH_INTERVAL:-2}"
FORCE_BUILD="${FORCE_BUILD:-0}"
REDEPLOY="${REDEPLOY:-0}"
KEEP_DUMPS="${KEEP_DUMPS:-10}"
MAX_LOG_BYTES="${MAX_LOG_BYTES:-1048576}"
# One alert per reason per 30 minutes. A 3-minute timer retrying a broken tag
# would otherwise send 20 identical messages an hour; a duplicate alert is a
# nuisance, an alert storm is an alert channel that gets muted.
ALERT_COOLDOWN_SECONDS="${ALERT_COOLDOWN_SECONDS:-1800}"

# ---------------------------------------------------------------- global state
SELF="$(readlink -f -- "${BASH_SOURCE[0]}")"
SECRET_VALUES=()
ALERTED=0
ALERT_ENV_LOADED=0
STACK_TOUCHED=0
LAST_HEALTH_CODE=""
LAST_CONTAINER_NOTE=""
IMAGE_INPUT_LIST=""
TARGET=""
PREV_REF=""
DUMP_FILE=""
MIGRATIONS_ADDED=0
PREV_IMAGE_ID=""
START_EPOCH=0

usage() {
    cat <<'USAGE'
usage: deploy.sh [--redeploy] [--force-build] [--help]

  (no argument)   deploy the newest deploy-* tag if it is not deployed yet
                  -- exactly what sijilna-deploy.timer runs
  --redeploy      deploy the newest tag even if the state file already names it
  --force-build   rebuild the app image even when its inputs did not change
  --help          this text

environment overrides: HEALTH_URL, HEALTH_ATTEMPTS, HEALTH_INTERVAL,
                       FORCE_BUILD, KEEP_DUMPS, SIJILNA_APP_DIR, SIJILNA_STATE_DIR
USAGE
}

# There is deliberately no flag that skips the instance-identity guard in
# preflight(). That check is the whole margin between one stray SIJILNA_* key
# and a deploy that brings the stack up against an empty volume, so it must not
# grow an off switch. An argument that turns it off must not be added here.
while (($# > 0)); do
    case "$1" in
        --redeploy) REDEPLOY=1 ;;
        --force-build) FORCE_BUILD=1 ;;
        -h | --help) usage; exit 0 ;;
        *)
            printf 'deploy.sh: unknown argument: %s\n' "$1" >&2
            usage >&2
            exit 2
            ;;
    esac
    shift
done

# ------------------------------------------------------------------- utilities
#
# Escape everything sed treats as special. Secret values are compared as plain
# strings, so a password containing `.` or `*` must not turn into a pattern that
# redacts half the log line.
sed_escape() {
    printf '%s' "$1" | sed -e 's/[][\\.^$*+?(){}|&#/]/\\&/g'
}

# Every value in SECRET_VALUES has its occurrences replaced before a line reaches
# the log, the journal or an alert. The log is capped at 1 MiB and rotates in
# place (see cap_log), so nothing here may print a secret even once.
mask() {
    local -a expr=()
    local v
    for v in ${SECRET_VALUES[@]+"${SECRET_VALUES[@]}"}; do
        [[ -n "$v" && ${#v} -ge 6 ]] || continue
        expr+=(-e "s#$(sed_escape "$v")#<redacted>#g")
    done
    if ((${#expr[@]} > 0)); then
        sed "${expr[@]}"
    else
        cat
    fi
}

# Size cap, in place, without any privilege.
#
# A `mv` to a .1 next to the log would be the usual rotation, but it needs write
# access to /var/log, which this script does not have and must not want: it runs
# as `mohamed`, and `mohamed` is not in the `syslog` group. So the cap keeps the
# newest half and rewrites the file through it -- one file, one owner, 0640,
# unchanged inode, no directory write. That is enough for a log that only grows
# when something is deployed.
cap_log() {
    local size=0
    if [[ -f "$LOG_FILE" ]]; then
        size="$(stat -c %s "$LOG_FILE" 2>/dev/null || echo 0)"
    fi
    if ((size > MAX_LOG_BYTES)); then
        mkdir -p "$RUN_DIR" 2>/dev/null || true
        if tail -c $((MAX_LOG_BYTES / 2)) "$LOG_FILE" \
            >"$RUN_DIR/log.tail" 2>/dev/null &&
            cat "$RUN_DIR/log.tail" >"$LOG_FILE" 2>/dev/null; then
            printf '%s log: capped, kept the newest %s bytes\n' \
                "$(date -Is)" "$((MAX_LOG_BYTES / 2))" >>"$LOG_FILE" || true
        fi
    fi
    # 0640 on every run: whatever recreated the file must not leave a fresh log
    # world-readable.
    chmod 640 "$LOG_FILE" 2>/dev/null || true
}

# Logs to the journal (stderr) AND to the file. A log write must never be the
# reason a deploy fails, hence the `|| true` on the file write.
log() {
    local line
    line="$(printf '%s %s' "$(date -Is)" "$*" | mask)"
    printf '%s\n' "$line" >&2
    printf '%s\n' "$line" >>"$LOG_FILE" 2>/dev/null || true
}

load_alert_env() {
    ((ALERT_ENV_LOADED == 0)) || return 0
    ALERT_ENV_LOADED=1
    if [[ ! -r "$ALERT_ENV" ]]; then
        log "alert: $ALERT_ENV is not readable - Telegram alerts are disabled"
        return 0
    fi
    # Deliberately NOT `set -a`: exporting these would put the bot token in the
    # environment of every child this script starts (git, docker, curl), where
    # /proc/<pid>/environ exposes it to any process running as this user.
    # shellcheck disable=SC1090 # path is configuration, not a literal
    if ! . "$ALERT_ENV" 2>/dev/null; then
        log "alert: could not source $ALERT_ENV - Telegram alerts are disabled"
        return 0
    fi
    [[ -n "${TELEGRAM_BOT_TOKEN:-}" ]] && SECRET_VALUES+=("$TELEGRAM_BOT_TOKEN")
    [[ -n "${TELEGRAM_CHAT_ID:-}" ]] && SECRET_VALUES+=("$TELEGRAM_CHAT_ID")
    return 0
}

# One Telegram message, reusing the homelab's existing bot credentials. Always
# returns 0: a failed alert must not mask (or replace) the deploy failure that
# caused it, and must not change the exit status the timer records.
alert() {
    local message="$1" body="" rc=0 now last_epoch last_key key
    load_alert_env

    key="$(printf '%s' "$message" | tr '\n' ' ' | cut -c1-200)"
    now="$(date +%s)"
    if [[ -r "$LAST_ALERT_FILE" ]]; then
        last_epoch="$(cut -d' ' -f1 "$LAST_ALERT_FILE" 2>/dev/null || true)"
        last_key="$(cut -d' ' -f2- "$LAST_ALERT_FILE" 2>/dev/null || true)"
        if [[ -n "$last_epoch" && "$last_key" == "$key" ]] &&
            ((now - last_epoch < ALERT_COOLDOWN_SECONDS)); then
            log "alert: suppressed, same reason alerted $((now - last_epoch))s ago"
            return 0
        fi
    fi
    printf '%s %s\n' "$now" "$key" >"$LAST_ALERT_FILE" 2>/dev/null || true

    if [[ -z "${TELEGRAM_BOT_TOKEN:-}" || -z "${TELEGRAM_CHAT_ID:-}" ]]; then
        log "alert: SKIPPED - no Telegram credentials; the journal has the reason"
        return 0
    fi

    log "alert: sending one Telegram message"
    # curl reads its configuration from stdin (-K -), so the bot token never
    # appears in argv where ps and /proc/<pid>/cmdline would show it. Same
    # technique as /opt/homelab/homelab-backup-alert.sh.
    body="$(curl -sS --max-time 20 -K - 2>&1 <<CURL_CONF
url = "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage"
data-urlencode = "chat_id=${TELEGRAM_CHAT_ID}"
data-urlencode = "text=${message}"
CURL_CONF
)" || rc=$?
    log "alert: curl_rc=${rc} response=$(printf '%s' "$body" | mask | tr -d '\n' | cut -c1-200)"
    return 0
}

alert_once() {
    if ((ALERTED == 0)); then
        ALERTED=1
        alert "$*"
    fi
    return 0
}

die() {
    log "FAILED: $*"
    alert_once "$(printf 'Sijilna CRM deploy FAILED on %s at %s: %s\nsee %s' \
        "$(hostname)" "$(date -Is)" "$*" "$LOG_FILE")"
    exit 1
}

# Anything that reaches `set -e` without going through die() still gets a
# message. The rollback path alerts through die() itself, so this only fires for
# the failures nobody predicted -- which are exactly the ones worth a message.
on_exit() {
    local rc=$?
    if ((rc != 0 && ALERTED == 0 && START_EPOCH > 0)); then
        alert_once "$(printf 'Sijilna CRM deploy FAILED on %s at %s: unexpected exit %s in %s\nsee %s' \
            "$(hostname)" "$(date -Is)" "$rc" "${TARGET:-?}" "$LOG_FILE")"
    fi
    return 0
}

# ------------------------------------------------------------------ git helpers
git_sijilna() {
    git -C "$APP_DIR" "$@"
}

configure_git_ssh() {
    # The deploy key is NOT in ~/.ssh: agent tooling guards that path, and this
    # key exists to be handed to exactly one command line. BatchMode=yes makes a
    # key problem fail immediately instead of hanging a timer run on a passphrase
    # prompt that nobody will ever see.
    export GIT_SSH_COMMAND="${GIT_SSH_COMMAND:-ssh -i $SSH_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o BatchMode=yes -o ConnectTimeout=20}"
}

refuse_dirty() {
    local dirt
    # Tracked modifications only: untracked files are not discarded by
    # `git checkout` (git refuses instead, which is also a safe failure), and a
    # stray build artifact must not be able to block every deploy forever.
    dirt="$(git_sijilna status --porcelain --untracked-files=no)"
    if [[ -n "$dirt" ]]; then
        die "the checkout at $APP_DIR has local modifications, refusing to touch it:$(printf '\n%s' "$dirt")"
    fi
    return 0
}

fetch_tags() {
    local out
    configure_git_ssh
    if ! out="$(git_sijilna fetch --prune --tags --quiet origin 2>&1)"; then
        printf '%s\n' "$out" >&2
        die "git fetch from origin failed: $(printf '%s' "$out" | tail -n 3)"
    fi
    return 0
}

# The newest deploy-* tag that is an ancestor of origin/main.
#
# `git describe` walks the graph from origin/main and answers in one process,
# which matters because this runs every three minutes and deploy tags accumulate
# (one per green push): a per-tag or per-commit `git tag --points-at` loop would
# get slower every week. --match keeps it to this pipeline's tags, --abbrev=0
# asks for the tag alone and not "<tag>-<n>-g<sha>".
#
# "Reachable from origin/main" IS the guarantee: it is the only safety property
# a tag needs on this host, because the workflow only tags a commit whose checks
# all passed. A tag pointing at a commit that was never merged, or that was
# merged and then rewritten away, cannot be seen here at all.
#
# Sorting tags by creator date would be the wrong selector: a tag published late
# on an old commit would win, and what matters is the commit, not the moment
# somebody published it.
pick_target() {
    local tag
    if ! tag="$(git_sijilna describe --tags --match "${TAG_PREFIX}*" \
        --abbrev=0 "$MAIN_REF" 2>/dev/null)"; then
        return 1
    fi
    [[ -n "$tag" ]] || return 1
    printf '%s\n' "$tag"
}

checkout_ref() {
    local ref="$1"
    if ! git_sijilna rev-parse --verify --quiet "${ref}^{commit}" >/dev/null; then
        log "checkout: cannot resolve '$ref' in $APP_DIR"
        return 1
    fi
    if ! git_sijilna checkout --quiet --detach "$ref"; then
        log "checkout: 'git checkout --detach $ref' failed"
        return 1
    fi
    return 0
}

# ----------------------------------------------------- instance identity guard
#
# THE OUTAGE THIS PREVENTS. deploy/docker-compose.yml derives every name a
# second instance would collide on -- the compose project, the three container
# names, the image name, the data volume and the published port -- from the
# seven SIJILNA_* keys listed in INSTANCE_IDENTITY_KEYS, each with a default
# that is EXACTLY the live instance's value. On this instance the correct state
# is that none of them is set.
#
# One stray SIJILNA_PROJECT or SIJILNA_WEB_PORT in /opt/sijilna/app/.env -- an
# operator pasting a second-instance file, or "filling the table in for
# clarity" -- makes the next `up -d` resolve a different project, different
# container names, `sijilna-<project>_sijilna-db-data` as a BRAND NEW EMPTY
# volume, and a second loopback port. Nothing is deleted (the real volume stays
# where it is), but the containers being deployed to are no longer the ones
# serving the site and the deploy health gate can pass against an empty
# database. That is an outage plus a silent data-fork.
#
# Two independent layers stop it, and neither is a substitute for the other:
#   1. this refusal, run at the top of preflight() -- before a fetch, a
#      checkout, a dump or a container -- and again inside compose(), which is
#      the only path from this script to the stack;
#   2. `compose()` pins `-p "$INSTANCE_PROJECT"`, so even an .env that resolves
#      cleanly cannot have its project moved by an exported
#      COMPOSE_PROJECT_NAME.
#
# A bypass flag must not be added: see the note above the argument parser.

# The .env files this script's own compose calls read, as absolute paths.
# On the live host deploy/.env is a symlink to ../.env, so resolve first and
# dedupe -- a message that names the same file twice reads like two problems.
env_files_under_guard() {
    local f real
    local -a seen=()
    for f in "$APP_DIR/deploy/.env" "$APP_DIR/.env"; do
        [[ -f "$f" ]] || continue
        real="$(readlink -f -- "$f" 2>/dev/null || true)"
        [[ -n "$real" ]] || real="$f"
        case " ${seen[*]-} " in *" $real "*) continue ;; esac
        seen+=("$real")
        printf '%s\n' "$real"
    done
}

# Key NAMES only, never values. This runs before anything has been read into
# SECRET_VALUES, and the same file holds APP_KEY and the database passwords, so
# its output goes to the log, the journal and Telegram as keys alone. A
# commented-out key is not a match: compose ignores it, so it cannot re-resolve
# the stack either.
instance_keys_in() {
    local file="$1"
    sed -n \
        -e 's/^[[:space:]]*SIJILNA_\([A-Za-z0-9_]*\)[[:space:]]*=.*/SIJILNA_\1/p' \
        -e 's/^[[:space:]]*export[[:space:]]\{1,\}SIJILNA_\([A-Za-z0-9_]*\)[[:space:]]*=.*/SIJILNA_\1/p' \
        -- "$file" 2>/dev/null || true
}

refuse_instance_identity_keys() {
    local file key report=""

    while IFS= read -r file; do
        while IFS= read -r key; do
            [[ -n "$key" ]] || continue
            report+="${report:+, }$key in $file"
        done < <(instance_keys_in "$file")
    done < <(env_files_under_guard)

    # An exported identity key outranks the env file in compose interpolation,
    # so it is the same outage from a different source. Only the seven are
    # checked: SIJILNA_APP_DIR, SIJILNA_STATE_DIR, SIJILNA_RUNNER,
    # SIJILNA_LOG_FILE and SIJILNA_ALERT_ENV are this script's own overrides and
    # are meant to be set (the systemd unit and the drills do exactly that).
    for key in "${INSTANCE_IDENTITY_KEYS[@]}"; do
        if [[ -n "$(printenv "$key" 2>/dev/null || true)" ]]; then
            report+="${report:+, }$key exported into this run's environment"
        fi
    done

    [[ -n "$report" ]] || return 0

    die "$(printf '%s\n    %s\n    %s\n    %s' \
        "instance-identity key(s) present in the live deployment, refusing to deploy: $report" \
        "Those keys re-resolve the compose project, container names, image, data volume and published port in deploy/docker-compose.yml. The live instance is defined by their ABSENCE (project sijilna, containers sijilna-db/sijilna-app/sijilna-web, image sijilna-app, volume sijilna-db-data, port 3102); with them set, 'up -d' would create a second stack on a new EMPTY volume beside the real one." \
        "NOTHING was fetched, checked out, built, dumped or restarted. Comment out or delete the keys named above, then run again. A second instance keeps its own SIJILNA_* keys in its OWN checkout (deploy/provision-instance.sh); they must never reach /opt/sijilna/app/.env." \
        "There is no flag that skips this check, and none should be added -- see deploy/README.md, 'Second instance'.")"
}

# ------------------------------------------------------------- docker helpers
compose() {
    # The guard runs here as well as in preflight(), on purpose: this function
    # is the ONLY path from this script to the stack, so a future call site (or
    # a future flag that skips preflight) still cannot reach compose with a
    # polluted .env. It reads a few hundred bytes of .env; it never calls the
    # daemon.
    refuse_instance_identity_keys
    # `-p "$INSTANCE_PROJECT"` on EVERY invocation -- build, up, stop, restart,
    # inspect. Without it the project comes from COMPOSE_PROJECT_NAME or the
    # file's `name:`, and a stray export moves every container, the network and
    # therefore the `db` alias with it. Do not drop it because the compose file
    # already carries `name: sijilna`: that line is the last fallback, not the
    # authority.
    docker compose -p "$INSTANCE_PROJECT" -f "$COMPOSE_FILE" "$@"
}

db_root_password() {
    local cenv
    cenv="$(docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' \
        "$DB_CONTAINER" 2>/dev/null)" || return 1
    printf '%s\n' "$cenv" | sed -n 's/^MYSQL_ROOT_PASSWORD=//p' | head -n 1
}

db_name() {
    local cenv
    cenv="$(docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' \
        "$DB_CONTAINER" 2>/dev/null)" || return 1
    printf '%s\n' "$cenv" | sed -n 's/^MYSQL_DATABASE=//p' | head -n 1
}

# ------------------------------------------------------------------- the dump
dump_database() {
    local db pw stamp out tmp bytes
    if [[ "$(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER" 2>/dev/null || echo false)" != "true" ]]; then
        log "dump: $DB_CONTAINER is not running"
        return 1
    fi
    if ! db="$(db_name)" || [[ -z "$db" ]]; then
        log "dump: MYSQL_DATABASE is empty in $DB_CONTAINER - refusing a nameless dump"
        return 1
    fi
    if ! pw="$(db_root_password)" || [[ -z "$pw" ]]; then
        log "dump: could not read MYSQL_ROOT_PASSWORD from $DB_CONTAINER"
        return 1
    fi
    SECRET_VALUES+=("$pw")

    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    out="$DUMP_DIR/sijilna-${stamp}-pre-${TARGET}.sql"
    tmp="$out.part"
    mkdir -p "$DUMP_DIR" || return 1
    chmod 700 "$DUMP_DIR" 2>/dev/null || true
    rm -f "$tmp"

    # Same flags as the nightly backup: --single-transaction for a consistent
    # InnoDB snapshot without locking the tables the CRM is serving from,
    # --quick to stream instead of buffering a whole table, --routines
    # --triggers --events for the objects a plain data dump drops, and
    # --databases so the output carries its own CREATE DATABASE / USE and needs
    # no manual setup to restore.
    if ! MYSQL_PWD="$pw" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" \
        mysqldump --single-transaction --quick --routines --triggers --events \
        --databases "$db" >"$tmp" 2>/dev/null </dev/null; then
        rm -f "$tmp"
        log "dump: mysqldump failed inside $DB_CONTAINER for database '$db'"
        return 1
    fi
    # mysqldump writes the trailer LAST, after every object, so its absence means
    # a truncated dump even when the exit status was 0 (container killed
    # mid-stream, disk full). A half-written .sql is worse than none at all.
    if ! tail -n 5 "$tmp" | grep -q '^-- Dump completed'; then
        rm -f "$tmp"
        log "dump: output carries no '-- Dump completed' trailer - truncated"
        return 1
    fi
    bytes="$(stat -c %s "$tmp" 2>/dev/null || echo 0)"
    if ((bytes == 0)); then
        rm -f "$tmp"
        log "dump: output is empty"
        return 1
    fi
    chmod 600 "$tmp" 2>/dev/null || true
    mv -f "$tmp" "$out" || return 1
    DUMP_FILE="$out"
    log "dump: $out (${bytes} bytes, database '$db', container $DB_CONTAINER)"

    # Keep the last KEEP_DUMPS, newest first. sed consumes the whole stream, so
    # nothing here closes a pipe early and trips `pipefail`.
    local old
    while IFS= read -r old; do
        rm -f "$old"
        log "dump: pruned $old"
    done < <(find "$DUMP_DIR" -maxdepth 1 -type f -name 'sijilna-*.sql' \
        -printf '%T@ %p\n' 2>/dev/null | sort -rn |
        sed -n "$((KEEP_DUMPS + 1)),\$p" | cut -d' ' -f2-)
    return 0
}

restore_database() {
    local file="$1" pw
    [[ -s "$file" ]] || { log "restore: $file is missing or empty"; return 1; }
    if ! pw="$(db_root_password)" || [[ -z "$pw" ]]; then
        log "restore: could not read MYSQL_ROOT_PASSWORD from $DB_CONTAINER"
        return 1
    fi
    SECRET_VALUES+=("$pw")
    log "restore: importing $file into $DB_CONTAINER"
    if ! MYSQL_PWD="$pw" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" \
        mysql -u root <"$file"; then
        log "restore: import failed"
        return 1
    fi
    log "restore: done"
    return 0
}

# ------------------------------------------------------- change classification
#
# Paths that can change without changing the image. Kept in sync with
# .dockerignore, which is the actual filter -- a path listed here but not there
# is still copied into the build context, and one listed there but not here
# forces a pointless rebuild. Everything else is an image input: a false
# "changed" costs at most a cached rebuild, a false "unchanged" ships stale
# code, so when the two lists disagree the safe direction is to rebuild.
#
# The deploy-only files under deploy/ are here because NOTHING at run time
# reads them. php.Dockerfile does `COPY . .`, so they would otherwise sit in the
# image, but no PHP code opens a compose file, the runner or a provision script:
# they are host-side operators' files. Leaving them classified as image inputs
# cost a full `composer install` (366 s and 500 s in two measured runs) for a
# one-line change to, say, the compose file.
#
# deploy/docker/* is deliberately NOT here: php.Dockerfile COPYs php.ini,
# php-fpm.conf and entrypoint.sh into the image, and nginx.conf is bind-mounted
# into the `web` container. All four are real inputs.
NON_IMAGE_GLOBS=(
    'docs/*' '.github/*' '.agents/*' '.ai/*' '.claude/*' '.codex/*'
    '.cursor/*' '.kilocode/*' '.beads/*' 'tests/*' 'bin/*' 'brand/*'
    'ignorables/*' 'data/*' 'docker-compose-collection/*' 'playwright/*'
    'node_modules/*' 'vendor/*' 'storage/*' '*.md' '*.hot' '.editorconfig'
    '.styleci.yml' '.gitignore' '.gitattributes' 'deploy/README.md'
    'deploy/deploy.sh' 'deploy/docker-compose.yml' 'deploy/provision-instance.sh'
)

image_inputs_changed() {
    local from="$1" to="$2" path glob skip
    local -a changed=()
    IMAGE_INPUT_LIST=""
    while IFS= read -r path; do
        [[ -n "$path" ]] && changed+=("$path")
    done < <(git_sijilna diff --name-only "$from" "$to")
    ((${#changed[@]} > 0)) || return 1

    for path in "${changed[@]}"; do
        skip=0
        for glob in "${NON_IMAGE_GLOBS[@]}"; do
            # shellcheck disable=SC2053 # the glob match is the point
            if [[ "$path" == $glob ]]; then
                skip=1
                break
            fi
        done
        if ((skip == 0)); then
            IMAGE_INPUT_LIST+="${IMAGE_INPUT_LIST:+, }$path"
        fi
    done
    [[ -n "$IMAGE_INPUT_LIST" ]]
}

migrations_added() {
    local from="$1" to="$2" path
    local -a added=()
    while IFS= read -r path; do
        [[ -n "$path" ]] && added+=("$path")
    # The backslash matters: without it the newline ends the command inside the
    # process substitution and the pathspec becomes a command of its own.
    done < <(git_sijilna diff --name-only --diff-filter=A "$from" "$to" \
        -- '*Database/Migrations/*' 'database/migrations/*')
    ((${#added[@]} > 0))
}

# -------------------------------------------------------------- health gate
health_probe() {
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 \
        "$HEALTH_URL" 2>/dev/null)" || code="000"
    LAST_HEALTH_CODE="$code"
    [[ "$code" == "200" ]]
}

containers_ok() {
    local c state health
    for c in $CONTAINERS; do
        state="$(docker inspect -f '{{.State.Status}}' "$c" 2>/dev/null)" ||
            state="missing"
        if [[ "$state" != "running" ]]; then
            LAST_CONTAINER_NOTE="$c status=$state"
            return 1
        fi
        health="$(docker inspect -f \
            '{{if .State.Health}}{{.State.Health.Status}}{{end}}' \
            "$c" 2>/dev/null)" || health=""
        # A container with no healthcheck reports nothing; a database reporting
        # "starting" is not up yet. Both "running" and "healthy" are required.
        if [[ -n "$health" && "$health" != "healthy" ]]; then
            LAST_CONTAINER_NOTE="$c health=$health"
            return 1
        fi
    done
    LAST_CONTAINER_NOTE=""
    return 0
}

wait_healthy() {
    local label="$1" attempt
    for ((attempt = 1; attempt <= HEALTH_ATTEMPTS; attempt++)); do
        if health_probe && containers_ok; then
            log "health: $label OK on probe ${attempt}/${HEALTH_ATTEMPTS} (HTTP 200 from $HEALTH_URL, three containers running)"
            return 0
        fi
        if ((attempt < HEALTH_ATTEMPTS)); then
            sleep "$HEALTH_INTERVAL"
        fi
    done
    log "health: $label FAILED after $HEALTH_ATTEMPTS probes (last HTTP ${LAST_HEALTH_CODE:-?} from $HEALTH_URL; ${LAST_CONTAINER_NOTE:-containers were running})"
    return 1
}

# ------------------------------------------------------------------ the deploy
#
# Make the app container actually run `sijilna-app:latest`.
#
# `up -d` alone is not enough to make a rebuilt image take effect here. This host
# runs Docker 29 on the containerd image store: compose records the image a
# container was created from as a *manifest-list* digest, and a locally built
# image that was never pushed has no such digest to compare against, so a rebuild
# that moves `sijilna-app:latest` can leave the old container running -- serving
# the OLD code and answering 200, which the health gate below cannot tell apart
# from a good deploy. Measured on this box: the container stayed on image A while
# `sijilna-app:latest` was image B, and `up -d --dry-run` still said "Running".
#
# So compare the two image IDs and replace the container explicitly when they
# disagree. --no-deps keeps the database and nginx out of it, so only the app
# restarts: about four seconds of 502 through the tunnel, measured.
ensure_app_on_latest() {
    local running_image target_image
    running_image="$(docker inspect -f '{{.Image}}' "$APP_CONTAINER" 2>/dev/null || true)"
    target_image="$(docker image inspect -f '{{.Id}}' "$IMAGE:latest" 2>/dev/null || true)"
    if [[ -z "$running_image" || -z "$target_image" ]]; then
        log "compose: cannot compare the running image with $IMAGE:latest - leaving the stack as it is"
        return 0
    fi
    if [[ "$running_image" == "$target_image" ]]; then
        log "compose: $APP_CONTAINER is already on $IMAGE:latest"
        return 0
    fi
    log "compose: $APP_CONTAINER runs ${running_image#sha256:} but $IMAGE:latest is ${target_image#sha256:} - recreating the app container"
    compose up -d --force-recreate --no-deps app || return 1
    return 0
}

deploy_forward() {
    log "checkout: $TARGET"
    checkout_ref "$TARGET" || return 1

    if ((FORCE_BUILD == 1)); then
        log "build: rebuilding $IMAGE (FORCE_BUILD=1)"
        compose build app || return 1
    elif [[ -n "$IMAGE_INPUT_LIST" ]]; then
        log "build: rebuilding $IMAGE (image inputs changed: $IMAGE_INPUT_LIST)"
        compose build app || return 1
    else
        # Not an approximation of a cached build: the image is built from this
        # checkout with .dockerignore filtering, so if nothing inside the build
        # context changed, the image a rebuild would produce is byte-identical
        # and `up -d` will not recreate anything. Rebuilding anyway would re-run
        # `composer install` (the COPY precedes it), which is minutes of network
        # work for the same bytes.
        log "build: skipped, no image inputs changed since $PREV_REF (reusing $IMAGE:latest)"
    fi

    log "compose: up -d"
    compose up -d || return 1
    STACK_TOUCHED=1
    ensure_app_on_latest || return 1
    return 0
}

rollback() {
    local reason="$1" rc=0
    log "rollback: $reason -- returning to $PREV_REF"

    if ! checkout_ref "$PREV_REF"; then
        log "rollback: cannot check out $PREV_REF - the stack is left as it is"
        return 1
    fi

    # Put the image that was running before this deploy back under the name the
    # compose file asks for. That is what keeps a rollback cheap: the running
    # image is tagged aside before the forward build moves `latest`, so putting
    # it back is a tag operation, not a six-minute composer install. The ID is
    # still tried second, for a host whose image store keeps unreferenced images.
    if docker image inspect "$PREV_IMAGE_REF" >/dev/null 2>&1 &&
        docker tag "$PREV_IMAGE_REF" "$IMAGE:latest"; then
        log "rollback: $IMAGE:latest is $PREV_IMAGE_REF again (no rebuild)"
    elif [[ -n "$PREV_IMAGE_ID" ]] &&
        docker image inspect "$PREV_IMAGE_ID" >/dev/null 2>&1 &&
        docker tag "$PREV_IMAGE_ID" "$IMAGE:latest"; then
        log "rollback: $IMAGE:latest is $PREV_IMAGE_ID again (no rebuild)"
    else
        log "rollback: the pre-deploy image is gone - rebuilding from $PREV_REF"
        compose build app || rc=1
    fi
    ((rc == 0)) || { log "rollback: could not restore a runnable image"; return 1; }

    if ((MIGRATIONS_ADDED == 1)); then
        # The failed deploy ran migrate --force at container start, so the schema
        # moved forward. The old code can answer against it for a moment, but the
        # pre-deploy dump is the state that was known good, so take the schema
        # back with it. Stopping the two request-serving containers first keeps
        # the site from answering against a half-restored schema.
        log "rollback: this deploy added migrations - stopping app/web to restore the dump"
        compose stop app web || true
    fi

    log "rollback: compose up -d"
    compose up -d || { log "rollback: up -d failed"; return 1; }
    # Same containerd-store caveat as deploy_forward: moving `latest` back is not
    # enough, the container has to be replaced onto it.
    ensure_app_on_latest || { log "rollback: could not move the app container back"; return 1; }

    if ((MIGRATIONS_ADDED == 1)); then
        if [[ -n "$DUMP_FILE" ]] && restore_database "$DUMP_FILE"; then
            log "rollback: restarting app on the restored schema"
            compose restart app || { log "rollback: restart failed"; return 1; }
        else
            log "rollback: COULD NOT RESTORE $DUMP_FILE - the schema is new and the code is old"
            return 1
        fi
    fi

    if wait_healthy "rollback"; then
        log "rollback: $PREV_REF is serving again"
        return 0
    fi
    log "rollback: the health gate still fails after returning to $PREV_REF"
    return 1
}

# Re-install the runner from the checkout when the tag being deployed carries a
# different one. Written to a temporary path and renamed, so the file this
# process was started from is never modified in place.
refresh_runner() {
    local candidate="$APP_DIR/deploy/deploy.sh"
    if [[ ! -f "$candidate" ]]; then
        log "runner: this tag has no deploy/deploy.sh - keeping the installed copy at $RUNNER"
        return 0
    fi
    if cmp -s "$candidate" "$RUNNER"; then
        return 0
    fi
    if ! bash -n "$candidate" 2>/dev/null; then
        log "runner: $candidate does not parse - NOT installing it"
        return 0
    fi
    if install -m 0750 "$candidate" "$RUNNER.new" && mv -f "$RUNNER.new" "$RUNNER"; then
        log "runner: installed the copy from $TARGET at $RUNNER (takes effect on the next run)"
    else
        log "runner: could not install $candidate - keeping the current runner"
    fi
    return 0
}

# ---------------------------------------------------------------- state helpers
read_state() {
    if [[ -r "$STATE_FILE" ]]; then
        head -n 1 "$STATE_FILE" 2>/dev/null || true
    fi
}

write_state() {
    printf '%s\n' "$1" >"$STATE_FILE" 2>/dev/null ||
        { log "state: could not write $STATE_FILE"; return 1; }
    return 0
}

# ------------------------------------------------------------------ preflight
preflight() {
    local bin missing=()
    # FIRST, before a single binary is looked for and long before anything is
    # fetched, checked out, dumped or restarted: the live instance must not be
    # re-resolved. See refuse_instance_identity_keys above.
    refuse_instance_identity_keys
    for bin in git docker curl flock; do
        command -v "$bin" >/dev/null 2>&1 || missing+=("$bin")
    done
    if ((${#missing[@]} > 0)); then
        die "missing required command(s): ${missing[*]}"
    fi
    if [[ ! -d "$APP_DIR/.git" ]]; then
        die "$APP_DIR is not a git checkout"
    fi
    if [[ ! -f "$COMPOSE_FILE" ]]; then
        die "$COMPOSE_FILE is missing from the checkout"
    fi
    # Not a stack invocation, so no -p: `docker compose version` resolves no
    # project, reads no compose file and no .env. It only proves the v2 plugin
    # is on PATH -- every call that touches the stack goes through compose().
    if ! docker compose version >/dev/null 2>&1; then
        die "docker compose is not available to $(id -un)"
    fi
    return 0
}

# ----------------------------------------------------------------------- main
main() {
    # Run the body from a private copy whenever this file lives inside the
    # checkout: `git checkout` below replaces deploy/deploy.sh in the working
    # tree, and a script must not be reading itself off the disk an operation is
    # rewriting. The installed copy the timer uses is outside the checkout and
    # is only ever replaced by an atomic rename, so this costs nothing there.
    if [[ "${SIJILNA_DEPLOY_REEXEC:-0}" != "1" && "$SELF" == "$APP_DIR"/* ]]; then
        if ! mkdir -p "$RUN_DIR" 2>/dev/null; then
            log "cannot create $RUN_DIR"
            exit 1
        fi
        if ! install -m 0700 -- "$SELF" "$RUN_DIR/deploy.sh"; then
            log "cannot copy $SELF to $RUN_DIR/deploy.sh"
            exit 1
        fi
        exec env SIJILNA_DEPLOY_REEXEC=1 bash -- "$RUN_DIR/deploy.sh" "$@"
    fi

    cap_log
    trap on_exit EXIT
    if [[ ! -w "$LOG_FILE" ]]; then
        printf '%s WARNING: %s is not writable by %s - the journal is the only record\n' \
            "$(date -Is)" "$LOG_FILE" "$(id -un)" >&2
    fi

    # ---- 1. single instance ------------------------------------------------
    if [[ ! -d "$STATE_DIR" || ! -w "$STATE_DIR" ]]; then
        die "$STATE_DIR is missing or not writable by $(id -un)"
    fi
    exec 9>"$LOCK_FILE" || die "cannot open $LOCK_FILE"
    if ! flock -n 9; then
        log "another deploy is running (lock: $LOCK_FILE) - exiting, not queueing"
        exit 0
    fi

    preflight

    # ---- 2. what should be deployed? ---------------------------------------
    fetch_tags

    if ! TARGET="$(pick_target)"; then
        # Nothing has ever been tagged deployable. This is the state of a fresh
        # installation, and it must not write a log line every three minutes.
        exit 0
    fi

    PREV_REF="$(read_state)"
    if [[ -z "$PREV_REF" ]]; then
        # First run since this pipeline was installed: whatever is on disk now
        # is what is being served, so that is what a rollback returns to.
        PREV_REF="$(git_sijilna rev-parse HEAD)"
    fi

    # A state file naming a ref this checkout no longer has (the tag was deleted,
    # the branch was rewritten) must not become a deploy that cannot be rolled
    # back: fall back to what is checked out now.
    if ! git_sijilna rev-parse --verify --quiet "${PREV_REF}^{commit}" \
        >/dev/null 2>&1; then
        PREV_REF="$(git_sijilna rev-parse HEAD)"
    fi

    if [[ "$TARGET" == "$PREV_REF" ]] && ((REDEPLOY == 0)); then
        exit 0
    fi

    # The cheap path is over: from here the run is logged, and may alert.
    START_EPOCH="$(date +%s)"
    load_alert_env
    mkdir -p "$RUN_DIR" "$DUMP_DIR" 2>/dev/null || true
    log "=== deploy $TARGET starting (was: $PREV_REF, health ${HEALTH_URL} x${HEALTH_ATTEMPTS})"

    # ---- 3. refuse to touch a dirty checkout ------------------------------
    refuse_dirty

    # ---- 4. what will this change? ----------------------------------------
    if image_inputs_changed "$PREV_REF" "$TARGET"; then
        log "diff: image inputs changed since $PREV_REF"
    else
        log "diff: no image inputs changed since $PREV_REF"
    fi
    if migrations_added "$PREV_REF" "$TARGET"; then
        MIGRATIONS_ADDED=1
        log "diff: this deploy adds migrations - a rollback will restore the dump"
    fi

    # The image the app container is running right now -- captured before
    # anything is touched, because that is the definition of "the previous
    # deploy" -- plus a tag that keeps it alive.
    #
    # The tag is not belt-and-braces for the ID, it is the mechanism. This host
    # runs Docker 29 on the containerd image store: when a rebuild moves
    # `sijilna-app:latest`, the image that was there is collected even though a
    # container is still running from it, so `docker image inspect <old id>`
    # stops resolving and an ID alone cannot be rolled back to. A tag is a
    # reference, so the image survives until the next deploy overwrites the tag.
    PREV_IMAGE_ID="$(docker inspect -f '{{.Image}}' "$APP_CONTAINER" 2>/dev/null || true)"
    if [[ -z "$PREV_IMAGE_ID" ]]; then
        log "image: could not read the running image ID - a rollback would have to rebuild"
    elif docker tag "$PREV_IMAGE_ID" "$PREV_IMAGE_REF" 2>/dev/null &&
        [[ "$(docker image inspect -f '{{.Id}}' "$PREV_IMAGE_REF" 2>/dev/null)" == "$PREV_IMAGE_ID" ]]; then
        log "image: running image $PREV_IMAGE_ID, kept as $PREV_IMAGE_REF for a rollback"
    else
        log "image: running image $PREV_IMAGE_ID, but it could not be kept as $PREV_IMAGE_REF"
    fi

    # ---- 5. dump before anything is touched -------------------------------
    dump_database || die "could not dump the database before deploying - refusing to deploy without a restore point"

    # ---- 6. deploy --------------------------------------------------------
    if ! deploy_forward; then
        # Nothing was started, so the stack is still serving the previous code.
        # Put the checkout back and stop: a rebuild failure is not a reason to
        # touch the database or to roll anything back.
        if ((STACK_TOUCHED == 0)); then
            log "deploy: failed before the stack was touched; nothing was restarted"
            checkout_ref "$PREV_REF" || true
            die "deploy of $TARGET failed while building - the running stack was left untouched"
        fi
        rollback "deploy of $TARGET failed"
        write_state "$PREV_REF" || true
        die "deploy of $TARGET failed; rolled back to $PREV_REF"
    fi

    # ---- 7. health gate ---------------------------------------------------
    if ! wait_healthy "forward"; then
        rollback "health gate failed after deploying $TARGET" ||
            alert_once "$(printf 'Sijilna CRM rollback FAILED on %s at %s: %s is running and the health gate does not pass\nsee %s' \
                "$(hostname)" "$(date -Is)" "$PREV_REF" "$LOG_FILE")"
        write_state "$PREV_REF" || true
        die "deploy of $TARGET failed the health gate; rolled back to $PREV_REF"
    fi

    # ---- 8. commit the run ------------------------------------------------
    write_state "$TARGET" || log "state: WARNING could not record $TARGET - the next run will deploy it again"
    refresh_runner
    log "=== deploy $TARGET done in $(( $(date +%s) - START_EPOCH ))s (dump: ${DUMP_FILE:-none})"
    return 0
}

main "$@"
