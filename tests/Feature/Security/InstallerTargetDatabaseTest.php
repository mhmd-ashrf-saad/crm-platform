<?php

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Webkul\Installer\Console\Commands\Installer;

/**
 * The installer used to take its database from the `.env` **file** rather than from the
 * configuration the application had already resolved, so an exported `DB_DATABASE` was overwritten
 * one line before `migrate:fresh` ran. On 2026-11-08 that destroyed the live `krayin` database and
 * rotated `APP_KEY`; see .agents/context/errors/installer-ignores-env-override.md.
 *
 * Every database these tests write to is a throwaway created and dropped by the test itself. The
 * migration test asserts that the target it resolved is the throwaway *before* it calls
 * `migrate:fresh`, and that assertion is what stops it: `expect()` throws, so a build in which the
 * installer would retarget the connection never reaches the destructive call, and the live `krayin`
 * database and `krayin_test` are never migrated by this file.
 */
beforeEach(function () {
    $this->originalDatabase = config('database');
    $this->originalApp = config('app');
    $this->originalEnv = app()['env'];

    /**
     * The `.env` **file** value, read the way the installer used to read it — the thing a fixed
     * installer must stop consulting.
     */
    $this->dotEnvValue = function (string $key): ?string {
        $line = collect(file(base_path('.env'), FILE_IGNORE_NEW_LINES))
            ->first(fn (string $row) => str_starts_with($row, $key.'='));

        return $line === null ? null : trim(substr($line, strlen($key) + 1), "\"'");
    };

    /**
     * A console command with its input and output bound, so the wizard-facing methods can run
     * outside `handle()`. Nothing here executes `handle()`: that rotates `APP_KEY`, seeds and runs
     * `optimize:clear`, none of which a test of the target database should do.
     */
    $this->installer = function (array $options = []): Installer {
        $command = app(Installer::class);

        $input = new ArrayInput($options, $command->getDefinition());

        $command->setInput($input);
        $command->setOutput(new OutputStyle($input, new BufferedOutput));

        return $command;
    };

    /**
     * `loadEnvConfigAtRuntime()` is protected on purpose — nobody outside the command should call
     * it — but it is the method that chose the wrong database, so it is the one under test.
     */
    $this->loadEnvConfig = function (Installer $command): void {
        (new ReflectionMethod($command, 'loadEnvConfigAtRuntime'))->invoke($command);
    };

    /**
     * A connection to the server itself, with no database selected, for creating and inspecting the
     * throwaway databases. Root first, then the application user, the same order
     * scripts/prepare-test-db.sh tries.
     */
    $this->serverConnection = function (): PDO {
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        $candidates = [];

        if ($rootPassword = env('DB_ROOT_PASSWORD')) {
            $candidates[] = ['root', $rootPassword];
        }

        $candidates[] = [config('database.connections.mysql.username'), config('database.connections.mysql.password')];

        $errors = [];

        foreach ($candidates as [$user, $password]) {
            try {
                return new PDO(
                    "mysql:host={$host};port={$port};charset=utf8mb4",
                    (string) $user,
                    (string) $password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        throw new RuntimeException('No credentials reached the MySQL server: '.implode(' / ', $errors));
    };

    $this->databaseExists = function (string $name): bool {
        $statement = ($this->serverConnection)()->prepare(
            'SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?'
        );

        $statement->execute([$name]);

        return ((int) $statement->fetchColumn()) > 0;
    };

    $this->tableCount = function (string $name): int {
        $statement = ($this->serverConnection)()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?'
        );

        $statement->execute([$name]);

        return (int) $statement->fetchColumn();
    };

    $this->createDatabase = function (string $name): void {
        $connection = ($this->serverConnection)();

        $connection->exec("DROP DATABASE IF EXISTS `{$name}`");
        $connection->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $user = config('database.connections.mysql.username');

        if ($user) {
            $connection->exec("GRANT ALL PRIVILEGES ON `{$name}`.* TO ".$connection->quote($user)."@'%'");
            $connection->exec('FLUSH PRIVILEGES');
        }
    };

    $this->dropDatabase = function (string $name): void {
        ($this->serverConnection)()->exec("DROP DATABASE IF EXISTS `{$name}`");
    };

    /**
     * Point the running application at one of the throwaway databases, which is what an exported
     * `DB_DATABASE` (or a `--target-database`) leaves behind once the config has been resolved.
     */
    $this->useDatabase = function (string $name): void {
        config(['database.connections.mysql.database' => $name]);

        DB::purge('mysql');
    };
});

afterEach(function () {
    foreach (['crm_7ov_install_target', 'crm_7ov_canary', 'crm_7ov_guarded_target', 'crm_7ov_absent_target'] as $name) {
        try {
            ($this->dropDatabase)($name);
        } catch (Throwable $e) {
            // The database was never created, or the server is gone; nothing to clean up.
        }
    }

    /**
     * `loadEnvConfigAtRuntime()` rewrites `app.*` and the connection, and one of these tests asserts
     * that a *broken* build does exactly that. Restoring both keeps the rest of the suite from
     * inheriting a connection this file left pointing somewhere unexpected.
     */
    config(['database' => $this->originalDatabase, 'app' => $this->originalApp]);

    app()['env'] = $this->originalEnv;

    DB::purge('mysql');
});

it('takes the database target from the resolved configuration instead of the .env file', function () {
    $envFileDatabase = ($this->dotEnvValue)('DB_DATABASE');

    $resolved = 'crm_7ov_install_target';

    /**
     * A tautology would prove nothing: the two names must differ for this test to have a subject.
     */
    expect($envFileDatabase)->not->toBe($resolved);

    ($this->useDatabase)($resolved);

    ($this->loadEnvConfig)(($this->installer)());

    expect(config('database.connections.mysql.database'))->toBe($resolved)
        ->and(config('database.connections.mysql.database'))->not->toBe($envFileDatabase);
});

it('installs into the database named by --target-database', function () {
    $envFileDatabase = ($this->dotEnvValue)('DB_DATABASE');

    $requested = 'crm_7ov_absent_target';

    ($this->loadEnvConfig)(($this->installer)(['--target-database' => $requested]));

    expect(config('database.connections.mysql.database'))->toBe($requested)
        ->and(config('database.connections.mysql.database'))->not->toBe($envFileDatabase);
});

it('migrates the requested database and leaves every other database untouched', function () {
    $target = 'crm_7ov_install_target';

    $canary = 'crm_7ov_canary';

    ($this->createDatabase)($target);

    ($this->createDatabase)($canary);

    /**
     * A canary database with a row in it: if the migration reached the wrong database, or reached
     * every database, this row is gone.
     */
    $canaryConnection = ($this->serverConnection)();

    $canaryConnection->exec("CREATE TABLE `{$canary}`.`canary` (id INT PRIMARY KEY, note VARCHAR(32))");
    $canaryConnection->exec("INSERT INTO `{$canary}`.`canary` VALUES (1, 'untouched')");

    /**
     * The two databases this machine must not lose. Their table counts are compared afterwards.
     */
    $liveTables = ($this->tableCount)(($this->dotEnvValue)('DB_DATABASE'));
    $testTables = ($this->tableCount)(config('database.connections.mysql.database'));

    expect(($this->databaseExists)($target))->toBeTrue()
        ->and(($this->tableCount)($target))->toBe(0);

    ($this->useDatabase)($target);

    $installer = ($this->installer)();

    ($this->loadEnvConfig)($installer);

    /**
     * Both assertions are evaluated before `migrate:fresh`, and either one throws on a build that
     * would retarget the connection — so the destructive call below cannot run against the wrong
     * database, whatever the code under test does.
     */
    expect(config('database.connections.mysql.database'))->toBe($target)
        ->and($installer->destructiveTargetProblem())->toBeNull();

    expect(Artisan::call('migrate:fresh'))->toBe(0);

    expect(($this->tableCount)($target))->toBeGreaterThan(0);

    $tables = ($this->serverConnection)()
        ->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = ?');
    $tables->execute([$target]);

    $migrated = $tables->fetchAll(PDO::FETCH_COLUMN);

    expect($migrated)->toContain('users')
        ->and($migrated)->toContain('leads')
        ->and($migrated)->toContain('migrations');

    /**
     * The canary the migration had no business touching.
     */
    expect($canaryConnection->query("SELECT note FROM `{$canary}`.`canary`")->fetchColumn())->toBe('untouched');

    /**
     * And the two databases this machine cannot afford to lose, unchanged.
     */
    expect(($this->tableCount)(($this->dotEnvValue)('DB_DATABASE')))->toBe($liveTables)
        ->and(($this->tableCount)('krayin_test'))->toBe($testTables);
});

it('refuses to wipe a database that already holds an installed application', function () {
    $guarded = 'crm_7ov_guarded_target';

    ($this->createDatabase)($guarded);

    $connection = ($this->serverConnection)();

    $connection->exec("CREATE TABLE `{$guarded}`.`users` (id INT PRIMARY KEY)");
    $connection->exec("INSERT INTO `{$guarded}`.`users` VALUES (1)");

    ($this->useDatabase)($guarded);

    $problem = ($this->installer)()->destructiveTargetProblem();

    expect($problem)->toBeString()
        ->and($problem)->toContain($guarded)
        ->and($problem)->toContain('--discard-existing-data');

    /**
     * The escape hatch the operator has to name explicitly.
     */
    expect(($this->installer)()->destructiveTargetProblem(discardExistingData: true))->toBeNull();
});

it('refuses when the target database cannot be reached', function () {
    ($this->useDatabase)('crm_7ov_absent_target');

    $problem = ($this->installer)()->destructiveTargetProblem();

    expect($problem)->toBeString()
        ->and($problem)->toContain('crm_7ov_absent_target')
        ->and($problem)->toContain('cannot be reached');
});
