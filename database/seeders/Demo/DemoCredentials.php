<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Str;

/**
 * The generated passwords for the demo users, and the file they are handed over in.
 *
 * Passwords are generated per run and never hardcoded: `Str::password()` draws from
 * `random_bytes`, so two runs of the seeder do not share a credential. They are written to a
 * scratch path the operator can read, and printed once by the seeder — never committed, and
 * never part of the seeded fixture data.
 */
class DemoCredentials
{
    /**
     * Where the credentials file is written unless the operator names another path.
     *
     * A path under `/var/tmp/pi-scratch` (disk) rather than `/tmp` (a small tmpfs that has already
     * been filled to the point of swapping on this machine).
     */
    public const DEFAULT_PATH = '/var/tmp/pi-scratch/demo-seed/credentials.txt';

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
     * Where the file will be written: the operator's `DEMO_SEED_CREDENTIALS_PATH`, or the default.
     */
    public static function path(): string
    {
        $path = env('DEMO_SEED_CREDENTIALS_PATH') ?: self::DEFAULT_PATH;

        return rtrim($path, '/');
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

        $lines = [
            '# Sijilna demo credentials — generated '.now()->toIso8601String().' by Database\\Seeders\\Demo\\DemoSeeder',
            '# Demo users only. Their addresses end in @'.DemoIdentity::RESERVED_EMAIL_DOMAIN.'; the wipe deletes nothing else.',
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
