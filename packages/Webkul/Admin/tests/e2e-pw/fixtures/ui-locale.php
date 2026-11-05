<?php

/**
 * Read or write the admin interface locale — the row the admin panel actually obeys.
 *
 * `Webkul\Admin\Http\Middleware\Locale` resolves the interface language on every admin request
 * from `core->getConfigData('general.general.locale_settings.locale')`, which is a row in
 * `core_config`, and only falls back to `app()->getLocale()` (`APP_LOCALE`) when that row is
 * missing. On an installed instance the row is always present, so pinning `APP_LOCALE` on the
 * server's environment does nothing at all: the row wins. That is why this script exists and why
 * the harness cannot pin the locale through a mere environment variable.
 *
 * It writes through the application's own repository, from the application's own bootstrap — the
 * same row the configuration screen in `admin/configuration` writes — rather than a hardcoded
 * connection string in the test harness.
 *
 * WHICH DATABASE — the part that is not negotiable
 * ------------------------------------------------
 * The bootstrap reads `.env`, so this script's default database is the *live* one. That was the
 * defect: a Playwright run aimed at a scratch instance (`APP_URL=http://127.0.0.1:8011`) still had
 * its locale pin resolve `.env`'s database, and so wrote the live instance's `core_config` row while
 * every assertion ran against the scratch instance. It restored itself only because teardown ran.
 *
 * Writing is therefore refused unless the caller states the database *and* proves it may have it:
 *
 *   - `--database=<name>` is required for `set` and `unset`. There is no fallback to `.env`, because
 *     a fallback is what makes the live database the default answer.
 *   - The name is applied to `DB_DATABASE` in `$_ENV`, `$_SERVER` and `getenv()` before the
 *     application boots, so Laravel's own connection resolves to it.
 *   - After booting, the connection's database name is read back and compared with the requested
 *     one. A mismatch — a cached `bootstrap/cache/config.php` beating the environment, for instance
 *     — aborts before anything is written.
 *   - A request naming the database `.env` configures is refused unless `--allow-env-database` is
 *     passed. Only the harness's `pinDatabase()` passes it, and only when the suite is driving the
 *     instance `.env` itself describes; a scratch run therefore cannot reach the live row even if it
 *     named it by mistake.
 *
 * Usage:
 *
 *     php ui-locale.php read                                          # .env's database
 *     php ui-locale.php read  --database=krayin_e2e_scratch           # a named database
 *     php ui-locale.php set en --database=krayin_e2e_scratch           # {"previous":"ar","current":"en",...}
 *     php ui-locale.php unset  --database=krayin_e2e_scratch           # removes the row again
 *     php ui-locale.php set en --database=krayin --allow-env-database   # the instance .env describes
 *
 * `set` is an update of the existing row when there is one, never an insert: `core_config` has no
 * unique index on `code`, and a second row would leave `findOneWhere()` free to answer either one.
 */
const CONFIG_CODE = 'general.general.locale_settings.locale';
$root = __DIR__;

while ($root !== dirname($root) && ! is_file($root.'/bootstrap/app.php')) {
    $root = dirname($root);
}

if (! is_file($root.'/bootstrap/app.php')) {
    fwrite(STDERR, 'Could not find the application root (a directory owning bootstrap/app.php) above '.__DIR__.".\n");

    exit(1);
}

require $root.'/vendor/autoload.php';

/**
 * Options come before the positional arguments in practice, but nothing enforces that, so the whole
 * argument list is walked and the first two non-option words are taken as command and locale.
 */
$command = null;
$locale = null;
$database = null;
$allowEnvDatabase = false;

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--database=')) {
        $database = substr($argument, strlen('--database='));

        continue;
    }

    if ($argument === '--allow-env-database') {
        $allowEnvDatabase = true;

        continue;
    }

    if (str_starts_with($argument, '--')) {
        fwrite(STDERR, "unknown option {$argument}\nusage: php ui-locale.php [--database=<name>] [--allow-env-database] read|set <locale>|unset\n");

        exit(2);
    }

    if ($command === null) {
        $command = $argument;
    } elseif ($locale === null) {
        $locale = $argument;
    } else {
        fwrite(STDERR, "unexpected argument {$argument}\n");

        exit(2);
    }
}

$command ??= 'read';

$usage = "usage: php ui-locale.php [--database=<name>] [--allow-env-database] read|set <locale>|unset\n";

if (! in_array($command, ['read', 'set', 'unset'], true)) {
    fwrite(STDERR, $usage);

    exit(2);
}

if ($database !== null && ! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "--database=\"{$database}\" is not a database name this helper will use.\n");

    exit(2);
}

$isWrite = $command !== 'read';

/** Writes must name their target. Reads may omit it and fall back to what `.env` configures. */
if ($isWrite && $database === null) {
    fwrite(STDERR, "refusing to write without --database=<name>: the bootstrap would otherwise resolve .env's database, which is the live one.\n{$usage}");

    exit(2);
}

/**
 * The database `.env` configures — the one this checkout's own instance reads.
 *
 * Read with one regex rather than by handing the file to dotenv: it is Laravel's `.env`, not an INI
 * file, and only a single key is needed. Its value is never used to connect — it is compared against
 * the requested database — so a value this cannot parse only means the "that is the live database"
 * refusal below does not apply, while the caller's own rule still decides whether a write happens.
 */
$envDatabase = (function () use ($root) {
    $path = $root.'/.env';

    if (! is_file($path)) {
        return null;
    }

    if (! preg_match('/^\s*(?:export\s+)?DB_DATABASE\s*=\s*(.*)$/m', (string) file_get_contents($path), $matches)) {
        return null;
    }

    $value = trim(trim($matches[1]), "\"'");

    return $value === '' ? null : $value;
})();

if ($isWrite && $database !== null && $envDatabase !== null && $database === $envDatabase && ! $allowEnvDatabase) {
    fwrite(STDERR, "refusing to write the locale of database \"{$database}\": that is the database .env configures, and this helper is being asked without --allow-env-database.\n");

    exit(2);
}

/**
 * Point the bootstrap at the named database. All three carriers are set because Laravel's `env()`
 * repository reads `$_SERVER`/`$_ENV` while library code and this script itself may ask `getenv()`.
 */
if ($database !== null) {
    putenv("DB_DATABASE={$database}");
    $_ENV['DB_DATABASE'] = $database;
    $_SERVER['DB_DATABASE'] = $database;
}

$app = require $root.'/bootstrap/app.php';

/**
 * Resolved by name, not by class literal: this file is a standalone script that only has the
 * autoloader after the `require` above, and naming the classes as strings keeps that ordering
 * obvious to a reader and to any static analyser pointed at the harness.
 */
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

/**
 * Read back which database the application actually opened. This is the check that makes the
 * `--database` argument binding instead of decorative: an environment that lost to a cached config
 * would otherwise write the wrong database while reporting success.
 */
$connected = $app->make('db')->connection()->getDatabaseName();

if ($database !== null && $connected !== $database) {
    fwrite(STDERR, "refusing to write: asked for database \"{$database}\" but the application connected to \"{$connected}\". Clear the cached config (`php artisan config:clear`) and try again.\n");

    exit(1);
}

$repository = $app->make('Webkul\Core\Repositories\CoreConfigRepository');

$row = $repository->findOneWhere(['code' => CONFIG_CODE]);

$previous = $row?->value;

if ($command === 'set') {
    if ($locale === null || $locale === '') {
        fwrite(STDERR, "usage: php ui-locale.php set <locale> --database=<name>\n");

        exit(2);
    }

    if ($row) {
        $repository->update(['value' => $locale], $row->id);
    } else {
        $repository->create(['code' => CONFIG_CODE, 'value' => $locale]);
    }
} elseif ($command === 'unset') {
    if ($row) {
        $repository->delete($row->id);
    }
}

echo json_encode([
    'previous' => $previous,
    'current' => $repository->findOneWhere(['code' => CONFIG_CODE])?->value,
    'database' => $connected,
]), PHP_EOL;
