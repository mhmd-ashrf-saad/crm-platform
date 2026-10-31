<?php

use Database\Seeders\Demo\DemoIdentity;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
    $passwords = [];

    foreach (file(demoCredentialsPath(), FILE_IGNORE_NEW_LINES) as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$email, $password] = explode(': ', $line, 2);

        $passwords[$email] = $password;
    }

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
