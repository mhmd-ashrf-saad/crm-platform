<?php

use Database\Seeders\Demo\DemoCredentials;
use Database\Seeders\Demo\DemoIdentity;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Webkul\Admin\Helpers\Dashboard;
use Webkul\Admin\Helpers\Reporting\AbstractReporting;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as InstallerSeeder;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * The demo dataset must be safe to run on an installation somebody is already using. These tests
 * pin the four properties the seeder promises: it is re-runnable, it never touches a user outside
 * its reserved domain, everything it links points at a row that exists, and its money adds up.
 *
 * The seeder is run through `Artisan::call('db:seed')` on purpose: that is exactly how the
 * operator runs it, including the `Model::unguarded()` wrapper the seed command installs, which
 * the seeder has to restore for the entity models to behave as they do behind a controller.
 *
 * The tests provision the installer's reference data themselves (see `seedInstallerReferenceData()`)
 * rather than assuming the database they run against was seeded for them. The demo seeder reads
 * roles, pipelines, stages, sources, types and attributes, and refuses to run without them; that
 * guard is product behaviour. A database that was only migrated has none of that reference data, and
 * CI runs the suite in parallel, where every worker database is migrated but never seeded.
 */

/**
 * The credentials path this test run uses, so the operator's real file is never touched.
 *
 * A path under `storage/framework/testing` keeps a failure reproducible; the file is removed
 * after every test.
 */
function demoCredentialsPath(): string
{
    static $path;

    return $path ??= storage_path('framework/testing/demo-credentials-'.Str::random(12).'.txt');
}

/**
 * A credentials *directory* this test run owns.
 *
 * `DemoCredentials::DEFAULT_DIRECTORY` is `/var/tmp/pi-scratch/demo-seed`, which holds the file a
 * live instance depends on right now. The suite must never create, overwrite or delete anything
 * there — doing so is the very defect this feature exists to prevent — so a test that exercises the
 * database-derived naming designates its own directory instead. `DemoCredentials::path()` takes one
 * as its second argument for exactly that reason, so the real derivation still runs: the connection
 * is read and the name is turned into a filename, only the write lands somewhere the suite owns.
 */
function demoCredentialsDirectory(): string
{
    return storage_path('framework/testing/demo-credentials-'.Str::random(12));
}

/**
 * The demo passwords as the credentials file holds them, keyed by address.
 *
 * @return array<string, string>
 */
function demoCredentialsFilePasswords(?string $path = null): array
{
    $passwords = [];

    foreach (file($path ?? demoCredentialsPath(), FILE_IGNORE_NEW_LINES) as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$email, $password] = explode(': ', $line, 2);

        $passwords[$email] = $password;
    }

    return $passwords;
}

/**
 * Provision the reference data a real installation already has.
 *
 * The demo seeder deliberately creates none of it: roles, pipelines, stages, sources, types,
 * attributes, email templates, workflows and the installer's own admin row belong to the installer,
 * and the seeder reads them and fails loudly when they are missing. So a test of the seeder needs
 * an installation to exist first, and this test has to build it rather than borrow it.
 *
 * Borrowing is what used to happen: `make test` seeds `krayin_test`, so locally the rows were
 * already there, while CI runs `vendor/bin/pest --parallel`, and Laravel's parallel runner gives
 * each worker its own database that it creates and only `migrate`s (never seeds). Every demo test
 * therefore ran against a migrated-but-empty installation and failed on the missing role.
 *
 * The installer's own `DatabaseSeeder` is used on purpose, so the shapes are the ones the product
 * ships instead of hand-written approximations: it is the same class `php artisan db:seed` (and
 * `scripts/prepare-test-db.sh`) runs.
 *
 * The locale is forced to English because the demo seeder matches the *names* of the installed lead
 * sources and types as English literals (`Email`, `Web Form`, `New Business`, ...). Those names are
 * translations, so seeding them in the deployment's `APP_LOCALE` would make this test depend on an
 * environment setting that has nothing to do with what it is testing.
 */
function seedInstallerReferenceData(): void
{
    config(['app.locale' => 'en']);

    $exitCode = Artisan::call('db:seed', [
        '--class' => InstallerSeeder::class,
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0, 'The installer reference-data seeder failed: '.Artisan::output());

    /**
     * The rows below are the ones the demo seeder reads and refuses to invent. Checking for them by
     * name rather than by count keeps this test from breaking when the installer adds a source, a
     * type or a stage.
     */
    expect(DB::table('roles')->where('permission_type', 'all')->exists())->toBeTrue()
        ->and(DB::table('lead_pipeline_stages')->pluck('code')->sort()->values()->all())
        ->toBe(['follow-up', 'lost', 'negotiation', 'new', 'prospect', 'won'])
        ->and(DB::table('lead_sources')->pluck('name')->all())
        ->toBe(['Email', 'Web', 'Web Form', 'Phone', 'Direct'])
        ->and(DB::table('lead_types')->pluck('name')->all())
        ->toBe(['New Business', 'Existing Business']);
}

/**
 * Run the demo seeder exactly as the operator would.
 */
function seedDemoDataset(): void
{
    $exitCode = Artisan::call('db:seed', [
        '--class' => DemoSeeder::class,
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0, 'The demo seeder failed: '.Artisan::output());
}

/**
 * The row counts every table in the demo report ends up with.
 *
 * @return array<string, int>
 */
function demoTableCounts(): array
{
    $counts = [];

    foreach (DemoSeeder::REPORTED_TABLES as $table) {
        $counts[$table] = DB::table($table)->count();
    }

    return $counts;
}

/**
 * The seeded lead titles, in insertion order.
 *
 * @return array<int, string>
 */
function demoLeadTitles(): array
{
    return DB::table('leads')->orderBy('id')->pluck('title')->all();
}

beforeEach(function () {
    $_ENV['DEMO_SEED_CREDENTIALS_PATH'] = demoCredentialsPath();
    $_SERVER['DEMO_SEED_CREDENTIALS_PATH'] = demoCredentialsPath();

    seedInstallerReferenceData();
});

afterEach(function () {
    unset($_ENV['DEMO_SEED_CREDENTIALS_PATH'], $_SERVER['DEMO_SEED_CREDENTIALS_PATH']);

    $path = demoCredentialsPath();

    if (file_exists($path)) {
        unlink($path);
    }
});

it('seeds the same record set when it runs twice', function () {
    seedDemoDataset();

    $firstCounts = demoTableCounts();
    $firstTitles = demoLeadTitles();
    $firstTotals = DB::table('quotes')->orderBy('id')->pluck('grand_total')->all();

    seedDemoDataset();

    expect(demoTableCounts())->toBe($firstCounts)
        ->and(demoLeadTitles())->toBe($firstTitles)
        ->and(DB::table('quotes')->orderBy('id')->pluck('grand_total')->all())->toBe($firstTotals);
});

it('never deletes or modifies a user outside the reserved demo domain', function () {
    $password = 'pre-existing-password';

    $preExisting = User::query()->create([
        'name' => 'عميل قائم',
        'email' => 'existing.user@sijilna.test',
        'password' => Hash::make($password),
        'role_id' => DB::table('roles')->value('id'),
        'status' => 1,
        'view_permission' => 'global',
    ]);

    $before = (array) DB::table('users')->where('id', $preExisting->id)->first();

    $installerAdmin = (array) DB::table('users')->where('email', 'admin@example.com')->first();

    seedDemoDataset();

    $after = (array) DB::table('users')->where('id', $preExisting->id)->first();

    expect($after)->toBe($before)
        ->and($after['password'])->toBe($before['password'])
        ->and(Hash::check($password, $after['password']))->toBeTrue();

    /**
     * The operator's own login must still work, not merely still exist.
     */
    expect(Auth::attempt(['email' => $preExisting->email, 'password' => $password]))->toBeTrue();

    if ($installerAdmin !== []) {
        expect((array) DB::table('users')->where('email', 'admin@example.com')->first())->toBe($installerAdmin);
    }
});

it('deletes only users whose address is on the reserved demo domain', function () {
    seedDemoDataset();

    $outsiders = DB::table('users')
        ->where('email', 'not like', DemoSeeder::RESERVED_EMAIL_PATTERN)
        ->pluck('email')
        ->all();

    foreach ($outsiders as $email) {
        expect(DemoIdentity::isDemoEmail($email))->toBeFalse();
    }

    expect(DB::table('users')->where('email', 'like', '%@sijilna.demo')->count())->toBe(3)
        ->and($outsiders)->not->toBeEmpty();
});

it('writes the demo passwords as real bcrypt hashes, with the file at the configured path', function () {
    seedDemoDataset();

    expect(file_exists(demoCredentialsPath()))->toBeTrue();

    $hashes = DB::table('users')
        ->where('email', 'like', DemoSeeder::RESERVED_EMAIL_PATTERN)
        ->pluck('password', 'email')
        ->all();

    expect($hashes)->toHaveCount(3);

    foreach ($hashes as $hash) {
        expect($hash)->toStartWith('$2y$')
            ->and(strlen($hash))->toBe(60);
    }

    /**
     * The path is a fixture this test owns and the seeder wrote moments ago, so reading it back is
     * not a dependency on an artefact the operator has to create before CI can pass.
     */
    $passwords = demoCredentialsFilePasswords();

    expect($passwords)->toHaveCount(3);

    foreach ($passwords as $email => $password) {
        expect(Hash::check($password, $hashes[$email]))->toBeTrue()
            /**
             * Not merely a matching hash: the guard the admin login uses must accept the pair.
             */
            ->and(Auth::guard('user')->validate(['email' => $email, 'password' => $password]))->toBeTrue();
    }
});

it('points every lead at a stage that exists', function () {
    seedDemoDataset();

    $danglingStages = DB::table('leads')
        ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
        ->whereNull('lead_pipeline_stages.id')
        ->count();

    $danglingPipelines = DB::table('leads')
        ->leftJoin('lead_pipelines', 'leads.lead_pipeline_id', '=', 'lead_pipelines.id')
        ->whereNull('lead_pipelines.id')
        ->count();

    $danglingPersons = DB::table('leads')
        ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
        ->whereNull('persons.id')
        ->count();

    expect($danglingStages)->toBe(0)
        ->and($danglingPipelines)->toBe(0)
        ->and($danglingPersons)->toBe(0);

    /**
     * Every installed stage is represented, so the kanban has a card in every column.
     */
    $stageCodes = DB::table('leads')
        ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
        ->distinct()
        ->pluck('lead_pipeline_stages.code')
        ->sort()
        ->values()
        ->all();

    expect($stageCodes)->toBe(['follow-up', 'lost', 'negotiation', 'new', 'prospect', 'won']);
});

it('gives every quote line items and a total that matches them', function () {
    seedDemoDataset();

    $quotes = DB::table('quotes')->orderBy('id')->get();

    expect($quotes)->not->toBeEmpty();

    foreach ($quotes as $quote) {
        $items = DB::table('quote_items')->where('quote_id', $quote->id)->get();

        expect($items)->not->toBeEmpty();

        $subTotal = 0.0;
        $tax = 0.0;
        $discount = 0.0;

        foreach ($items as $item) {
            $base = round((float) $item->price * (int) $item->quantity, 2);

            $subTotal += $base;
            $tax += (float) $item->tax_amount;
            $discount += (float) $item->discount_amount;

            expect(round((float) $item->total, 2))->toBe($base);
        }

        /**
         * The quote screen computes exactly this: subtotal + tax - discount + adjustment.
         */
        expect(round((float) $quote->sub_total, 2))->toBe(round($subTotal, 2))
            ->and(round((float) $quote->grand_total, 2))
            ->toBe(round($subTotal + $tax - $discount + (float) $quote->adjustment_amount, 2));

        expect(DB::table('persons')->where('id', $quote->person_id)->exists())->toBeTrue();
    }
});

it('fills every module it claims to fill', function () {
    seedDemoDataset();

    expect(DB::table('leads')->count())->toBeGreaterThan(20)
        ->and(DB::table('organizations')->count())->toBeGreaterThan(5)
        ->and(DB::table('persons')->count())->toBeGreaterThan(10)
        ->and(DB::table('products')->count())->toBeGreaterThan(10)
        ->and(DB::table('quotes')->count())->toBeGreaterThan(5)
        ->and(DB::table('tags')->count())->toBeGreaterThan(3)
        ->and(DB::table('marketing_campaigns')->count())->toBeGreaterThan(1)
        ->and(DB::table('web_forms')->count())->toBeGreaterThan(0)
        ->and(DB::table('web_form_attributes')->count())->toBeGreaterThan(0)
        ->and(DB::table('attribute_values')->count())->toBeGreaterThan(100);

    /**
     * The calendar and the activity grid only read call, meeting and lunch.
     */
    $plannerTypes = DB::table('activities')->whereIn('type', ['call', 'meeting', 'lunch'])->count();

    expect($plannerTypes)->toBeGreaterThan(10)
        ->and(DB::table('activities')->whereNotNull('schedule_from')->where('schedule_from', '>', now())->count())->toBeGreaterThan(0)
        ->and(DB::table('activities')->whereNotNull('schedule_from')->where('schedule_from', '<', now())->count())->toBeGreaterThan(0);

    /**
     * The mailbox has threads to open and an unread one to notice.
     */
    expect(DB::table('emails')->whereNull('parent_id')->count())->toBeGreaterThan(2)
        ->and(DB::table('emails')->where('is_read', 0)->count())->toBeGreaterThan(0)
        ->and(DB::table('emails')->whereNotNull('parent_id')->count())->toBeGreaterThan(2);

    /**
     * Tags are attached to more than one kind of record.
     */
    expect(DB::table('lead_tags')->count())->toBeGreaterThan(5)
        ->and(DB::table('person_tags')->count())->toBeGreaterThan(0)
        ->and(DB::table('product_tags')->count())->toBeGreaterThan(0);
});

it('refuses to seed an installation with no role and rolls the partial wipe back', function () {
    seedDemoDataset();

    /**
     * A role is what is missing, so that is what this removes. The foreign key on `users.role_id`
     * cascades, so the users go with it -- which is exactly the state a run against a half-installed
     * database starts from: reference data missing, demo rows from an earlier run still in place.
     */
    DB::table('roles')->delete();

    /**
     * The role's removal cascades into the users; the demo's contacts, products and leads survive
     * it (their foreign keys do not cascade), and those are the rows the run's wipe deletes before
     * it reaches the missing role. They are the rows a rollback therefore has to restore.
     */
    $before = demoTableCounts();

    expect(DB::table('roles')->count())->toBe(0)
        ->and($before['persons'])->toBeGreaterThan(10)
        ->and($before['products'])->toBeGreaterThan(10);

    /**
     * The successful run wrote the credentials file. Remove it, so the assertion at the end of this
     * test is about the failing run and not about the file that was already there.
     */
    unlink(demoCredentialsPath());

    /**
     * The seeder cannot know what role the demo users should hold, so it must refuse -- not invent
     * a role, and not leave the wipe it already performed committed.
     */
    expect(fn () => seedDemoDataset())->toThrow(
        RuntimeException::class,
        'No role exists in this installation; run the installer before the demo seeder.'
    );

    /**
     * The wipe ran before the failure, inside the seeder's transaction: identical counts afterwards
     * are the proof it was rolled back instead of committed.
     */
    expect(demoTableCounts())->toBe($before)
        ->and(DB::table('users')->where('email', 'like', DemoSeeder::RESERVED_EMAIL_PATTERN)->count())->toBe(0)
        ->and(DB::table('roles')->count())->toBe(0);

    /**
     * Nothing the seeder owes the operator is written on a failed run either.
     */
    expect(file_exists(demoCredentialsPath()))->toBeFalse();
});

it('leaves installed reference data exactly as the installer seeded it', function () {
    $pipelines = DB::table('lead_pipelines')->count();
    $stages = DB::table('lead_pipeline_stages')->count();
    $sources = DB::table('lead_sources')->count();
    $types = DB::table('lead_types')->count();
    $attributes = DB::table('attributes')->count();
    $roles = DB::table('roles')->count();

    seedDemoDataset();

    expect(DB::table('lead_pipelines')->count())->toBe($pipelines)
        ->and(DB::table('lead_pipeline_stages')->count())->toBe($stages)
        ->and(DB::table('lead_sources')->count())->toBe($sources)
        ->and(DB::table('lead_types')->count())->toBe($types)
        ->and(DB::table('attributes')->count())->toBe($attributes)
        ->and(DB::table('roles')->count())->toBe($roles)
        ->and(DB::table('email_templates')->whereIn('name', ['Activity created', 'Activity modified'])->count())
        ->toBe(2);
});

it('opens the dashboard on a window that covers the dataset it seeds', function () {
    /**
     * A freshly installed instance holds no row for the dashboard range: the settings screen has
     * never been saved, so `SystemConfig` answers the field's own default and the dashboard falls
     * back to one month. That is the state the first screen of a demo used to open in -- won
     * `US$ 0.00`, lost `US$ 0.00`, «إجمالي المؤسسات» `0` and two «لا توجد بيانات متاحة» panels --
     * because the fixtures reach 75 days back (bead `crm-703`).
     */
    DB::table('core_config')->where('code', DemoSeeder::DASHBOARD_RANGE_SETTING)->delete();

    expect(core()->getConfigData(DemoSeeder::DASHBOARD_RANGE_SETTING))->toBe('1_month');

    seedDemoDataset();

    expect(core()->getConfigData(DemoSeeder::DASHBOARD_RANGE_SETTING))->toBe(DemoSeeder::DASHBOARD_RANGE)
        ->and(DB::table('core_config')->where('code', DemoSeeder::DASHBOARD_RANGE_SETTING)->pluck('value')->all())
        ->toBe([DemoSeeder::DASHBOARD_RANGE]);

    /**
     * The setting is only worth writing if the window it produces is the one the date control
     * starts on, so the reporting helper's own default is read rather than a copy of the rule.
     */
    $reporting = new class extends AbstractReporting {};

    expect($reporting->getDefaultStartDate())->toEqual(now()->subMonths(3)->startOfDay())
        ->and($reporting->getDefaultStartDate()->lessThanOrEqualTo(now()->subDays(75)))->toBeTrue();

    /**
     * And the numbers the dashboard reports on that untouched first load are the ones the tiles
     * print: nothing here passes a `start` or `end`, exactly as a browser that lands on
     * `/admin/dashboard` passes none.
     */
    $dashboard = app(Dashboard::class);

    $revenue = $dashboard->getRevenueStats();
    $overall = $dashboard->getOverAllStats();

    expect($revenue['total_won_revenue']['current'])->toBeGreaterThan(0)
        ->and($revenue['total_lost_revenue']['current'])->toBeGreaterThan(0)
        ->and($overall['total_organizations']['current'])->toBeGreaterThan(0)
        ->and($dashboard->getLeadsStatsBySources())->not->toBeEmpty()
        ->and($dashboard->getLeadsStatsByTypes())->not->toBeEmpty();
});

it('records the dashboard window once and never overwrites one the installation already holds', function () {
    DB::table('core_config')->where('code', DemoSeeder::DASHBOARD_RANGE_SETTING)->delete();

    seedDemoDataset();

    expect(DB::table('core_config')->where('code', DemoSeeder::DASHBOARD_RANGE_SETTING)->count())->toBe(1);

    /**
     * `core_config` has no unique index on `code`, so a second row is possible and would leave
     * `findOneWhere()` free to answer either one. A re-run must not add one.
     */
    seedDemoDataset();

    expect(DB::table('core_config')->where('code', DemoSeeder::DASHBOARD_RANGE_SETTING)->count())->toBe(1)
        ->and(DB::table('core_config')->where('code', DemoSeeder::DASHBOARD_RANGE_SETTING)->value('value'))
        ->toBe(DemoSeeder::DASHBOARD_RANGE);

    /**
     * A range the operator saved afterwards is an installation decision, and a seeder that can be
     * re-run must not move it: the same rule the installer's own locale seeder follows.
     */
    DB::table('core_config')
        ->where('code', DemoSeeder::DASHBOARD_RANGE_SETTING)
        ->update(['value' => '9_months']);

    seedDemoDataset();

    expect(DB::table('core_config')->where('code', DemoSeeder::DASHBOARD_RANGE_SETTING)->pluck('value')->all())
        ->toBe(['9_months']);
});

it('keeps the credentials file authenticating against the rows after two consecutive runs', function () {
    seedDemoDataset();

    $first = demoCredentialsFilePasswords();

    seedDemoDataset();

    $second = demoCredentialsFilePasswords();

    $hashes = DB::table('users')
        ->where('email', 'like', DemoSeeder::RESERVED_EMAIL_PATTERN)
        ->pluck('password', 'email')
        ->all();

    expect(array_keys($second))->toEqualCanonicalizing(array_keys($hashes));

    /**
     * Every line of the file the operator just received must work: the hash the login guard reads
     * and the clear-text password the file hands over are one pair, not two facts to be trusted.
     */
    foreach ($second as $email => $password) {
        expect(Hash::check($password, $hashes[$email]))->toBeTrue()
            ->and(Auth::guard('user')->validate(['email' => $email, 'password' => $password]))->toBeTrue();
    }

    /**
     * A rerun regenerates the passwords, so the first run's file describes logins that no longer
     * exist; a file still holding them is exactly the stale file this bead is about.
     */
    foreach ($first as $email => $password) {
        expect($password)->not->toBe($second[$email])
            ->and(Hash::check($password, $hashes[$email]))->toBeFalse();
    }
});

it('binds the credentials file to the database it describes', function () {
    /**
     * The defect: one global filename meant a run against a throwaway database replaced the file an
     * operator reads for the live one, and the passwords in it authenticated against neither the
     * rows they were generated for nor the file's new contents. The path is now derived from the
     * connection's database name.
     *
     * Every path here is under `demoCredentialsDirectory()`, not `DemoCredentials::DEFAULT_DIRECTORY`.
     * The default directory holds the file a live instance is using right now, so a test that writes
     * and deletes there would re-execute the defect against the directory this feature protects.
     */
    unset($_ENV['DEMO_SEED_CREDENTIALS_PATH'], $_SERVER['DEMO_SEED_CREDENTIALS_PATH']);

    $directory = demoCredentialsDirectory();
    $default = config('database.default');

    config([
        'database.connections.demo_other_database' => array_merge(
            config('database.connections.'.$default),
            ['database' => 'krayin_other']
        ),
        'database.default' => 'demo_other_database',
    ]);

    $otherPath = DemoCredentials::path(null, $directory);

    config(['database.default' => $default]);

    $currentPath = DemoCredentials::path(null, $directory);
    $currentDatabase = DB::connection()->getDatabaseName();

    /**
     * The seeded run goes through the operator's own entry point — `DemoCredentials::path()` with no
     * arguments — so the override is pointed at the path this derivation just produced. The run then
     * writes exactly the file the operator would get, in the directory this test owns.
     */
    $_ENV['DEMO_SEED_CREDENTIALS_PATH'] = $currentPath;
    $_SERVER['DEMO_SEED_CREDENTIALS_PATH'] = $currentPath;

    $sentinel = '# sentinel: another database\'s credentials'.PHP_EOL;

    /**
     * Every path this test can create, plus the directory itself when this test is the one creating
     * it, is registered *before* the first assertion and before the `try`: an expectation failing
     * anywhere below then still removes what the test made, instead of leaving a credentials file
     * behind in a directory a later run or an operator will read.
     */
    $removeDirectory = is_dir($directory) ? null : $directory;
    $created = [$otherPath, $currentPath, $currentPath.'.tmp'];

    /**
     * A file left by the pre-binding scheme is neither trusted nor deleted. On a machine that has
     * one, this run must leave it byte-identical.
     */
    $legacyDigest = file_exists(DemoCredentials::LEGACY_PATH)
        ? hash_file('sha256', DemoCredentials::LEGACY_PATH)
        : null;

    try {
        expect($otherPath)->not->toBe($currentPath)
            ->and(basename($otherPath))->toBe('credentials-krayin_other.txt')
            /**
             * The name comes from the connection, not from the default database configured in a
             * file: passing that name explicitly has to resolve to the very same path.
             */
            ->and($currentPath)->toBe(DemoCredentials::path($currentDatabase, $directory))
            ->and($currentPath)->toStartWith($directory.'/credentials-')
            ->and($currentPath)->not->toBe(DemoCredentials::LEGACY_PATH);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($otherPath, $sentinel);

        seedDemoDataset();

        /**
         * A file written for another database has to come through this database's run untouched.
         * The file that proves it is the sentinel above; the run it survives is a real one, seeded
         * the way the operator seeds it.
         */
        expect(file_exists($currentPath))->toBeTrue()
            ->and(file_get_contents($otherPath))->toBe($sentinel);

        /**
         * The header names what the file is bound to, next to the timestamp: a reader can see which
         * connection and database the passwords below belong to without guessing.
         */
        $header = implode("\n", array_filter(
            file($currentPath, FILE_IGNORE_NEW_LINES),
            fn (string $line) => str_starts_with($line, '#')
        ));

        expect($header)->toContain('database ['.$currentDatabase.']')
            ->and($header)->toContain('connection ['.DB::connection()->getName().']');
    } finally {
        foreach (array_unique($created) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if ($removeDirectory !== null && is_dir($removeDirectory)) {
            rmdir($removeDirectory);
        }
    }

    if ($legacyDigest !== null) {
        expect(hash_file('sha256', DemoCredentials::LEGACY_PATH))->toBe($legacyDigest);
    }
});

it('gives every database its own filename, and keeps the current one for a safe name', function () {
    /**
     * A slug alone is not one filename per database. `filename()` collapses every run of disallowed
     * bytes to a single `-`, so slugging alone puts `krayin$test` and `krayin-test` on one file, and
     * two multibyte names that differ only outside `[A-Za-z0-9._-]` on another.
     *
     * Nothing is written here: the derivation is pure, which is what lets it be checked against a
     * directory that is never created.
     */
    unset($_ENV['DEMO_SEED_CREDENTIALS_PATH'], $_SERVER['DEMO_SEED_CREDENTIALS_PATH']);

    $directory = demoCredentialsDirectory();

    /**
     * A name that is already filename-safe keeps exactly the name it has today. `krayin` is the live
     * instance's database and an operator is depending on the file that name already produced, so
     * renaming it would strand the credentials they are reading.
     */
    expect(basename(DemoCredentials::path('krayin', $directory)))->toBe('credentials-krayin.txt')
        ->and(basename(DemoCredentials::path('krayin_test', $directory)))->toBe('credentials-krayin_test.txt');

    $lossy = DemoCredentials::path('krayin$test', $directory);
    $safe = DemoCredentials::path('krayin-test', $directory);

    expect($lossy)->not->toBe($safe)
        ->and(basename($safe))->toBe('credentials-krayin-test.txt')
        ->and(basename($lossy))->toStartWith('credentials-krayin-test-')
        ->and(basename($lossy))->toEndWith('.txt');

    /**
     * The digest is a function of the raw name alone, so the same database always resolves to the
     * same file and two different ones never do.
     */
    expect(DemoCredentials::path('krayin$test', $directory))->toBe($lossy)
        ->and(DemoCredentials::path('krayin@test', $directory))->not->toBe($lossy)
        ->and(DemoCredentials::path('قاعدة', $directory))->not->toBe(DemoCredentials::path('بيانات', $directory))
        /**
         * Deriving a path writes nothing, so the directory above is still absent.
         */
        ->and(file_exists($directory))->toBeFalse();
});

it('fails loudly when a generated password does not match the row it was written to', function () {
    $credentials = new DemoCredentials;

    $users = [
        ['key' => 'manager', 'email' => 'manager@sijilna.demo'],
        ['key' => 'agent', 'email' => 'agent@sijilna.demo'],
    ];

    $credentials->generate($users);

    $hashes = [
        'manager@sijilna.demo' => Hash::make($credentials->password('manager')),
        'agent@sijilna.demo' => Hash::make('a password this run did not generate'),
    ];

    expect(fn () => $credentials->verify($users, $hashes))->toThrow(
        RuntimeException::class,
        'The generated demo password for [agent@sijilna.demo] does not authenticate'
    );

    /**
     * The failure names the account and never the password: the exception reaches a console and a
     * log file that are not the credentials file.
     */
    try {
        $credentials->verify($users, $hashes);
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->not->toContain($credentials->password('agent'))
            ->not->toContain($credentials->password('manager'));
    }

    /**
     * The same pair with the stored hash corrected passes: the check is about the pair, not about
     * refusing everything it is shown.
     */
    $credentials->verify($users, [
        'manager@sijilna.demo' => $hashes['manager@sijilna.demo'],
        'agent@sijilna.demo' => Hash::make($credentials->password('agent')),
    ]);
});
