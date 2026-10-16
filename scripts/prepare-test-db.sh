#!/usr/bin/env bash
#
# prepare-test-db.sh - the one provisioning path for the test database.
#
# `make test-db` and the CI workflow both call this script, so the local gate and the CI gate can
# never drift apart: whichever database `phpunit.xml` pins is the database both of them create,
# grant, migrate and seed.
#
# CONTRACT
#   * The database name comes FROM phpunit.xml, never from the command line, so the pin stays the
#     single source of truth. A missing pin is refused loudly instead of guessed.
#   * The connection details are resolved the way the application resolves them: the caller's
#     environment first, then .env as fallback. Laravel's dotenv repository is immutable, so an
#     exported DB_* value wins over .env -- what artisan is handed below is exactly what it reads.
#   * The database is created if absent, the application user is granted access, and the migrations
#     and seeders run against it.
#   * Creation is attempted over PDO first, because PDO behaves identically on a CI runner and on a
#     developer machine and needs no host `mysql` client. The privileged credentials are
#     DB_ROOT_PASSWORD when it is set (the local container's root), otherwise the application user
#     itself (which is root on the CI service container). Only if every PDO attempt fails does it
#     fall back to `docker exec` into the MySQL container. If no route works it exits non-zero,
#     naming exactly what is missing.
#   * It is idempotent: re-running it changes nothing.
#
# Usage: bash scripts/prepare-test-db.sh [--check]
#
# --check resolves and prints the database, host, port, user and the action it would take, then
# exits without writing anything (same style as `scripts/provision-dev.sh --check-env`).
#
# Exit status: 0 when the test database is ready (or --check completed), non-zero when it cannot be
# prepared.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_ROOT"

ENV_FILE="$PROJECT_ROOT/.env"
PHPUNIT_FILE="$PROJECT_ROOT/phpunit.xml"
DB_CONTAINER="${DB_CONTAINER:-${KRAYIN_DB_CONTAINER:-crm-platform-mysql}}"

CHECK_ONLY=0
while [ $# -gt 0 ]; do
    case "$1" in
        --check) CHECK_ONLY=1; shift ;;
        -h|--help) awk 'NR > 1 { if ($0 !~ /^#/) exit; print }' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "prepare-test-db: unknown option '$1' (try --help)" >&2; exit 2 ;;
    esac
done

refuse() { echo "prepare-test-db: refusing - $1" >&2; exit 1; }

# --------------------------------------------------------------- resolution
# `.env` is a plain KEY=VALUE file; strip one layer of surrounding quotes, like the other scripts.
read_env() {
    grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- \
        | sed -e 's/^"//' -e 's/"$//' || true
}

# The caller's environment first, .env as fallback. An empty exported value counts as unset so an
# exported `DB_ROOT_PASSWORD=` cannot shadow the real one in .env.
env_value() {
    local name="$1" value
    value="$(printenv "$name" 2>/dev/null || true)"
    if [ -n "$value" ]; then printf '%s' "$value"; else read_env "$name"; fi
}

# The phpunit.xml pin: the last DB_* value with that name (the file sets each key twice, once in
# <server> and once in <env>, deliberately with the same value).
pin_value() {
    sed -nE "s/.*name=\"$1\"[[:space:]]+value=\"([^\"]+)\".*/\1/p" "$PHPUNIT_FILE" | tail -1
}

[ -f "$PHPUNIT_FILE" ] || refuse "phpunit.xml not found at $PHPUNIT_FILE, so the test database cannot be pinned"
TEST_DB="$(pin_value DB_DATABASE)"
[ -n "$TEST_DB" ] || refuse "phpunit.xml pins no DB_DATABASE; add it there (in both the <env> and <server> blocks) instead of naming the database here"
PIN_HOST="$(pin_value DB_HOST)"
PIN_PORT="$(pin_value DB_PORT)"

DB_CONNECTION="$(env_value DB_CONNECTION)"; DB_CONNECTION="${DB_CONNECTION:-mysql}"
DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"
DB_ROOT_PASSWORD="$(env_value DB_ROOT_PASSWORD)"
LIVE_DB="$(env_value DB_DATABASE)"
LIVE_DB="${LIVE_DB:-krayin}"

[ -n "$DB_HOST" ] || refuse "DB_HOST is not set in the environment or .env, so the MySQL server is unknown"
[ -n "$DB_PORT" ] || refuse "DB_PORT is not set in the environment or .env, so the MySQL port is unknown"
[ -n "$DB_USERNAME" ] || refuse "DB_USERNAME is not set in the environment or .env, so the application user is unknown"
[ "$DB_CONNECTION" = "mysql" ] || refuse "DB_CONNECTION is '$DB_CONNECTION', not mysql; this script only speaks the mysql driver"
[ "$TEST_DB" != "$LIVE_DB" ] || refuse "phpunit.xml pins '$TEST_DB', the same name as the live database; the test database must be a separate one"
# A cached config beats both .env and phpunit.xml: artisan would read the database name from the
# cache while this script read the pin, and the two would disagree.
[ ! -f bootstrap/cache/config.php ] || refuse "bootstrap/cache/config.php exists and would ignore .env and phpunit.xml; run: php artisan config:clear"

# The pinned host/port are what the suite itself connects with; the resolved ones are what artisan
# reads. Report a disagreement rather than let the two gates drift apart silently.
if [ -n "$PIN_HOST" ] && [ "$PIN_HOST" != "$DB_HOST" ]; then
    echo "prepare-test-db: warning: phpunit.xml pins DB_HOST=$PIN_HOST but the environment/.env resolves DB_HOST=$DB_HOST" >&2
fi
if [ -n "$PIN_PORT" ] && [ "$PIN_PORT" != "$DB_PORT" ]; then
    echo "prepare-test-db: warning: phpunit.xml pins DB_PORT=$PIN_PORT but the environment/.env resolves DB_PORT=$DB_PORT" >&2
fi

masked_password() { if [ -n "$1" ]; then printf '<set>'; else printf '<unset>'; fi; }

# ------------------------------------------------------------------- PDO engine
# Prints machine-readable RESULT lines on success and exits 0; exits non-zero when no candidate
# credential can perform the (idempotent) operation. Both credential pairs are tried because the
# privileged user differs by environment: root with DB_ROOT_PASSWORD locally, the application user
# itself on the CI service container (DB_USERNAME=root there).
run_pdo() {
    local mode="$1"
    P_MODE="$mode" \
    P_DB_HOST="$DB_HOST" \
    P_DB_PORT="$DB_PORT" \
    P_DB_DATABASE="$TEST_DB" \
    P_DB_USERNAME="$DB_USERNAME" \
    P_DB_PASSWORD="$DB_PASSWORD" \
    P_DB_ROOT_PASSWORD="$DB_ROOT_PASSWORD" \
    php <<'PHP'
<?php
$mode = getenv('P_MODE') ?: 'ensure';
$host = getenv('P_DB_HOST');
$port = getenv('P_DB_PORT');
$dbName = getenv('P_DB_DATABASE');
$appUser = (string) getenv('P_DB_USERNAME');
$appPass = (string) getenv('P_DB_PASSWORD');
$rootPass = (string) getenv('P_DB_ROOT_PASSWORD');

$candidates = [];
if ($rootPass !== '') {
    $candidates[] = ['root', $rootPass, 'pdo-root'];
}
if ($appUser !== '') {
    $candidates[] = [$appUser, $appPass, 'pdo-app'];
}

$seen = [];
$errors = [];
$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];

$dbExists = function (PDO $pdo) use ($dbName): bool {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
    $statement->execute([$dbName]);
    return ((int) $statement->fetchColumn()) > 0;
};
$tableCount = function (PDO $pdo) use ($dbName): int {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
    $statement->execute([$dbName]);
    return (int) $statement->fetchColumn();
};
$quoteIdentifier = function (string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
};

foreach ($candidates as [$user, $pass, $label]) {
    $key = $user . "\0" . $pass;
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (Throwable $exception) {
        $errors[] = "{$label}: connect failed: " . $exception->getMessage();
        continue;
    }

    try {
        $existedBefore = $dbExists($pdo);
        if ($mode === 'ensure' && !$existedBefore) {
            $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $quoteIdentifier($dbName)
                . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
        if ($mode === 'ensure' && $appUser !== '') {
            $pdo->exec('GRANT ALL PRIVILEGES ON ' . $quoteIdentifier($dbName) . '.* TO '
                . $pdo->quote($appUser) . "@'%'");
            $pdo->exec('FLUSH PRIVILEGES');
        }
        $existedAfter = $dbExists($pdo);
        $tables = $existedAfter ? $tableCount($pdo) : 0;

        echo "ROUTE={$label}\n";
        echo 'EXISTED=' . ($existedBefore ? '1' : '0') . "\n";
        echo 'CREATED=' . (($existedAfter && !$existedBefore) ? '1' : '0') . "\n";
        echo "TABLES={$tables}\n";
        exit(0);
    } catch (Throwable $exception) {
        $errors[] = "{$label}: operation failed: " . $exception->getMessage();
        continue;
    }
}

fwrite(STDERR, implode("\n", $errors) . "\n");
exit(1);
PHP
}

# ---------------------------------------------------------------- docker route
# Fallback only: the MySQL client runs inside the container, so no host `mysql` is required. CI has
# no Docker and never reaches this route.
run_docker() {
    docker inspect -f '{{.State.Running}}' "$DB_CONTAINER" >/dev/null 2>&1 \
        || refuse "no PDO route could create '$TEST_DB', and container '$DB_CONTAINER' is not running - start it with: make setup"
    [ -n "$DB_ROOT_PASSWORD" ] \
        || refuse "no PDO route could create '$TEST_DB', and DB_ROOT_PASSWORD is not set in the environment or .env, so the docker fallback cannot authenticate as root"
    local quoted_db quoted_user
    quoted_db="${TEST_DB//\`/\`\`}"
    quoted_user="${DB_USERNAME//\'/\'\'}"
    docker exec -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_CONTAINER" mysql -uroot \
        -e "CREATE DATABASE IF NOT EXISTS \`$quoted_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$quoted_db\`.* TO '$quoted_user'@'%'; FLUSH PRIVILEGES;"
}

# --------------------------------------------------------------------- --check
if [ "$CHECK_ONLY" -eq 1 ]; then
    echo "check: project_root=$PROJECT_ROOT"
    echo "check: phpunit_pin=$PHPUNIT_FILE"
    echo "check: db_database=$TEST_DB (pinned in phpunit.xml)"
    echo "check: db_connection=$DB_CONNECTION"
    echo "check: db_host=$DB_HOST"
    echo "check: db_port=$DB_PORT"
    echo "check: db_username=$DB_USERNAME"
    echo "check: db_password=$(masked_password "$DB_PASSWORD")"
    echo "check: db_root_password=$(masked_password "$DB_ROOT_PASSWORD")"
    echo "check: container=$DB_CONTAINER"
    if check_output="$(run_pdo check 2>/dev/null)"; then
        existed="$(printf '%s\n' "$check_output" | sed -nE 's/^EXISTED=//p')"
        tables="$(printf '%s\n' "$check_output" | sed -nE 's/^TABLES=//p')"
        route="$(printf '%s\n' "$check_output" | sed -nE 's/^ROUTE=//p')"
        if [ "$existed" = "1" ]; then
            echo "check: exists=yes tables=$tables route=$route"
            echo "check: action=migrate and seed '$TEST_DB' (the database already exists)"
        else
            echo "check: exists=no route=$route"
            echo "check: action=create '$TEST_DB', grant '$DB_USERNAME', migrate and seed"
        fi
    else
        echo "check: exists=unknown route=none"
        echo "check: action=create '$TEST_DB', grant '$DB_USERNAME', migrate and seed (existence could not be verified read-only)"
    fi
    echo "check: read-only - nothing was written"
    exit 0
fi

# ---------------------------------------------------------------------- ensure
ROUTE=""
if ensure_output="$(run_pdo ensure)"; then
    ROUTE="$(printf '%s\n' "$ensure_output" | sed -nE 's/^ROUTE=//p')"
    CREATED="$(printf '%s\n' "$ensure_output" | sed -nE 's/^CREATED=//p')"
else
    echo "prepare-test-db: PDO could not create '$TEST_DB'; trying container '$DB_CONTAINER'" >&2
    run_docker
    ROUTE="docker-exec"
    CREATED="1"
fi

# Hand artisan exactly the connection that was just verified. Laravel's dotenv repository is
# immutable, so an inherited DB_* value would otherwise win inside artisan and migrate a different
# database than the one this script prepared.
export DB_CONNECTION DB_HOST DB_PORT DB_USERNAME DB_PASSWORD
export DB_DATABASE="$TEST_DB"

echo "prepare-test-db: '$TEST_DB' on $DB_HOST:$DB_PORT as '$DB_USERNAME' (route: $ROUTE, created: ${CREATED:-0})"
echo "prepare-test-db: migrating and seeding '$TEST_DB' (only that database)"
php artisan migrate --force
php artisan db:seed --force

TABLES="?"
if final_output="$(run_pdo check 2>/dev/null)"; then
    TABLES="$(printf '%s\n' "$final_output" | sed -nE 's/^TABLES=//p')"
fi
echo "prepare-test-db: '$TEST_DB' ready on $DB_HOST:$DB_PORT ($TABLES tables), '$DB_USERNAME'@'%' granted access"
