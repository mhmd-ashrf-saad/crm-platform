<?php

namespace Webkul\Installer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Throwable;
use Webkul\Core\Providers\CoreServiceProvider;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as KrayinDatabaseSeeder;
use Webkul\Installer\Events\ComposerEvents;
use Webkul\Installer\Helpers\DatabaseManager;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class Installer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'krayin-crm:install
        { --skip-env-check : Skip env check. }
        { --skip-admin-creation : Skip admin creation. }
        { --target-database= : Install into this database, instead of the one the resolved configuration names. }
        { --discard-existing-data : Allow wiping a database that already holds an installed application. }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'krayin installer.';

    /**
     * Values this run's interactive wizard wrote to `.env`, by key.
     *
     * The console application resolved `config()` from the environment (and `.env`) before this
     * command ran, so an answer given to the wizard exists only in the file it was written to.
     * Recording it here is what lets `loadEnvConfigAtRuntime()` take the database connection from the
     * already-resolved configuration without losing the installer's own answers.
     *
     * @var array<string, string>
     */
    protected $wizardAnswers = [];

    /**
     * Locales list.
     *
     * @var array
     */
    protected $locales = [
        'ar' => 'Arabic',
        'en' => 'English',
        'tr' => 'Turkish',
        'es' => 'Spanish',
        'fa' => 'Persian',
        'pt_BR' => 'Portuguese',
        'zh_CN' => 'Chinese (Simplified)',
    ];

    /**
     * Currencies list.
     *
     * @var array
     */
    protected $currencies = [
        'AED' => 'United Arab Emirates Dirham',
        'ARS' => 'Argentine Peso',
        'AUD' => 'Australian Dollar',
        'BDT' => 'Bangladeshi Taka',
        'BRL' => 'Brazilian Real',
        'CAD' => 'Canadian Dollar',
        'CHF' => 'Swiss Franc',
        'CLP' => 'Chilean Peso',
        'CNY' => 'Chinese Yuan',
        'COP' => 'Colombian Peso',
        'CZK' => 'Czech Koruna',
        'DKK' => 'Danish Krone',
        'DZD' => 'Algerian Dinar',
        'EGP' => 'Egyptian Pound',
        'EUR' => 'Euro',
        'FJD' => 'Fijian Dollar',
        'GBP' => 'British Pound Sterling',
        'HKD' => 'Hong Kong Dollar',
        'HUF' => 'Hungarian Forint',
        'IDR' => 'Indonesian Rupiah',
        'ILS' => 'Israeli New Shekel',
        'INR' => 'Indian Rupee',
        'JOD' => 'Jordanian Dinar',
        'JPY' => 'Japanese Yen',
        'KRW' => 'South Korean Won',
        'KWD' => 'Kuwaiti Dinar',
        'KZT' => 'Kazakhstani Tenge',
        'LBP' => 'Lebanese Pound',
        'LKR' => 'Sri Lankan Rupee',
        'LYD' => 'Libyan Dinar',
        'MAD' => 'Moroccan Dirham',
        'MUR' => 'Mauritian Rupee',
        'MXN' => 'Mexican Peso',
        'MYR' => 'Malaysian Ringgit',
        'NGN' => 'Nigerian Naira',
        'NOK' => 'Norwegian Krone',
        'NPR' => 'Nepalese Rupee',
        'NZD' => 'New Zealand Dollar',
        'OMR' => 'Omani Rial',
        'PAB' => 'Panamanian Balboa',
        'PEN' => 'Peruvian Nuevo Sol',
        'PHP' => 'Philippine Peso',
        'PKR' => 'Pakistani Rupee',
        'PLN' => 'Polish Zloty',
        'PYG' => 'Paraguayan Guarani',
        'QAR' => 'Qatari Rial',
        'RON' => 'Romanian Leu',
        'RUB' => 'Russian Ruble',
        'SAR' => 'Saudi Riyal',
        'SEK' => 'Swedish Krona',
        'SGD' => 'Singapore Dollar',
        'THB' => 'Thai Baht',
        'TND' => 'Tunisian Dinar',
        'TRY' => 'Turkish Lira',
        'TWD' => 'New Taiwan Dollar',
        'UAH' => 'Ukrainian Hryvnia',
        'USD' => 'United States Dollar',
        'UZS' => 'Uzbekistani Som',
        'VEF' => 'Venezuelan Bolívar',
        'VND' => 'Vietnamese Dong',
        'XAF' => 'CFA Franc BEAC',
        'XOF' => 'CFA Franc BCEAO',
        'ZAR' => 'South African Rand',
        'ZMW' => 'Zambian Kwacha',
    ];

    /**
     * Install and configure krayin.
     */
    public function handle()
    {
        $this->output->writeln(ComposerEvents::cloudHostingBox());

        $applicationDetails = ! $this->option('skip-env-check')
            ? $this->checkForEnvFile()
            : [];

        $this->loadEnvConfigAtRuntime();

        /**
         * Asked twice on purpose. The first call runs before `key:generate`, so a refused run never
         * rotates `APP_KEY` — losing it is damage of its own, and the 2026-11-08 incident did it. The
         * second runs immediately before `migrate:fresh`, which is the call that destroys data: it
         * re-resolves the connection from scratch, so what it proves is what the wipe inherits rather
         * than what some earlier step believed.
         */
        if (! $this->guardDestructiveTarget()) {
            return self::FAILURE;
        }

        $this->warn('Step: Generating key...');
        $this->call('key:generate');

        $this->warn('Step: Migrating all tables...');

        if (! $this->guardDestructiveTarget()) {
            return self::FAILURE;
        }

        $this->call('migrate:fresh');

        $this->warn('Step: Seeding basic data for Krayin kickstart...');
        $this->info(app(KrayinDatabaseSeeder::class)->run([
            /*
             * The seeders resolve every name they write through the install locale, and fall back
             * to the running app locale when they are not told one. Passing a hardcoded `en` here
             * would defeat that fallback: `--skip-env-check` leaves $applicationDetails empty, so a
             * non-interactive install of an `ar` application would be seeded with English attribute
             * names, email templates, workflows and role label.
             */
            'locale' => $applicationDetails['locale'] ?? config('app.locale'),
            'currency' => $applicationDetails['currency'] ?? 'USD',
        ]));

        $this->warn('Step: Publishing assets and configurations...');
        $result = $this->call('vendor:publish', ['--provider' => CoreServiceProvider::class, '--force' => true]);
        $this->info($result);

        $this->warn('Step: Linking storage directory...');
        $this->call('storage:link');

        $this->warn('Step: Clearing cached bootstrap files...');
        $this->call('optimize:clear');

        if (! $this->option('skip-admin-creation')) {
            $this->warn('Step: Create admin credentials...');

            $this->createAdminCredentials();
        }

        ComposerEvents::postCreateProject();
    }

    /**
     *  Checking .env file and if not found then create .env file
     *
     * @return ?array
     */
    protected function checkForEnvFile()
    {
        if (! file_exists(base_path('.env'))) {
            $this->info('Creating the environment configuration file.');

            File::copy('.env.example', '.env');
        } else {
            $this->info('Great! your environment configuration file already exists.');
        }

        return $this->createEnvFile();
    }

    /**
     * Create a new .env file. Afterwards, request environment configuration details and set them
     * in the .env file to facilitate the migration to our database.
     *
     * @return ?array
     */
    protected function createEnvFile()
    {
        try {
            $applicationDetails = $this->askForApplicationDetails();

            $this->askForDatabaseDetails();

            return $applicationDetails;
        } catch (\Exception $e) {
            $this->error('Error in creating .env file, please create it manually and then run `php artisan migrate` again.');
        }
    }

    /**
     * Ask for application details.
     *
     * @return void
     */
    protected function askForApplicationDetails()
    {
        $this->updateEnvVariable(
            'APP_NAME',
            'Please enter the application name',
            env('APP_NAME', 'Krayin CRM')
        );

        $this->updateEnvVariable(
            'APP_URL',
            'Please enter the application URL',
            env('APP_URL', 'http://localhost:8000')
        );

        $this->envUpdate(
            'APP_TIMEZONE',
            date_default_timezone_get()
        );

        $this->info('Your Default Timezone is '.date_default_timezone_get());

        $locale = $this->updateEnvChoice(
            'APP_LOCALE',
            'Please select the default application locale',
            $this->locales
        );

        $currency = $this->updateEnvChoice(
            'APP_CURRENCY',
            'Please select the default currency',
            $this->currencies
        );

        return [
            'locale' => $locale,
            'currency' => $currency,
        ];
    }

    /**
     * Add the database credentials to the .env file.
     */
    protected function askForDatabaseDetails()
    {
        $databaseDetails = [
            'DB_CONNECTION' => select(
                'Please select the database connection',
                ['mysql', 'mariadb', 'pgsql', 'sqlsrv']
            ),

            'DB_HOST' => text(
                label: 'Please enter the database host',
                default: env('DB_HOST', '127.0.0.1'),
                required: true
            ),

            'DB_PORT' => text(
                label: 'Please enter the database port',
                default: env('DB_PORT', '3306'),
                required: true
            ),

            'DB_DATABASE' => text(
                label: 'Please enter the database name',
                default: env('DB_DATABASE', ''),
                required: true
            ),

            'DB_PREFIX' => text(
                label: 'Please enter the database prefix',
                default: env('DB_PREFIX', ''),
                hint: 'or press enter to continue',
                validate: function ($value) {
                    $input = strlen($value);

                    if ($input
                        && ($input < 1
                        || $input > 6)
                    ) {
                        return 'The database prefix must be between 1 and 6 characters.';
                    }

                    if (preg_match('/[^a-zA-Z0-9_]/', $value)) {
                        return 'The database prefix may only contain letters, numbers, and underscores.';
                    }

                    return null;
                }
            ),

            'DB_USERNAME' => text(
                label: 'Please enter your database username',
                default: env('DB_USERNAME', ''),
                required: true
            ),

            'DB_PASSWORD' => password(
                label: 'Please enter your database password',
                required: false
            ),
        ];

        if (
            ! $databaseDetails['DB_DATABASE']
            || ! $databaseDetails['DB_USERNAME']
            || ! $databaseDetails['DB_PASSWORD']
        ) {
            return $this->error('Please enter the database credentials.');
        }

        foreach ($databaseDetails as $key => $value) {
            if ($value) {
                $this->envUpdate($key, $value);
            }
        }
    }

    /**
     * Create a admin credentials.
     *
     * @return mixed
     */
    protected function createAdminCredentials()
    {
        $adminName = text(
            label: 'Enter the name of the admin user',
            default: 'Example',
            required: true
        );

        $adminEmail = text(
            label: 'Enter the email address of the admin user',
            default: 'admin@example.com',
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'The email address you entered is not valid please try again.',
                default => null
            }
        );

        /**
         * The administrator password is prompted for with `password()` rather than `text()`, so it
         * is not echoed into terminal scrollback or a CI log, and it carries no default: a
         * pre-filled value that an operator can accept with Enter is a published credential on
         * every installation that takes it.
         */
        $adminPassword = password(
            label: 'Configure the password for the admin user',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 8 => 'The password must be at least 8 characters.',
                default => null
            }
        );

        $password = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 10]);

        try {
            DB::table('users')->updateOrInsert(
                ['id' => 1],
                [
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password' => $password,
                    'role_id' => 1,
                    'status' => 1,
                ]
            );

            $filePath = storage_path('installed');

            File::put($filePath, 'Krayin is successfully installed');

            /**
             * Recorded in the database as well as on disk, so losing the marker file cannot reopen
             * the installer on an application that already holds real data.
             */
            app(DatabaseManager::class)->markInstallationCompleted();

            app(DatabaseManager::class)->clearInstallationInProgress();

            $this->info('-----------------------------');
            $this->info('Congratulations!');
            $this->info('The installation has been finished and you can now use Krayin.');
            $this->info('Go to '.env('APP_URL').'/admin/dashboard'.' and authenticate with:');
            $this->info('Email: '.$adminEmail);
            $this->info('Password: the one you just set.');
            $this->info('Cheers!');

            Event::dispatch('krayin.installed');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Loaded Env variables for config files.
     */
    protected function loadEnvConfigAtRuntime(): void
    {
        $this->warn('Loading configs...');

        /**
         * Setting application environment.
         */
        app()['env'] = $this->getEnvAtRuntime('APP_ENV');

        /**
         * Setting application configuration.
         */
        config([
            'app.env' => $this->getEnvAtRuntime('APP_ENV'),
            'app.name' => $this->getEnvAtRuntime('APP_NAME'),
            'app.url' => $this->getEnvAtRuntime('APP_URL'),
            'app.timezone' => $this->getEnvAtRuntime('APP_TIMEZONE'),
            'app.locale' => $this->getEnvAtRuntime('APP_LOCALE'),
            'app.currency' => $this->getEnvAtRuntime('APP_CURRENCY'),
        ]);

        /**
         * Setting database configurations.
         *
         * Every one of these is taken from the configuration this application resolved when it
         * booted, not re-read from the `.env` file. That file is what made the installer ignore
         * `DB_DATABASE`: an exported value was overwritten with the file's own one line before
         * `migrate:fresh` ran, so a caller could not retarget the command at all and a populated
         * database was destroyed with it. See
         * .agents/context/errors/installer-ignores-env-override.md.
         */
        $databaseConnection = $this->wizardAnswers['DB_CONNECTION'] ?? config('database.default');

        config([
            "database.connections.{$databaseConnection}.host" => $this->getDatabaseSettingAtRuntime($databaseConnection, 'host', 'DB_HOST'),
            "database.connections.{$databaseConnection}.port" => $this->getDatabaseSettingAtRuntime($databaseConnection, 'port', 'DB_PORT'),
            "database.connections.{$databaseConnection}.database" => $this->getDatabaseSettingAtRuntime($databaseConnection, 'database', 'DB_DATABASE', $this->option('target-database')),
            "database.connections.{$databaseConnection}.username" => $this->getDatabaseSettingAtRuntime($databaseConnection, 'username', 'DB_USERNAME'),
            "database.connections.{$databaseConnection}.password" => $this->getDatabaseSettingAtRuntime($databaseConnection, 'password', 'DB_PASSWORD'),
            "database.connections.{$databaseConnection}.prefix" => $this->getDatabaseSettingAtRuntime($databaseConnection, 'prefix', 'DB_PREFIX'),
        ]);

        DB::purge($databaseConnection);

        $this->info('Configuration loaded...');
    }

    /**
     * One `database.connections` setting, resolved the way the rest of the application resolves it.
     *
     * Three sources, in order: an explicit `--target-database` (the database name only), an answer
     * this run's wizard gave, and the value the application already resolved into `config()` at boot
     * — which is the caller's exported `DB_*` when there is one and the `.env` value otherwise.
     *
     * The `.env` **file** is deliberately not consulted. Re-reading it here is exactly the defect: it
     * makes the connection the installer migrates depend on a file the caller did not change, so no
     * environment override could bound the command.
     */
    protected function getDatabaseSettingAtRuntime(string $connection, string $setting, string $envKey, ?string $explicit = null): mixed
    {
        if ($explicit) {
            return $explicit;
        }

        if (array_key_exists($envKey, $this->wizardAnswers)) {
            return $this->wizardAnswers[$envKey];
        }

        return config("database.connections.{$connection}.{$setting}");
    }

    /**
     * Why the database this run would migrate must not be wiped, or `null` when it may be.
     *
     * Read by `guardDestructiveTarget()`, which is called immediately before `migrate:fresh`. It
     * resolves the connection from the configuration at the moment of the call and probes it, so the
     * answer describes the connection the destructive sub-call will inherit rather than what some
     * earlier step believed: a check made before `loadEnvConfigAtRuntime()` purged the connection is
     * decoration, because the purge is what replaced the connection the check had looked at.
     *
     * The second fence is deliberately not a name comparison. Refusing on a *mismatch* between the
     * configured target and `.env` passes in precisely the case that destroyed the live database —
     * nothing overridden, so both sides name the live database. What makes a wipe harmful is the data
     * already in the target, so that is what is measured, and no name can satisfy it by accident.
     */
    public function destructiveTargetProblem(bool $discardExistingData = false): ?string
    {
        $connection = $this->wizardAnswers['DB_CONNECTION'] ?? config('database.default');

        $target = config("database.connections.{$connection}.database");

        try {
            $probe = DB::connection($connection);

            /**
             * Opening the connection is the point of the probe: an unreachable server or a database
             * that does not exist is refused here instead of failing halfway through the wipe.
             */
            $probe->getPdo();

            $resolved = $probe->getDatabaseName();
        } catch (Throwable $e) {
            return sprintf(
                'Refusing to install: the "%s" connection cannot be reached (%s). Nothing has been migrated.',
                $connection,
                $e->getMessage()
            );
        }

        if ($resolved !== $target) {
            return sprintf(
                'Refusing to install: the "%s" connection points at "%s" while the installer would migrate "%s". Nothing has been migrated.',
                $connection,
                $resolved,
                $target
            );
        }

        if ($discardExistingData) {
            return null;
        }

        $database = app(DatabaseManager::class);

        try {
            $populated = $database->isInstalled();

            $completed = $database->isInstallationCompleted();
        } catch (Throwable $e) {
            return sprintf(
                'Refusing to install: could not tell whether "%s" already holds data (%s). Nothing has been migrated.',
                $target,
                $e->getMessage()
            );
        }

        if ($populated || $completed) {
            return sprintf(
                'Refusing to wipe "%s": it already holds an installed application (%s). Nothing has been migrated. Pass --discard-existing-data only if that data is disposable.',
                $target,
                $populated ? 'administrator rows present' : 'installation.completed recorded'
            );
        }

        return null;
    }

    /**
     * Report the database this run will migrate, and stop when wiping it cannot be justified.
     */
    protected function guardDestructiveTarget(): bool
    {
        $connection = $this->wizardAnswers['DB_CONNECTION'] ?? config('database.default');

        $problem = $this->destructiveTargetProblem((bool) $this->option('discard-existing-data'));

        if ($problem !== null) {
            $this->error($problem);

            return false;
        }

        $this->info(sprintf(
            'Target database: %s (connection %s)',
            config("database.connections.{$connection}.database"),
            $connection
        ));

        return true;
    }

    /**
     * Method for asking the details of .env files
     */
    protected function updateEnvVariable(string $key, string $question, string $defaultValue): void
    {
        $input = text(
            label: $question,
            default: $defaultValue,
            required: true
        );

        $this->envUpdate($key, $input ?: $defaultValue);
    }

    /**
     * Method for asking choice based on the list of options.
     *
     * @return string
     */
    protected function updateEnvChoice(string $key, string $question, array $choices)
    {
        $choice = select(
            label: $question,
            options: $choices,
            default: env($key)
        );

        $this->envUpdate($key, $choice);

        return $choice;
    }

    /**
     * Update the .env values.
     */
    protected function envUpdate(string $key, string $value): void
    {
        $data = file_get_contents(base_path('.env'));

        // Check if $value contains spaces, and if so, add double quotes
        if (preg_match('/\s/', $value)) {
            $value = '"'.$value.'"';
        }

        $data = preg_replace("/$key=(.*)/", "$key=$value", $data);

        file_put_contents(base_path('.env'), $data);

        /**
         * Recorded as well as written: the connection this run migrates is taken from the resolved
         * configuration, and an answer given now is not in it yet.
         */
        $this->wizardAnswers[$key] = $value;
    }

    /**
     * Check key in `.env` file because it will help to find values at runtime.
     */
    protected static function getEnvAtRuntime(string $key): string|bool
    {
        if ($data = file(base_path('.env'))) {
            foreach ($data as $line) {
                $line = preg_replace('/\s+/', '', $line);

                $rowValues = explode('=', $line);

                if (strlen($line) !== 0) {
                    if (strpos($key, $rowValues[0]) !== false) {
                        return $rowValues[1];
                    }
                }
            }
        }

        return false;
    }
}
