#!/usr/bin/env bash
#
# restore-rehearsal.sh - restore a dump into a THROWAWAY database and prove the restore is real.
#
# A backup nobody has restored is a hypothesis. This script is the rehearsal: it takes the newest dump
# `scripts/backup-db.sh` wrote (or one you name), verifies it against its manifest, restores it into a
# scratch database whose name must end in `_rehearsal`, compares the restored row counts with the
# counts the manifest recorded, proves an administrator can actually sign in against the restored
# data, proves the LIVE database was not touched, and then drops the scratch database.
#
# WHAT IT NEVER DOES
#   * It never writes to the live database. The only statement it aims at the live database is a
#     SELECT, and it re-reads the live counts at the end to prove they are unchanged.
#   * It never touches `krayin_test` - that database belongs to the Pest suite.
#   * It never imports a dump into the database the dump names. `mysqldump --databases` emits
#     `CREATE DATABASE`/`USE` for the source database, so importing the file as-is would write to the
#     LIVE database. Those two statements are stripped, the stripped file is asserted to contain no
#     `CREATE DATABASE` or `USE` statement at all, and the import is aimed at the scratch database
#     with `mysql -D`. That assertion is the difference between a rehearsal and the 2026-11-08
#     incident happening a second time.
#
# Usage:
#   bash scripts/restore-rehearsal.sh                     # newest dump, default scratch database
#   bash scripts/restore-rehearsal.sh --dump FILE         # a specific dump (name or absolute path)
#   bash scripts/restore-rehearsal.sh --database NAME     # override the scratch name (must end _rehearsal)
#   bash scripts/restore-rehearsal.sh --keep              # leave the scratch database in place
#   bash scripts/restore-rehearsal.sh --help
#
# Environment overrides:
#   BACKUP_DIR      where the dumps live                 [$HOME/backups/crm-platform]
#   DB_CONTAINER    the MySQL container                  [crm-platform-mysql]
#   DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_ROOT_PASSWORD   caller's environment first, then .env
#
# Exit status: 0 only when every check passed AND the scratch database is gone (or --keep was given).
# The last line of a successful run is machine-greppable: `rehearsal: OK ...`.
#
# Operator procedure: docs/ops/backup-and-restore.md.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

ENV_FILE="$PROJECT_ROOT/.env"
PHPUNIT_FILE="$PROJECT_ROOT/phpunit.xml"
DB_CONTAINER="${DB_CONTAINER:-${KRAYIN_DB_CONTAINER:-crm-platform-mysql}}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/crm-platform}"
REHEARSAL_DB="${REHEARSAL_DB:-krayin_backup_rehearsal}"
DUMP_ARG=''
KEEP=0

COUNT_TABLES='leads persons organizations products quotes quote_items activities emails users'

while [ $# -gt 0 ]; do
    case "$1" in
        --dump)     shift; DUMP_ARG="${1:-}"; [ -n "$DUMP_ARG" ] || { echo "rehearsal: --dump needs a file" >&2; exit 2; }; shift ;;
        --database) shift; REHEARSAL_DB="${1:-}"; [ -n "$REHEARSAL_DB" ] || { echo "rehearsal: --database needs a name" >&2; exit 2; }; shift ;;
        --keep)     KEEP=1; shift ;;
        -h|--help)  awk 'NR > 1 { if ($0 !~ /^#/) exit; print }' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "rehearsal: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
done

log()  { printf 'rehearsal: %s\n' "$*"; }
warn() { printf 'rehearsal: WARN: %s\n' "$*" >&2; }
die()  { printf 'rehearsal: FAIL: %s\n' "$*" >&2; exit 1; }

# Elapsed wall clock between two `now_ms` readings, in milliseconds, so a sub-second dump or verify is
# not reported as `0s`. Integer arithmetic only: bash has no floating point.
now_ms() { date +%s%3N; }
since_ms() {
    local elapsed=$(( $(now_ms) - $1 ))
    printf '%d.%03ds' $((elapsed / 1000)) $((elapsed % 1000))
}

# --------------------------------------------------------------- resolution
read_env() {
    grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- \
        | sed -e 's/^"//' -e 's/"$//' || true
}

env_value() {
    local name="$1" value
    value="$(printenv "$name" 2>/dev/null || true)"
    if [ -n "$value" ]; then printf '%s' "$value"; else read_env "$name"; fi
}

[ -f "$ENV_FILE" ] || die ".env not found at $ENV_FILE"

LIVE_DB="$(env_value DB_DATABASE)"
APP_USER="$(env_value DB_USERNAME)"
APP_PASS="$(env_value DB_PASSWORD)"
ROOT_PASS="$(env_value DB_ROOT_PASSWORD)"
TEST_DB="$(sed -nE 's/.*name="DB_DATABASE"[[:space:]]+value="([^"]+)".*/\1/p' "$PHPUNIT_FILE" 2>/dev/null | tail -1)"

# ------------------------------------------------------------------- guards
[ -n "$LIVE_DB" ] || die "DB_DATABASE is empty in the environment and in .env"
if [ -z "$APP_USER" ] || [ -z "$APP_PASS" ]; then
    die "DB_USERNAME / DB_PASSWORD are empty in .env"
fi
docker inspect -f '{{.State.Running}}' "$DB_CONTAINER" >/dev/null 2>&1 \
    || die "container '$DB_CONTAINER' is not running (start it with: make setup)"

# A cached config beats the DB_DATABASE this script exports, so the login check could read (and write)
# the wrong database. Same refusal `make test` makes, for the same measured reason.
[ ! -f bootstrap/cache/config.php ] \
    || die "bootstrap/cache/config.php exists and would ignore DB_DATABASE; run: php artisan config:clear"

case "$REHEARSAL_DB" in
    *_rehearsal) ;;
    *) die "the scratch database name '$REHEARSAL_DB' does not end in '_rehearsal'. This script drops it, so the name must make it impossible to aim at a real database." ;;
esac
[ "$REHEARSAL_DB" != "$LIVE_DB" ] || die "the scratch database name equals the live database '$LIVE_DB'"
[ -z "$TEST_DB" ] || [ "$REHEARSAL_DB" != "$TEST_DB" ] || die "the scratch database name equals the test database '$TEST_DB' (pinned in phpunit.xml)"
case "$REHEARSAL_DB" in
    mysql|information_schema|performance_schema|sys) die "'$REHEARSAL_DB' is a MySQL system schema" ;;
esac

# ---------------------------------------------------------------- the dump
if [ -n "$DUMP_ARG" ]; then
    case "$DUMP_ARG" in
        /*) DUMP_FILE="$DUMP_ARG" ;;
        *)  DUMP_FILE="$BACKUP_DIR/$DUMP_ARG" ;;
    esac
else
    # Same naming rule backup-db.sh writes and prunes by, so the newest rehearsable dump is the newest
    # one this repository produced -- never a hand-taken file that has no manifest.
    newest=''
    for f in "$BACKUP_DIR"/*.sql.gz; do
        [ -e "$f" ] || continue
        b="$(basename "$f")"
        [[ "$b" =~ ^[A-Za-z0-9_.-]+-[0-9]{8}T[0-9]{6}Z\.sql\.gz$ ]] || continue
        if [ -z "$newest" ] || [[ "$b" > "$newest" ]]; then newest="$b"; fi
    done
    [ -n "$newest" ] || die "no dump found in $BACKUP_DIR - run: bash scripts/backup-db.sh"
    DUMP_FILE="$BACKUP_DIR/$newest"
fi
[ -f "$DUMP_FILE" ] || die "$DUMP_FILE does not exist"

MANIFEST="${DUMP_FILE%.sql.gz}.manifest"
[ -f "$MANIFEST" ] || die "$MANIFEST is missing - this dump has no manifest, so its checksum and counts cannot be verified and the rehearsal could not prove anything"

SOURCE_DB="$(sed -nE 's/^database=(.*)$/\1/p' "$MANIFEST" | tail -1)"
[ -n "$SOURCE_DB" ] || die "$MANIFEST does not record a database name"
if [ "$SOURCE_DB" != "$LIVE_DB" ]; then
    warn "the dump was taken from database '$SOURCE_DB' while this checkout resolves '$LIVE_DB'. The restore is still valid; the live comparison below is between different databases."
fi

manifest_rows() { # <table> -> the count the manifest recorded, or empty
    sed -nE "s/^rows_$1=(.*)$/\1/p" "$MANIFEST" | tail -1
}

manifest_value() { sed -nE "s/^$1=(.*)$/\1/p" "$MANIFEST" | tail -1; }

# ---------------------------------------------------------------- SQL helpers
# CREATE/DROP on the scratch database needs a privileged user; the application user's grant covers the
# live database only. Root is used when .env has DB_ROOT_PASSWORD (this machine's container); otherwise
# the application user is tried, which is root on the CI service container.
admin_sql() {
    local sql="$1"
    if [ -n "$ROOT_PASS" ]; then
        MYSQL_PWD="$ROOT_PASS" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" mysql -uroot -N -B -e "$sql" </dev/null
    else
        MYSQL_PWD="$APP_PASS" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" mysql -u"$APP_USER" -N -B -e "$sql" </dev/null
    fi
}

db_exists() {
    local n
    n="$(admin_sql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$1'")" || die "could not query information_schema as a privileged user"
    [ "$n" = '1' ]
}

counts_of() { # <database> -> "table<TAB>count" lines
    local db="$1" q='' t
    for t in $COUNT_TABLES; do
        q="${q}${q:+ UNION ALL }SELECT '${t}', COUNT(*) FROM \`${db}\`.\`${t}\`"
    done
    MYSQL_PWD="$APP_PASS" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" \
        mysql -u"$APP_USER" -N -B -e "$q" 2>/dev/null </dev/null
}

tables_of() {
    admin_sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$1'" \
        || die "could not count tables in '$1'"
}

# --------------------------------------------------------------- the work
WORK_BASE="$BACKUP_DIR/.rehearsal-work"
mkdir -p "$WORK_BASE" || die "could not create $WORK_BASE"
chmod 700 "$WORK_BASE" 2>/dev/null || true
WORK_DIR="$(mktemp -d "$WORK_BASE/run.XXXXXX")" || die "could not create a work directory under $WORK_BASE"
chmod 700 "$WORK_DIR" 2>/dev/null || true
SCRATCH_DB_CREATED=0
cleanup() {
    # The decompressed SQL holds live client rows and password hashes; it never outlives the run.
    rm -rf "$WORK_DIR" 2>/dev/null || true
    if [ "$SCRATCH_DB_CREATED" -eq 1 ] && [ "$KEEP" -eq 0 ] && [ "${REHEARSAL_FINISHED:-0}" -ne 1 ]; then
        printf 'rehearsal: cleaning up after an incomplete run - dropping %s\n' "$REHEARSAL_DB" >&2
        admin_sql "DROP DATABASE IF EXISTS \`$REHEARSAL_DB\`" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

log "dump    $(basename "$DUMP_FILE")"
log "source  database '$SOURCE_DB', manifest says $(manifest_value tables) tables, counts_stable=$(manifest_value counts_stable)"

# 1. Integrity, using the same verify path an operator would run by hand.
log 'verify  checking the dump against its manifest'
T_VERIFY_START=$(now_ms)
bash "$SCRIPT_DIR/backup-db.sh" --verify "$DUMP_FILE" || die "the dump does not verify against its manifest - do not restore it"
T_VERIFY=$(since_ms "$T_VERIFY_START")

# 2. The live state, recorded before anything is created, so it can be proved unchanged afterwards.
log "live    reading '$LIVE_DB' counts (before)"
LIVE_BEFORE="$(counts_of "$LIVE_DB")" || die "could not read the live row counts"
LIVE_TABLES_BEFORE="$(tables_of "$LIVE_DB")" || die "could not count the live tables"

# 3. A scratch database, and never the live one.
if db_exists "$REHEARSAL_DB"; then
    log "scratch '$REHEARSAL_DB' already exists - dropping it first"
    admin_sql "DROP DATABASE \`$REHEARSAL_DB\`" || die "could not drop the stale scratch database '$REHEARSAL_DB'"
fi
admin_sql "CREATE DATABASE \`$REHEARSAL_DB\` DEFAULT CHARACTER SET utf8mb4" </dev/null \
    || die "could not create the scratch database '$REHEARSAL_DB' (does the .env user have CREATE?)"
SCRATCH_DB_CREATED=1
admin_sql "GRANT ALL PRIVILEGES ON \`$REHEARSAL_DB\`.* TO '$APP_USER'@'%'" >/dev/null 2>&1 || \
    warn "could not grant '$APP_USER' access to '$REHEARSAL_DB' (fine when that user is already root)"
log "scratch created '$REHEARSAL_DB'"

# 4. The import. `--databases` dumps carry CREATE DATABASE and USE for the SOURCE database; importing
#    them unchanged would write to the live database. They are stripped, and the stripped file is
#    asserted to have no database-switching statement left before a single row is loaded.
log 'restore decompressing and neutralising the source database statements'
IMPORT_SQL="$WORK_DIR/import.sql"
gzip -dc "$DUMP_FILE" | sed -E \
    -e "/^CREATE DATABASE .*\`${SOURCE_DB}\`/d" \
    -e "/^USE \`${SOURCE_DB}\`;/d" \
    >"$IMPORT_SQL" || die "could not decompress $DUMP_FILE"
chmod 600 "$IMPORT_SQL" 2>/dev/null || true

if [ ! -s "$IMPORT_SQL" ]; then
    die "the filtered SQL is empty - nothing would be restored"
fi
# --single-transaction dumps contain no further USE statements, so zero matches is the expected result
# and any match means the filter above was wrong. This is the guard that keeps a rehearsal from
# becoming a second 2026-11-08.
STRAY="$(grep -cE '^(USE |CREATE DATABASE|DROP DATABASE)' "$IMPORT_SQL" || true)"
[ "$STRAY" = '0' ] || die "the filtered SQL still contains $STRAY database-switching statement(s) - refusing to import, because it could reach '$SOURCE_DB'"
log "restore  $(grep -c '^CREATE TABLE' "$IMPORT_SQL" || true) CREATE TABLE statements, no database-switching statements left"

log "restore  importing into '$REHEARSAL_DB'"
T_RESTORE_START=$(now_ms)
MYSQL_PWD="$APP_PASS" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" \
    mysql -u"$APP_USER" -D "$REHEARSAL_DB" <"$IMPORT_SQL" \
    || die "the import into '$REHEARSAL_DB' failed"
T_RESTORE=$(since_ms "$T_RESTORE_START")
rm -f "$IMPORT_SQL"

# 5. Counts: restored vs manifest vs live.
log 'count   comparing the restored rows with the manifest and with the live database'
RESTORED_TABLES="$(tables_of "$REHEARSAL_DB")" || die "could not count the restored tables"
RESTORED_COUNTS="$(counts_of "$REHEARSAL_DB")" || die "could not read the restored row counts"

declare -A MANIFEST_ROWS=() RESTORED_ROWS=() LIVE_ROWS=()
while IFS=$'\t' read -r t n; do [ -n "${t:-}" ] && MANIFEST_ROWS["$t"]="$n"; done < <(
    for t in $COUNT_TABLES; do printf '%s\t%s\n' "$t" "$(manifest_rows "$t")"; done
)
while IFS=$'\t' read -r t n; do [ -n "${t:-}" ] && RESTORED_ROWS["$t"]="$n"; done <<<"$RESTORED_COUNTS"
while IFS=$'\t' read -r t n; do [ -n "${t:-}" ] && LIVE_ROWS["$t"]="$n"; done <<<"$LIVE_BEFORE"

COUNT_FAILURES=0
printf 'rehearsal: %-15s %10s %10s %10s  %s\n' 'table' 'restored' 'manifest' 'live now' 'result'
for t in $COUNT_TABLES; do
    r="${RESTORED_ROWS[$t]:-?}"
    m="${MANIFEST_ROWS[$t]:-missing}"
    l="${LIVE_ROWS[$t]:-?}"
    if [ "$m" = 'missing' ]; then
        result='FAIL (no manifest count)'
        COUNT_FAILURES=$((COUNT_FAILURES + 1))
    elif [ "$r" = "$m" ]; then
        result='PASS'
    else
        result='FAIL (restored != manifest)'
        COUNT_FAILURES=$((COUNT_FAILURES + 1))
    fi
    printf 'rehearsal: %-15s %10s %10s %10s  %s\n' "$t" "$r" "$m" "$l" "$result"
done

MANIFEST_TABLES="$(manifest_value tables)"
if [ "$RESTORED_TABLES" = "$MANIFEST_TABLES" ]; then
    printf 'rehearsal: %-15s %10s %10s %10s  %s\n' 'tables' "$RESTORED_TABLES" "$MANIFEST_TABLES" "$LIVE_TABLES_BEFORE" 'PASS'
else
    printf 'rehearsal: %-15s %10s %10s %10s  %s\n' 'tables' "$RESTORED_TABLES" "$MANIFEST_TABLES" "$LIVE_TABLES_BEFORE" 'FAIL'
    COUNT_FAILURES=$((COUNT_FAILURES + 1))
fi

[ "$COUNT_FAILURES" -eq 0 ] || die "$COUNT_FAILURES restored count(s) disagree with the manifest - the restore is not faithful"

# 6. A real login against the restored data. Never proves a login by counting rows: the password
#    column holds bcrypt hashes, so the check is bcrypt.
log 'login   proving an administrator can sign in against the restored data'
REHEARSAL_PASSWORD="$(openssl rand -hex 16)"
T_LOGIN_START=$(now_ms)
if ! DB_DATABASE="$REHEARSAL_DB" BACKUP_REHEARSAL_PASSWORD="$REHEARSAL_PASSWORD" php scripts/verify-restored-login.php; then
    unset REHEARSAL_PASSWORD
    die 'the restored database does not authenticate an administrator - the restore is not usable'
fi
unset REHEARSAL_PASSWORD
T_LOGIN=$(since_ms "$T_LOGIN_START")

# 7. The live database must be exactly as it was. This is the proof that the import stayed in the
#    scratch database, and it is checked rather than assumed.
LIVE_AFTER="$(counts_of "$LIVE_DB")" || die "could not re-read the live row counts"
LIVE_TABLES_AFTER="$(tables_of "$LIVE_DB")" || die "could not re-count the live tables"
if [ "$LIVE_AFTER" != "$LIVE_BEFORE" ] || [ "$LIVE_TABLES_AFTER" != "$LIVE_TABLES_BEFORE" ]; then
    die "the LIVE database '$LIVE_DB' changed during the rehearsal ($LIVE_TABLES_BEFORE -> $LIVE_TABLES_AFTER tables). It is not a client instance, but this is a hard stop: establish what wrote to it before going further."
fi
log "live    '$LIVE_DB' unchanged ($LIVE_TABLES_AFTER tables, all tracked row counts identical)"

# 8. Drop the scratch database and prove it is gone.
if [ "$KEEP" -eq 1 ]; then
    log "keep    leaving '$REHEARSAL_DB' in place (--keep); drop it with: docker exec -e MYSQL_PWD=... $DB_CONTAINER mysql -uroot -e 'DROP DATABASE \`$REHEARSAL_DB\`'"
else
    admin_sql "DROP DATABASE \`$REHEARSAL_DB\`" || die "could not drop the scratch database '$REHEARSAL_DB' - drop it by hand, it holds a copy of client data"
    if db_exists "$REHEARSAL_DB"; then
        die "'$REHEARSAL_DB' still exists after DROP DATABASE"
    fi
    SCRATCH_DB_CREATED=0
    log "drop    '$REHEARSAL_DB' dropped, and confirmed absent from information_schema"
fi

REHEARSAL_FINISHED=1

log "timings verify $T_VERIFY, restore $T_RESTORE, login $T_LOGIN"
log "OK dump=$(basename "$DUMP_FILE") restored_into=$REHEARSAL_DB tables=$RESTORED_TABLES checks=manifest+counts+login live_untouched=yes dropped=$([ "$KEEP" -eq 1 ] && echo no || echo yes)"
