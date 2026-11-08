<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The generated passwords for the demo users, and the file they are handed over in.
 *
 * Passwords are generated per run and never hardcoded: `Str::password()` draws from
 * `random_bytes`, so two runs of the seeder do not share a credential. They are written to a
 * scratch path the operator can read, and printed once by the seeder — never committed, and
 * never part of the seeded fixture data.
 *
 * The file is *bound to a database*. A single global filename cannot be: the credentials it holds
 * only authenticate against the rows of the database they were written to, so one run against a
 * throwaway database overwrote the file an operator reads for the live one, and the seed still
 * reported success (bead `crm-4ok`). The name now carries the connection's database, and the
 * header carries the connection and the database it describes.
 */
class DemoCredentials
{
    /**
     * The directory the credentials file is written to unless the operator names another path.
     *
     * A path under `/var/tmp/pi-scratch` (disk) rather than `/tmp` (a small tmpfs that has already
     * been filled to the point of swapping on this machine).
     */
    public const DEFAULT_DIRECTORY = '/var/tmp/pi-scratch/demo-seed';

    /**
     * The name this class wrote before it was bound to a database: one file, shared by every run.
     *
     * It is kept only so the seeder can tell the operator that such a file is there. It is never
     * read, never trusted and never deleted, and no database resolves to it, because every name
     * this class now writes carries the `credentials-` prefix.
     */
    public const LEGACY_PATH = self::DEFAULT_DIRECTORY.'/credentials.txt';

    /**
     * The generated passwords, keyed by the demo user's fixture key.
     *
     * @var array<string, string>
     */
    protected array $passwords = [];

    /**
     * Generate one password per demo user.
     *
     * Symbols are left out on purpose: `Str::password(20)` with symbols produces characters a
     * shell, a chat client or a spreadsheet will eat, and the operator has to paste these into a
     * login form. Twenty alphanumeric characters are still about 119 bits of entropy.
     *
     * @param  array<int, array<string, string>>  $users
     */
    public function generate(array $users): void
    {
        foreach ($users as $user) {
            $this->passwords[$user['key']] = Str::password(length: 20, symbols: false);
        }
    }

    /**
     * The password for a fixture key.
     */
    public function password(string $key): string
    {
        if (! isset($this->passwords[$key])) {
            throw new \RuntimeException("No demo password was generated for [{$key}].");
        }

        return $this->passwords[$key];
    }

    /**
     * Minimum length the seeder is willing to write, so a future edit cannot quietly weaken this.
     */
    public const MINIMUM_LENGTH = 16;

    /**
     * Fail loudly unless every generated password authenticates against the hash stored for it.
     *
     * The hashes are read back out of the database, so this checks the pair the credentials file is
     * about to describe and not the intention behind it: a password that was generated but never
     * reached its row is exactly the state that must not be reported as a successful seed.
     *
     * The message names the account and never the password.
     *
     * @param  array<int, array<string, string>>  $users
     * @param  array<string, string>  $hashes  the stored hash, keyed by the user's address
     */
    public function verify(array $users, array $hashes): void
    {
        foreach ($users as $user) {
            $email = $user['email'];
            $password = $this->password($user['key']);
            $hash = $hashes[$email] ?? null;

            if ($hash === null) {
                throw new \RuntimeException(
                    "The demo user [{$email}] has no row to verify its generated password against."
                );
            }

            if (! Hash::check($password, $hash)) {
                throw new \RuntimeException(
                    "The generated demo password for [{$email}] does not authenticate against the row just written. "
                    .'Reporting success would hand over a login that cannot work, so the seed is treated as failed.'
                );
            }
        }
    }

    /**
     * Where the file will be written: the operator's `DEMO_SEED_CREDENTIALS_PATH`, or the name of
     * the database the current connection points at, in `DEFAULT_DIRECTORY`.
     *
     * The override stays a whole path and still wins over both arguments, so an operator (or a
     * test) can put the file anywhere; everything derived from the connection is skipped in that
     * case.
     *
     * The two arguments exist so the naming can be exercised without writing where an operator
     * reads: `DEFAULT_DIRECTORY` holds the credentials file a live instance depends on, and the
     * suite must never create, overwrite or delete anything there. Passing the database name and a
     * directory a test owns runs the real derivation — `provenance()` reading the connection,
     * `filename()` turning the name into something safe — with the write landing in that directory.
     */
    public static function path(?string $database = null, ?string $directory = null): string
    {
        $override = env('DEMO_SEED_CREDENTIALS_PATH');

        if (filled($override)) {
            return rtrim((string) $override, '/');
        }

        $database ??= self::provenance()['database'];

        return rtrim($directory ?? self::DEFAULT_DIRECTORY, '/').'/credentials-'.self::filename($database).'.txt';
    }

    /**
     * The connection and database the current run is writing for, named in the file's header.
     *
     * @return array{connection: string, database: string}
     */
    public static function provenance(): array
    {
        try {
            $connection = DB::connection();

            return [
                'connection' => (string) $connection->getName(),
                'database' => (string) $connection->getDatabaseName(),
            ];
        } catch (\Throwable) {
            /**
             * No connection to ask, and no reason to refuse to write a file about it.
             */
            return ['connection' => 'unknown', 'database' => 'unknown'];
        }
    }

    /**
     * The filename stem a database name resolves to: its slug, disambiguated when slugging is lossy.
     *
     * A slug alone is not one name per database. `preg_replace()` collapses every run of disallowed
     * bytes to a single `-`, so `krayin$test` and `krayin-test` both become `krayin-test`, and any two
     * multibyte names that differ only outside `[A-Za-z0-9._-]` collapse together well — which puts
     * two databases back on one file, the defect this class exists to prevent.
     *
     * A name that slugging leaves untouched resolves to that name exactly, so a file an operator
     * already reads keeps its path: `krayin` is still `credentials-krayin.txt`. Only a name slugging
     * would have lost carries a short digest of the raw name beside the slug.
     */
    protected static function filename(string $database): string
    {
        $slug = self::slug($database);

        return $slug === $database ? $slug : $slug.'-'.substr(sha1($database), 0, 8);
    }

    /**
     * A database name reduced to something safe to put in a filename.
     *
     * Lossy on its own: `filename()` is what names a file.
     */
    protected static function slug(string $database): string
    {
        $slug = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $database), '.-');

        return $slug === '' ? 'unknown' : $slug;
    }

    /**
     * Write the credentials file and return its absolute path.
     *
     * `file_put_contents()` + `rename()` on the same filesystem is the replacement, so a reader
     * never sees half a file and an earlier run's file is never left pointing at users this run
     * deleted. The file is created with `0600`: it holds every demo password in clear text.
     *
     * @param  array<int, array<string, string>>  $users
     */
    public function write(array $users, string $path): string
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create the credentials directory [{$directory}].");
        }

        $provenance = self::provenance();

        $lines = [
            '# Sijilna demo credentials — generated '.now()->toIso8601String().' by Database\\Seeders\\Demo\\DemoSeeder',
            '# Demo users only. Their addresses end in @'.DemoIdentity::RESERVED_EMAIL_DOMAIN.'; the wipe deletes nothing else.',
            '# Bound to connection ['.$provenance['connection'].'], database ['.$provenance['database'].']: these',
            '# passwords authenticate against that database only. A run against another database writes another file.',
            '# This file is not committed and must be deleted once the demo is over.',
            '',
        ];

        foreach ($users as $user) {
            $password = $this->password($user['key']);

            if (strlen($password) < self::MINIMUM_LENGTH) {
                throw new \RuntimeException('A generated demo password is shorter than the seeder accepts.');
            }

            $lines[] = $user['email'].': '.$password;
        }

        $lines[] = '';

        $temporary = $path.'.tmp';

        if (@file_put_contents($temporary, implode(PHP_EOL, $lines), LOCK_EX) === false) {
            throw new \RuntimeException("Unable to write the credentials file [{$temporary}].");
        }

        @chmod($temporary, 0600);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new \RuntimeException("Unable to move the credentials file into place at [{$path}].");
        }

        return $path;
    }
}
