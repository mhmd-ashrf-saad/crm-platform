<?php

namespace Database\Seeders\Demo;

/**
 * The boundary that decides which rows belong to the demo dataset.
 *
 * Every demo user's address ends in the reserved domain, and that domain is the only thing the
 * wipe is allowed to delete from the `users` table. Keeping the rule in one tiny class means the
 * seeder, the wiper and the credential writer all agree on it without any of them depending on
 * another, and a reader can check the boundary in one place instead of three.
 *
 * `sijilna.demo` is not a registrable domain: it is deliberately a reserved TLD so a demo address
 * can never receive real mail. Contact addresses in the fixture data use `example.com`, which is
 * reserved for the same reason.
 */
final class DemoIdentity
{
    /**
     * The reserved domain every demo user address ends with.
     */
    public const RESERVED_EMAIL_DOMAIN = 'sijilna.demo';

    /**
     * SQL LIKE pattern matching exactly the addresses on the reserved domain.
     */
    public const RESERVED_EMAIL_PATTERN = '%@'.self::RESERVED_EMAIL_DOMAIN;

    /**
     * Whether an address belongs to a demo user.
     */
    public static function isDemoEmail(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        return str_ends_with(strtolower($email), '@'.self::RESERVED_EMAIL_DOMAIN);
    }
}
