<?php

/**
 * Create or update the Krayin administrator account, unattended.
 *
 * This reproduces the admin step of `php artisan krayin-crm:install` -- see
 * packages/Webkul/Installer/src/Console/Commands/Installer.php::createAdminCredentials() -- but
 * without the interactive `Laravel\Prompts` questions that step is wrapped in, so it can run from a
 * provisioning script. It writes the same durable state the command writes:
 *
 *   - the `users` row (role_id = 1, status = 1), hashed the same way (bcrypt, cost 10),
 *   - the `storage/installed` marker file,
 *   - the `core_config` row `installation.completed` = 1,
 *   - and clears the `storage/installing` in-progress marker.
 *
 * It deliberately does NOT dispatch the `krayin.installed` event. That event's only listener,
 * Webkul\Installer\Listeners\Installer, POSTs the admin's name, email and domain to
 * updates.krayincrm.com -- pure telemetry with no effect on the install. A provisioning step should
 * not re-report a client's admin email to a third party.
 *
 * Idempotent: the row is UPDATED when it already exists, so running this twice leaves one user, not
 * two. When the seeded `admin@example.com` row (or any row with id 1) is present it is reused
 * rather than duplicated.
 *
 * Usage:
 *   KRAYIN_ADMIN_EMAIL=ops@client.test KRAYIN_ADMIN_PASSWORD='<chosen-password>' \
 *     php scripts/create-admin.php
 *
 * Required environment -- there is deliberately no default for either, so that no credential
 * literal can live in this repository and a bare re-run cannot reset an existing admin's password:
 *   KRAYIN_ADMIN_EMAIL     the administrator's login email
 *   KRAYIN_ADMIN_PASSWORD  the administrator's password, at least 8 characters (the installer's
 *                          own minimum)
 *
 * Optional:
 *   KRAYIN_ADMIN_NAME      display name                                 [Mohamed Ashraf]
 *
 * Both required variables are validated before the application boots and before the `users` table
 * is read: with either one missing this script names both of them, exits 1 and writes nothing.
 * scripts/provision-dev.sh runs this script only on a fresh install; scripts/README.md documents
 * the deliberate way to change an admin on an existing instance.
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Webkul\Installer\Helpers\DatabaseManager;

// Project root, derived from this script's own location (`scripts/..`) rather than a hardcoded
// absolute path, so the script keeps working after the checkout is moved and on a client machine.
$basePath = dirname(__DIR__);

$fail = static function (string $message): never {
    fwrite(STDERR, "create-admin: {$message}\n");
    exit(1);
};

/**
 * Printed whenever a required credential is missing. It always names both variables, so a caller
 * never has to read this file to discover what the script needs.
 */
$usage = <<<'TXT'
both KRAYIN_ADMIN_EMAIL and KRAYIN_ADMIN_PASSWORD must be set. This repository holds no default
credential for either, and no default is written into the checkout.

Required:
  KRAYIN_ADMIN_EMAIL     the administrator's login email
  KRAYIN_ADMIN_PASSWORD  the administrator's password (at least 8 characters)

Optional:
  KRAYIN_ADMIN_NAME      display name [Mohamed Ashraf]

Example:
  KRAYIN_ADMIN_EMAIL='admin@client.test' KRAYIN_ADMIN_PASSWORD='<chosen-password>' \
    php scripts/create-admin.php
TXT;

$name = getenv('KRAYIN_ADMIN_NAME') ?: 'Mohamed Ashraf';
$email = getenv('KRAYIN_ADMIN_EMAIL');
$password = getenv('KRAYIN_ADMIN_PASSWORD');

// Validate everything up front, before bootstrap and before any read or write of the database, so
// that a misconfigured run cannot change any state at all.
if ($email === false || trim($email) === '' || $password === false || $password === '') {
    $fail($usage);
}

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fail("KRAYIN_ADMIN_EMAIL (\"{$email}\") is not a valid email address.");
}

if (strlen($password) < 8) {
    $fail('KRAYIN_ADMIN_PASSWORD must be at least 8 characters, as the installer requires.');
}

if (! is_file($basePath.'/vendor/autoload.php')) {
    $fail('vendor/ is missing. Run `composer install` first (or scripts/provision-dev.sh).');
}

require $basePath.'/vendor/autoload.php';

/**
 * Laravel 12's bootstrap/app.php returns the configured Application instance, so requiring it is
 * enough; the console kernel then has to be bootstrapped before facades and helpers such as
 * storage_path() work.
 */
$app = require $basePath.'/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

try {
    $existing = DB::table('users')->where('email', $email)->first()
        ?? DB::table('users')->where('id', 1)->first();
} catch (Throwable $e) {
    $fail('could not read the `users` table ('.$e->getMessage().'). Run the installer first.');
}

$attributes = [
    'name' => $name,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]),
    'role_id' => 1,
    'status' => 1,
    'updated_at' => now(),
];

if ($existing) {
    DB::table('users')->where('id', $existing->id)->update($attributes);
    $action = "updated user id {$existing->id}";
} else {
    DB::table('users')->insert($attributes + ['created_at' => now()]);
    $action = 'created user';
}

File::put(storage_path('installed'), 'Krayin is successfully installed');

app(DatabaseManager::class)->markInstallationCompleted();
app(DatabaseManager::class)->clearInstallationInProgress();

printf(
    "create-admin: %s -- %s <%s>, role_id=1, status=1 (password length %d, hashed bcrypt cost 10).\n",
    $action,
    $name,
    $email,
    strlen($password),
);

printf("create-admin: %d row(s) in `users`; storage/installed and installation.completed set.\n", DB::table('users')->count());
