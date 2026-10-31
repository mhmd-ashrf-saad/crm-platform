<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * Wipes and rebuilds the demo dataset: a complete, Arabic-first, Egyptian-market CRM that fills
 * every user-facing module so the product can be shown to a client and screenshotted.
 *
 *     php artisan db:seed --class='Database\Seeders\Demo\DemoSeeder' --force
 *
 * What this seeder guarantees, and why each guarantee exists:
 *
 * - **No run-time data generation.** The dataset is static fixture data (DemoFixtures). The
 *   random-data generator is a development dependency and the production image is built with
 *   `composer install --no-dev`, so depending on it would be a fatal error on the live instance.
 * - **The operator's account survives.** Only users whose address ends in the reserved demo domain
 *   are ever deleted, and every other user row is asserted byte-identical after the run. The
 *   operator logs in with one of those rows.
 * - **Installed reference data survives.** Pipelines, stages, sources, types, roles, attributes and
 *   the installer's email templates are read, never recreated and never deleted.
 * - **Re-running is safe.** Same record set, same links, every time; the wipe happens first, inside
 *   the same transaction as the rebuild.
 * - **Dates are relative to today,** so the dashboard date-range report, "this month" and the
 *   activity calendar are never empty however long after this file was written it runs.
 */
class DemoSeeder extends Seeder
{
    /**
     * The reserved domain that marks a user row as owned by this seeder.
     */
    public const RESERVED_EMAIL_DOMAIN = DemoIdentity::RESERVED_EMAIL_DOMAIN;

    /**
     * SQL LIKE pattern matching exactly the addresses on the reserved domain.
     */
    public const RESERVED_EMAIL_PATTERN = DemoIdentity::RESERVED_EMAIL_PATTERN;

    /**
     * Rows the seeder writes, in the order the report prints them.
     */
    public const REPORTED_TABLES = [
        'users',
        'organizations',
        'persons',
        'products',
        'leads',
        'lead_products',
        'quotes',
        'quote_items',
        'activities',
        'lead_activities',
        'person_activities',
        'tags',
        'lead_tags',
        'person_tags',
        'product_tags',
        'email_templates',
        'marketing_events',
        'marketing_campaigns',
        'emails',
        'email_tags',
        'web_forms',
        'web_form_attributes',
        'attribute_values',
    ];

    /**
     * Create a new seeder instance.
     */
    public function __construct(
        protected DemoWiper $wiper,
        protected DemoCredentials $credentials,
    ) {}

    /**
     * Run the demo dataset.
     */
    public function run(): void
    {
        /**
         * `db:seed` runs wrapped in `Model::unguarded()`, which would let a key that is not a
         * column on the entity -- `entity_type`, which the attribute-value repository needs --
         * reach the entity's own insert and fail with "Unknown column". The admin controllers run
         * with the normal guard in place, so the seeder restores it here and behaves like a save
         * through the UI. `entity_type` still persists where it belongs: it is explicitly fillable
         * on `AttributeValue`.
         */
        Model::reguard();

        $fixtureUsers = $this->prepareFixtureUsers();

        $this->credentials->generate($fixtureUsers);

        $protectedUsers = $this->protectedUsers();

        $counts = DB::transaction(function () use ($fixtureUsers) {
            $this->wiper->wipe();

            $context = $this->seedUsers($fixtureUsers);

            $context = app(DemoSalesSeeder::class)->seed($context);

            $this->seedMail($context);

            return $this->countRows();
        });

        $this->assertProtectedUsersUntouched($protectedUsers);

        $credentialsPath = $this->credentials->write($fixtureUsers, DemoCredentials::path());

        $this->report($fixtureUsers, $counts, $credentialsPath);
    }

    /**
     * The fixture users with their full addresses filled in from the reserved domain.
     *
     * @return array<int, array<string, string>>
     */
    protected function prepareFixtureUsers(): array
    {
        return array_map(function (array $user) {
            $user['email'] = $user['email'].'@'.self::RESERVED_EMAIL_DOMAIN;

            return $user;
        }, DemoFixtures::users());
    }

    /**
     * A byte-for-byte copy of every user row this seeder must not touch.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function protectedUsers(): array
    {
        $rows = DB::table('users')
            ->where('email', 'not like', self::RESERVED_EMAIL_PATTERN)
            ->orderBy('id')
            ->get();

        $snapshot = [];

        foreach ($rows as $row) {
            /**
             * The LIKE pattern and the PHP rule must agree. If they ever stop agreeing, a demo
             * address could be treated as the operator's own and survive -- or worse, the reverse.
             */
            if (DemoIdentity::isDemoEmail($row->email)) {
                throw new \RuntimeException(
                    "The reserved domain rule is inconsistent: user [{$row->id}] on [{$row->email}] was read as protected."
                );
            }

            $snapshot[(string) $row->id] = (array) $row;
        }

        return $snapshot;
    }

    /**
     * Fail loudly if a single column of a single pre-existing user row changed or disappeared.
     *
     * The demo cannot be demoed if the operator cannot log in, so this is checked after every run
     * rather than trusted to the wipe's own scoping.
     *
     * @param  array<string, array<string, mixed>>  $before
     */
    protected function assertProtectedUsersUntouched(array $before): void
    {
        $after = $this->protectedUsers();

        if (array_keys($before) !== array_keys($after)) {
            throw new \RuntimeException(
                'The demo seeder removed or added a user outside the reserved [@'.self::RESERVED_EMAIL_DOMAIN.'] domain. '
                .'Existing ids before: ['.implode(', ', array_keys($before)).'], after: ['.implode(', ', array_keys($after)).'].'
            );
        }

        foreach ($before as $id => $row) {
            foreach ($row as $column => $value) {
                if (! array_key_exists($column, $after[$id])) {
                    throw new \RuntimeException("User [{$id}] lost the column [{$column}] during the demo seed.");
                }

                if ((string) $after[$id][$column] !== (string) $value) {
                    throw new \RuntimeException("User [{$id}] had [{$column}] changed by the demo seed.");
                }
            }
        }

        foreach ($before as $id => $row) {
            if (($row['password'] ?? null) === null || ! str_starts_with((string) $row['password'], '$2y$')) {
                throw new \RuntimeException("User [{$id}] did not hold a bcrypt hash before the seed, which is unexpected.");
            }
        }
    }

    /**
     * Create the three demo users and hand back the context the rest of the seed needs.
     *
     * Roles are read, never created: a fresh installation ships exactly one role (Administrator),
     * so all three demo users share it. If the operator has created sales or support roles, the
     * best-named match is used for each user instead.
     *
     * @param  array<int, array<string, string>>  $fixtureUsers
     * @return array<string, mixed>
     */
    protected function seedUsers(array $fixtureUsers): array
    {
        $roles = Role::query()->orderBy('id')->get();

        if ($roles->isEmpty()) {
            throw new \RuntimeException('No role exists in this installation; run the installer before the demo seeder.');
        }

        $administrator = $roles->firstWhere('permission_type', 'all') ?? $roles->first();

        $creator = DB::table('users')
            ->where('email', 'not like', self::RESERVED_EMAIL_PATTERN)
            ->orderBy('id')
            ->value('id');

        $users = [];

        foreach ($fixtureUsers as $fixture) {
            $user = User::query()->create([
                'name' => $fixture['name'],
                'email' => $fixture['email'],
                'password' => Hash::make($this->credentials->password($fixture['key'])),
                'role_id' => $this->roleFor($fixture['key'], $roles, $administrator)->id,
                'status' => 1,
                'view_permission' => 'global',
                'created_by' => $creator,
            ]);

            $users[$fixture['key']] = $user;
        }

        /**
         * The activity log stamps its entries with the acting user. Without this every seeded
         * "Created" entry would read as an anonymous system action.
         */
        $this->actingAs($users['manager']);

        if ($roles->count() === 1) {
            $this->command?->warn(
                'Only the ['.$administrator->name.'] role exists in this installation, so all three demo users share it. '
                .'The sales and support roles were not invented: roles are installed reference data.'
            );
        }

        return [
            'users' => $users,
            'roles' => $roles,
        ];
    }

    /**
     * Pick the role a demo user should hold, from roles that already exist.
     *
     * @param  Collection<int, Role>  $roles
     */
    protected function roleFor(string $key, $roles, Role $fallback): Role
    {
        $preferred = [
            'manager' => ['Sales Manager', 'مدير المبيعات', 'Manager'],
            'agent' => ['Sales Agent', 'مندوب مبيعات', 'Sales'],
            'support' => ['Support Agent', 'مسؤول دعم العملاء', 'Support'],
        ];

        foreach ($preferred[$key] ?? [] as $name) {
            $match = $roles->first(fn (Role $role) => mb_strtolower($role->name) === mb_strtolower($name));

            if ($match) {
                return $match;
            }
        }

        return $fallback;
    }

    /**
     * Set the acting user for the model events the repositories fire.
     */
    protected function actingAs(User $user): void
    {
        auth()->guard('user')->setUser($user);
    }

    /**
     * Marketing templates, events, campaigns and the seeded mailbox.
     *
     * @param  array<string, mixed>  $context
     */
    protected function seedMail(array $context): void
    {
        app(DemoMailSeeder::class)->seed($context);
    }

    /**
     * Row counts for the report.
     *
     * @return array<string, int>
     */
    protected function countRows(): array
    {
        $counts = [];

        foreach (self::REPORTED_TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * Print what was seeded, where the passwords went and how to use them.
     *
     * @param  array<int, array<string, string>>  $fixtureUsers
     * @param  array<string, int>  $counts
     */
    protected function report(array $fixtureUsers, array $counts, string $credentialsPath): void
    {
        $this->command?->newLine();
        $this->command?->info('Sijilna demo dataset seeded.');
        $this->command?->newLine();

        foreach ($counts as $table => $count) {
            $this->command?->line(sprintf('  %-24s %5d', $table, $count));
        }

        $this->command?->newLine();
        $this->command?->line('Demo logins (passwords are in the credentials file, never in the repository):');

        foreach ($fixtureUsers as $fixture) {
            $this->command?->line(sprintf('  %-32s %s', $fixture['email'], $this->credentials->password($fixture['key'])));
        }

        $this->command?->newLine();
        $this->command?->line('Credentials file: '.$credentialsPath);
        $this->command?->line('File mode 0600. It is not committed: delete it when the demo is over.');
        $this->command?->newLine();
    }
}
