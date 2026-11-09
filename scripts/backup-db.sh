#!/usr/bin/env bash
#
# backup-db.sh - a consistent, compressed, manifest-backed dump of the live CRM database, with an
# explicit retention policy enforced by this script.
#
# WHY THIS EXISTS
#   The 2026-11-08 restore of the live database was possible only because a full dump happened to sit
#   in /var/tmp/pi-scratch/<run>/krayin.sql -- a directory a 48-hour reaper deletes. It was three days
#   old, so three days of writes were gone. There was no schedule, no retention policy, no manifest
#   and no rehearsed restore. A backup nobody can verify is a hypothesis, so every dump this script
#   writes carries a manifest of its own sha256, its byte sizes, the database name and the row counts
#   of the tables that matter, and `scripts/restore-rehearsal.sh` proves the whole loop by restoring
#   one into a throwaway database.
#
# CONTRACT
#   * The dump is taken INSIDE the MySQL container, as the application user from .env, with the
#     password handed over through MYSQL_PWD in the container's environment -- never as a -p argument
#     and never printed.
#   * `--single-transaction` gives a consistent InnoDB snapshot with no table locks, so the CRM keeps
#     serving during the dump. `--quick` streams instead of buffering a table in memory, `--routines
#     --triggers --events` keep the objects a plain data dump silently drops, `--databases` makes the
#     file self-contained (it carries its own CREATE DATABASE and USE), and `--set-gtid-purged=OFF`
#     means the file can be imported by a user without SUPER if GTID replication is ever turned on.
#   * The output is gzipped, timestamped in UTC and named `<database>-<YYYYMMDDTHHMMSSZ>.sql.gz`, with
#     a sibling `<same>.manifest`. The manifest is written AFTER the dump is complete and validated,
#     so it always describes the file that is actually on disk.
#   * A dump is validated before it is promoted: gzip must accept it, and it must end with mysqldump's
#     `-- Dump completed` trailer. The trailer is written last, after every object, so its absence
#     means a truncated file even when the exit status was 0 (disk full, container killed mid-stream).
#     A half-written .sql is worse than none at all, so a partial file is deleted, never renamed.
#   * RETENTION IS EXPLICIT AND ENFORCED HERE: the newest dump of each of the last KEEP_DAILY days,
#     plus the newest dump of each of the last KEEP_WEEKLY ISO weeks, plus the newest dump of each of
#     the last KEEP_MONTHLY calendar months. A dump kept by ANY of those three rules is kept --
#     exactly restic's `forget --keep-daily/--keep-weekly/--keep-monthly` semantics, which is the
#     house policy already used by the homelab offsite pull. Everything else this script wrote is
#     deleted together with its manifest.
#   * Only files matching the pattern above are ever pruned. A hand-taken dump (for example the
#     emergency copy `krayin-2026-11-05-1422.sql`) does not match and is never deleted by retention.
#   * The backup directory defaults to $HOME/backups/crm-platform -- durable, outside this checkout and
#     outside /tmp and /var/tmp. If BACKUP_DIR points INSIDE the checkout the script refuses unless
#     `git check-ignore` proves the path is ignored, because a dump in a tracked directory holds live
#     client rows and password hashes and would otherwise be committable.
#
# Usage:
#   bash scripts/backup-db.sh                 # dump, write the manifest, enforce retention
#   bash scripts/backup-db.sh --verify        # verify the newest dump against its manifest
#   bash scripts/backup-db.sh --verify FILE   # verify one specific dump
#   bash scripts/backup-db.sh --list          # show what retention keeps and what it drops
#   bash scripts/backup-db.sh --help
#
# Overridable from the environment:
#   BACKUP_DIR      where dumps land                  [$HOME/backups/crm-platform]
#   KEEP_DAILY      daily dumps kept                  [14]
#   KEEP_WEEKLY     weekly dumps kept                 [8]
#   KEEP_MONTHLY    monthly dumps kept                [6]
#   DB_CONTAINER    the MySQL container               [crm-platform-mysql]
#   DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_HOST, DB_PORT   the caller's environment first, then
#                   .env -- the same precedence the application itself uses, so a second install is
#                   dumped by exporting its own values.
#
# Scheduled not by this script: `scripts/systemd/crm-platform-backup.timer` runs it nightly. The
# installer for that pair is in scripts/README.md; the operator procedure is docs/ops/backup-and-restore.md.
#
# Exit status: 0 on a complete dump (or a passing verify), non-zero on refusal or failure. The last
# line of a successful run is machine-greppable (`backup: OK ...`).
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

ENV_FILE="$PROJECT_ROOT/.env"
DB_CONTAINER="${DB_CONTAINER:-${KRAYIN_DB_CONTAINER:-crm-platform-mysql}}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/crm-platform}"
KEEP_DAILY="${KEEP_DAILY:-14}"
KEEP_WEEKLY="${KEEP_WEEKLY:-8}"
KEEP_MONTHLY="${KEEP_MONTHLY:-6}"

# The only files retention may delete. Any other name in BACKUP_DIR is somebody's hand-taken dump and
# is left alone.
DUMP_RE='^[A-Za-z0-9_.-]+-[0-9]{8}T[0-9]{6}Z\.sql\.gz$'

MODE='backup'
VERIFY_FILE=''

while [ $# -gt 0 ]; do
    case "$1" in
        --verify)
            MODE='verify'
            shift
            if [ $# -gt 0 ] && [ "${1#-}" = "$1" ]; then VERIFY_FILE="$1"; shift; fi
            ;;
        --list) MODE='list'; shift ;;
        -h|--help) awk 'NR > 1 { if ($0 !~ /^#/) exit; print }' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "backup: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
done

log()  { printf 'backup: %s\n' "$*"; }
warn() { printf 'backup: WARN: %s\n' "$*" >&2; }
die()  { printf 'backup: FAIL: %s\n' "$*" >&2; exit 1; }

# Milliseconds, so a sub-second dump is not reported as `0`. Real numbers here are what let a future
# operator know how long a dump of this instance takes; they go into the manifest.
now_ms() { date +%s%3N; }
since_ms() {
    local elapsed=$(( $(now_ms) - $1 ))
    printf '%d.%03ds' $((elapsed / 1000)) $((elapsed % 1000))
}

# --------------------------------------------------------------- resolution
# `.env` is a plain KEY=VALUE file; strip one layer of surrounding quotes, like the other scripts.
read_env() {
    grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- \
        | sed -e 's/^"//' -e 's/"$//' || true
}

# The caller's environment first, .env as fallback. An empty exported value counts as unset, so an
# exported `DB_PASSWORD=` cannot shadow the real one in .env. DB_DATABASE resolved this way is also
# what `--verify` and the rehearsal check a manifest against.
env_value() {
    local name="$1" value
    value="$(printenv "$name" 2>/dev/null || true)"
    if [ -n "$value" ]; then printf '%s' "$value"; else read_env "$name"; fi
}

# The connection is resolved here for every mode, but only `do_backup` insists on it: `--list` and
# `--verify` are deliberately usable on a machine that holds a dump and no credential, which is exactly
# the laptop an operator checks a dump on before restoring it somewhere. `resolve_target()` is where the
# .env file itself is required.
DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"

resolve_target() {
    [ -f "$ENV_FILE" ] || die ".env not found at $ENV_FILE - there is no connection to resolve, and no credential to read"
    [ -n "$DB_NAME" ] || die "DB_DATABASE is empty in the environment and in .env - there is no database to dump"
    [ -n "$DB_USER" ] || die "DB_USERNAME is empty in the environment and in .env"
    [ -n "$DB_PASS" ] || die "DB_PASSWORD is empty in the environment and in .env - refusing to guess a credential"
    case "$DB_NAME" in
        mysql|information_schema|performance_schema|sys)
            die "'$DB_NAME' is a MySQL system database, not the CRM database" ;;
    esac
    docker inspect -f '{{.State.Running}}' "$DB_CONTAINER" >/dev/null 2>&1 \
        || die "container '$DB_CONTAINER' is not running (start it with: make setup)"
}

# ------------------------------------------------------------- backup dir
# The dump holds live client CRM rows and every user's password hash. Outside the checkout by
# default; inside it only when git itself proves the path can never be committed.
prepare_backup_dir() {
    mkdir -p "$BACKUP_DIR" || die "could not create $BACKUP_DIR"
    chmod 700 "$BACKUP_DIR" 2>/dev/null || warn "could not set mode 700 on $BACKUP_DIR (NTFS mounts report 777 by design)"

    local real_dir real_root
    real_dir="$(cd "$BACKUP_DIR" && pwd -P)"
    real_root="$(cd "$PROJECT_ROOT" && pwd -P)"
    case "$real_dir" in
        "$real_root"|"$real_root"/*)
            if git -C "$PROJECT_ROOT" check-ignore -q -- "$real_dir"; then
                log "note: $real_dir is inside the checkout but git ignores it ($(git -C "$PROJECT_ROOT" check-ignore -v -- "$real_dir" | cut -d: -f2-))"
            else
                die "$real_dir is inside the checkout and NOT gitignored - a dump there holds live client rows and password hashes and would be committable. Add it to .gitignore first, or set BACKUP_DIR outside the checkout."
            fi
            ;;
    esac
}

# --------------------------------------------------------------- the dump
# Row counts of the tables that matter, read over one connection. They go into the manifest so a
# restore can be PROVEN equal rather than eyeballed, and they are read immediately before the dump.
# `rows_` keys are namespaced so the manifest stays a flat KEY=VALUE file any tool can read.
COUNT_TABLES='leads persons organizations products quotes quote_items activities emails users'

read_counts() {
    local q='' t
    for t in $COUNT_TABLES; do
        q="${q}${q:+ UNION ALL }SELECT '${t}', COUNT(*) FROM \`${t}\`"
    done
    MYSQL_PWD="$DB_PASS" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" \
        mysql -u"$DB_USER" -N -B -D "$DB_NAME" -e "$q" 2>/dev/null </dev/null
}

read_table_count() {
    MYSQL_PWD="$DB_PASS" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" \
        mysql -u"$DB_USER" -N -B \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME'" 2>/dev/null </dev/null
}

read_server_version() {
    MYSQL_PWD="$DB_PASS" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" \
        mysql -u"$DB_USER" -N -B -e 'SELECT VERSION()' 2>/dev/null </dev/null
}

do_backup() {
    resolve_target
    prepare_backup_dir

    local stamp out tmp manifest manifest_tmp
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    out="$BACKUP_DIR/$DB_NAME-$stamp.sql.gz"
    tmp="$out.part"
    manifest="$BACKUP_DIR/$DB_NAME-$stamp.manifest"
    manifest_tmp="$manifest.part"

    # Two dumps in the same second (a re-run in a loop) must not silently overwrite the first.
    [ ! -e "$out" ] || die "$out already exists - refusing to overwrite an existing dump"

    log "dumping '$DB_NAME' from $DB_CONTAINER as $DB_USER -> $out"

    local tables_before counts_before counts_after counts_stable='yes'
    tables_before="$(read_table_count)" || die "could not count tables in '$DB_NAME' - is the app user's grant intact?"
    if [ -z "$tables_before" ] || [ "$tables_before" = '0' ]; then
        die "'$DB_NAME' reports ${tables_before:-no} tables - that is not a CRM database to dump"
    fi
    counts_before="$(read_counts)" || die "could not read row counts from '$DB_NAME' - refusing to write a manifest that could not describe the dump"

    local t_dump_start t_dump
    t_dump_start=$(now_ms)

    # pipefail makes this line fail if mysqldump OR gzip fails mid-stream; the trailer check below
    # catches the case where both exited 0 but the stream was cut short.
    if ! MYSQL_PWD="$DB_PASS" docker exec -e MYSQL_PWD -i "$DB_CONTAINER" \
        mysqldump -u"$DB_USER" --single-transaction --quick --routines --triggers --events \
            --no-tablespaces --set-gtid-purged=OFF --databases "$DB_NAME" \
        2>"$BACKUP_DIR/$DB_NAME-$stamp.stderr" | gzip -9 >"$tmp"; then
        rm -f "$tmp"
        die "mysqldump failed inside $DB_CONTAINER for database '$DB_NAME' (details: $BACKUP_DIR/$DB_NAME-$stamp.stderr)"
    fi
    if ! gzip -t "$tmp" 2>/dev/null; then
        rm -f "$tmp"
        die "the compressed dump does not pass gzip -t - the stream was corrupt, nothing was written"
    fi
    # mysqldump writes this trailer LAST, after every object. `grep -c` rather than `grep -q` on
    # purpose: -q exits on the first match and the upstream tail then takes SIGPIPE, which pipefail
    # turns into a failure of a pipeline that actually found the trailer.
    local trailer
    trailer="$(gzip -dc "$tmp" | tail -n 5 | grep -c '^-- Dump completed' || true)"
    if [ "$trailer" -lt 1 ]; then
        rm -f "$tmp"
        die "the dump carries no '-- Dump completed' trailer - it was truncated, nothing was written"
    fi

    counts_after="$(read_counts || true)"
    t_dump=$(since_ms "$t_dump_start")
    if [ "$counts_before" != "$counts_after" ]; then
        counts_stable='no'
        warn "the row counts changed while the dump was running (the CRM was writing). The manifest records the values read immediately BEFORE the dump, and marks counts_stable=no."
    fi

    local bytes uncompressed_bytes sha create_table_statements app_key_sha commit dirty
    bytes="$(stat -c %s "$tmp")"
    [ "$bytes" -gt 0 ] || { rm -f "$tmp"; die "the compressed dump is empty"; }
    uncompressed_bytes="$(gzip -dc "$tmp" | wc -c)"
    sha="$(sha256sum "$tmp" | cut -d' ' -f1)"
    create_table_statements="$(gzip -dc "$tmp" | grep -c '^CREATE TABLE' || true)"
    # The fingerprint, never the key: after a restore an operator can confirm they hold the matching
    # APP_KEY (which is what decrypts APP_KEY-encrypted columns) without the key itself being written
    # anywhere near the dump.
    app_key_sha="$(printf '%s' "$(read_env APP_KEY)" | sha256sum | cut -d' ' -f1)"
    commit="$(git -C "$PROJECT_ROOT" rev-parse HEAD 2>/dev/null || echo unknown)"
    if [ -n "$(git -C "$PROJECT_ROOT" status --porcelain 2>/dev/null | head -1)" ]; then dirty='yes'; else dirty='no'; fi

    chmod 600 "$tmp" 2>/dev/null || true

    {
        printf 'manifest_version=1\n'
        printf 'created_at=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        printf 'database=%s\n' "$DB_NAME"
        printf 'container=%s\n' "$DB_CONTAINER"
        printf 'server_version=%s\n' "$(read_server_version || echo unknown)"
        printf 'dump_file=%s\n' "$(basename "$out")"
        printf 'dump_sha256=%s\n' "$sha"
        printf 'dump_bytes=%s\n' "$bytes"
        printf 'uncompressed_bytes=%s\n' "$uncompressed_bytes"
        printf 'dump_duration=%s\n' "$t_dump"
        printf 'compression=gzip -9\n'
        printf 'tables=%s\n' "$tables_before"
        printf 'create_table_statements=%s\n' "$create_table_statements"
        printf 'rows_measured_at=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        printf 'counts_stable=%s\n' "$counts_stable"
        printf '%s\n' "$counts_before" | while IFS=$'\t' read -r t n; do printf 'rows_%s=%s\n' "$t" "$n"; done
        printf 'mysqldump_flags=%s\n' '--single-transaction --quick --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF --databases'
        printf 'app_key_sha256=%s\n' "$app_key_sha"
        printf 'checkout_commit=%s\n' "$commit"
        printf 'checkout_dirty=%s\n' "$dirty"
        # The credential is deliberately a literal $DB_PASSWORD placeholder: the manifest records the
        # SHAPE of the restore command, never a password.
        # shellcheck disable=SC2016
        printf 'restore_command=%s\n' "gzip -dc <this file> | docker exec -i -e MYSQL_PWD=\"\$DB_PASSWORD\" $DB_CONTAINER mysql -u$DB_USER"
        printf 'verify_command=bash scripts/backup-db.sh --verify %s\n' "$(basename "$out")"
        printf 'rehearse_command=bash scripts/restore-rehearsal.sh --dump %s\n' "$(basename "$out")"
    } >"$manifest_tmp"

    mv -f "$tmp" "$out" || die "could not move the validated dump into place"
    mv -f "$manifest_tmp" "$manifest" || die "could not write $manifest"
    chmod 600 "$manifest" 2>/dev/null || true
    # mysqldump's stderr is kept only when it said something (a truncated run died above with the
    # file still in place for the operator to read).
    if [ -f "$BACKUP_DIR/$DB_NAME-$stamp.stderr" ] && [ ! -s "$BACKUP_DIR/$DB_NAME-$stamp.stderr" ]; then
        rm -f "$BACKUP_DIR/$DB_NAME-$stamp.stderr"
    elif [ -f "$BACKUP_DIR/$DB_NAME-$stamp.stderr" ]; then
        warn "mysqldump wrote to stderr (kept at $BACKUP_DIR/$DB_NAME-$stamp.stderr): $(tr '\n' ' ' <"$BACKUP_DIR/$DB_NAME-$stamp.stderr" | cut -c1-200)"
    fi

    log "wrote $out (${bytes} bytes compressed, ${uncompressed_bytes} uncompressed, sha256 ${sha:0:16}..., database '$DB_NAME', taken in $t_dump)"
    log "wrote $manifest ($tables_before tables; counts_stable=$counts_stable)"

    prune_retention

    log "OK $out ${bytes} bytes sha256=$sha database=$DB_NAME tables=$tables_before counts_stable=$counts_stable"
}

# ------------------------------------------------------------- retention
# Newest first, by the timestamp IN THE FILENAME (stable and independent of mtime, which any copy
# rewrites). Only files this script wrote are considered.
load_dumps() {
    local f
    DUMPS=()
    for f in "$BACKUP_DIR"/*.sql.gz; do
        [ -e "$f" ] || continue
        f="$(basename "$f")"
        [[ "$f" =~ $DUMP_RE ]] || continue
        DUMPS+=("$f")
    done
    # Reverse lexicographic on the UTC timestamp in the name == newest first.
    if [ "${#DUMPS[@]}" -gt 1 ]; then
        mapfile -t DUMPS < <(printf '%s\n' "${DUMPS[@]}" | sort -r)
    fi
}

stamp_epoch() {
    local s="$1"
    date -u -d "${s:0:4}-${s:4:2}-${s:6:2}T${s:9:2}:${s:11:2}:${s:13:2}Z" +%s
}

# Echo the files one policy keeps: the newest file of each of the last <limit> distinct <kind>
# buckets, walking newest-first. A bucket is a UTC day, an ISO week or a calendar month. It reads
# the global DUMPS array rather than stdin, so three policies can run over one listing.
select_for_policy() {
    local kind="$1" limit="$2" f stamp key epoch count=0
    local -A seen=()
    for f in "${DUMPS[@]}"; do
        stamp="${f##*-}"
        stamp="${stamp%.sql.gz}"
        case "$kind" in
            day)   key="${stamp:0:8}" ;;
            week)  epoch="$(stamp_epoch "$stamp")"; key="$(date -u -d "@$epoch" +%G-W%V)" ;;
            month) key="${stamp:0:6}" ;;
        esac
        [ -n "${seen[$key]:-}" ] && continue
        seen[$key]=1
        if [ "$count" -lt "$limit" ]; then
            printf '%s\n' "$f"
            count=$((count + 1))
        fi
    done
}

# The union of the three policies. A dump kept by any of them is kept (restic's semantics); what is
# left over is a file no policy has a reason to hold.
compute_kept() {
    load_dumps
    {
        select_for_policy day "$KEEP_DAILY"
        select_for_policy week "$KEEP_WEEKLY"
        select_for_policy month "$KEEP_MONTHLY"
    } | sort -u
}

prune_retention() {
    local kept f removed=0 kept_count=0
    local -A KEEP=()
    load_dumps
    [ "${#DUMPS[@]}" -gt 0 ] || { log "retention: nothing to prune"; return 0; }
    kept="$(compute_kept)"
    while IFS= read -r f; do
        [ -n "$f" ] && KEEP["$f"]=1
    done <<<"$kept"

    for f in "${DUMPS[@]}"; do
        if [ -n "${KEEP[$f]:-}" ]; then
            kept_count=$((kept_count + 1))
            continue
        fi
        rm -f "$BACKUP_DIR/$f" "$BACKUP_DIR/${f%.sql.gz}.manifest"
        log "retention: pruned $f"
        removed=$((removed + 1))
    done
    log "retention: kept $kept_count/${#DUMPS[@]} dumps (daily $KEEP_DAILY, weekly $KEEP_WEEKLY, monthly $KEEP_MONTHLY), pruned $removed"
}

cmd_list() {
    [ -d "$BACKUP_DIR" ] || { log "no backup directory at $BACKUP_DIR yet"; return 0; }
    local kept f state
    local -A KEEP=()
    load_dumps
    if [ "${#DUMPS[@]}" -eq 0 ]; then
        log "no dumps this script wrote in $BACKUP_DIR"
    else
        kept="$(compute_kept)"
        while IFS= read -r f; do
            [ -n "$f" ] && KEEP["$f"]=1
        done <<<"$kept"
        printf '%-42s %12s  %s\n' 'DUMP' 'BYTES' 'RETENTION'
        for f in "${DUMPS[@]}"; do
            if [ -n "${KEEP[$f]:-}" ]; then state='keep'; else state='prune'; fi
            printf '%-42s %12s  %s\n' "$f" "$(stat -c %s "$BACKUP_DIR/$f" 2>/dev/null || echo '?')" "$state"
        done
    fi
    # Anything else in the directory is not ours to delete, and saying so is the point: the emergency
    # copy taken by hand on 2026-11-05 lives here too.
    local other
    other="$(find "$BACKUP_DIR" -maxdepth 1 -type f ! -name '*.sql.gz' ! -name '*.manifest' ! -name '*.stderr' -printf '%f\n' 2>/dev/null | sort)"
    if [ -n "$other" ]; then
        printf '\nnot managed by retention (never deleted by this script):\n%s\n' "$other"
    fi
}

# ---------------------------------------------------------------- verify
# Integrity only: the file on disk must still be the file the manifest describes. Row counts in the
# manifest are proven by scripts/restore-rehearsal.sh (restore and re-count) or by re-importing.
do_verify() {
    local file="$1"
    [ -f "$file" ] || die "$file does not exist"
    local base manifest
    base="${file%.sql.gz}"
    manifest="$base.manifest"
    [ -f "$manifest" ] || die "$manifest is missing - a dump with no manifest cannot be verified, only restored hopefully"

    local m_sha m_bytes m_db m_file m_tables m_cts
    m_sha="$(sed -nE 's/^dump_sha256=(.*)$/\1/p' "$manifest" | tail -1)"
    m_bytes="$(sed -nE 's/^dump_bytes=(.*)$/\1/p' "$manifest" | tail -1)"
    m_db="$(sed -nE 's/^database=(.*)$/\1/p' "$manifest" | tail -1)"
    m_file="$(sed -nE 's/^dump_file=(.*)$/\1/p' "$manifest" | tail -1)"
    m_tables="$(sed -nE 's/^tables=(.*)$/\1/p' "$manifest" | tail -1)"
    m_cts="$(sed -nE 's/^create_table_statements=(.*)$/\1/p' "$manifest" | tail -1)"

    local failures=0
    check() { # <label> <ok: 0/1> <detail>
        if [ "$2" -eq 0 ]; then printf 'verify: PASS  %-26s %s\n' "$1" "$3"; else printf 'verify: FAIL  %-26s %s\n' "$1" "$3" >&2; failures=$((failures + 1)); fi
    }
    skip() { printf 'verify: SKIP  %-26s %s\n' "$1" "$2"; }

    local sha bytes ok
    sha="$(sha256sum "$file" | cut -d' ' -f1)"
    if [ "$sha" = "$m_sha" ]; then ok=0; else ok=1; fi
    check 'sha256' "$ok" "on disk ${sha:0:16}... manifest ${m_sha:0:16}..."

    bytes="$(stat -c %s "$file")"
    if [ "$bytes" = "$m_bytes" ]; then ok=0; else ok=1; fi
    check 'bytes' "$ok" "on disk $bytes manifest $m_bytes"

    if [ "$(basename "$file")" = "$m_file" ]; then ok=0; else ok=1; fi
    check 'manifest names this file' "$ok" "manifest dump_file=$m_file"

    if [ -n "$m_db" ]; then ok=0; else ok=1; fi
    check 'manifest names a database' "$ok" "database=$m_db${DB_NAME:+ (this checkout resolves '$DB_NAME')}"

    if gzip -t "$file" 2>/dev/null; then ok=0; else ok=1; fi
    check 'gzip integrity' "$ok" 'gzip -t'

    # Decompression-based checks are meaningless on a stream gzip already rejected: `gzip -dc` still
    # emits whatever it managed to read, so a truncated file would otherwise 'pass' them.
    if [ "$ok" -ne 0 ]; then
        skip 'dump complete' 'not checked: the gzip stream is corrupt'
        skip 'table statements' 'not checked: the gzip stream is corrupt'
    else
        if [ "$(gzip -dc "$file" | tail -n 5 | grep -c '^-- Dump completed' || true)" -ge 1 ]; then ok=0; else ok=1; fi
        check 'dump complete' "$ok" "'-- Dump completed' trailer present"

        local cts
        cts="$(gzip -dc "$file" | grep -c '^CREATE TABLE' || true)"
        if [ "$cts" = "$m_cts" ]; then ok=0; else ok=1; fi
        check 'table statements' "$ok" "CREATE TABLE $cts, manifest $m_cts, manifest counted $m_tables tables"
    fi

    if [ "$failures" -ne 0 ]; then
        die "$failures check(s) failed for $(basename "$file") - do not restore this file"
    fi
    printf 'verify: OK %s sha256=%s bytes=%s database=%s tables=%s\n' "$(basename "$file")" "$sha" "$bytes" "$m_db" "$m_tables"
}

newest_dump() {
    load_dumps
    [ "${#DUMPS[@]}" -gt 0 ] || die "no dump found in $BACKUP_DIR that this script wrote - run: bash scripts/backup-db.sh"
    printf '%s/%s\n' "$BACKUP_DIR" "${DUMPS[0]}"
}

case "$MODE" in
    backup) do_backup ;;
    list)   cmd_list ;;
    verify)
        if [ -n "$VERIFY_FILE" ]; then
            case "$VERIFY_FILE" in
                /*) do_verify "$VERIFY_FILE" ;;
                *)  do_verify "$BACKUP_DIR/$VERIFY_FILE" ;;
            esac
        else
            do_verify "$(newest_dump)"
        fi
        ;;
esac
