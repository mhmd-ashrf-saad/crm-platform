<!-- Context: operations/demo-dataset.md | Priority: high | Version: 1.3 | Updated: 2026-11-09 -->

# Demo dataset

`Database\Seeders\Demo\DemoSeeder` wipes and rebuilds a full Arabic-first demo CRM
for client demos and screenshots. It is **destructive on CRM content**, not on the
operator's login. Run it only on a demo instance.

```bash
php artisan db:seed --class='Database\Seeders\Demo\DemoSeeder' --force
```

Plain `php artisan db:seed` does not seed the demo — `Database\Seeders\DatabaseSeeder`
calls only the installer's seeder.

## The five things an agent must know

1. **The wipe is wholesale, not row-scoped.** Leads, contacts, organizations,
   products, quotes, activities, tags, web forms, campaigns, emails and their pivots
   are emptied *entirely* — including rows a colleague created. Only `users`
   (reserved `@sijilna.demo` domain), `attribute_values.entity_type`, and two
   name-matched tables are scoped.
2. **The operator's users survive, byte-identical.** Every user not on the reserved
   domain is snapshotted before the run and asserted unchanged afterwards, password
   hash included; the LIKE pattern and `DemoIdentity::isDemoEmail()` are cross-checked
   so they cannot disagree.
3. **Installed reference data is read, never created.** Roles, pipelines, stages,
   sources, types, attributes and the installer's two email templates survive. A fresh
   install with only Administrator gives all three demo users Administrator, and the
   seeder warns; no role at all throws and rolls the wipe back inside its transaction.
4. **Passwords are per run, in a clear-text file bound to one database.** Default
   `/var/tmp/pi-scratch/demo-seed/credentials-<database>.txt`, named from the connection's
   database via `DemoCredentials::provenance()`. A database name that is already
   filename-safe keeps that exact name (`krayin` → `credentials-krayin.txt`); a name
   slugging would lose gets the slug plus the first eight hex of `sha1(raw name)`
   (`krayin$test` → `credentials-krayin-test-<digest>.txt`), so colliding names stay two
   files. `DEMO_SEED_CREDENTIALS_PATH` overrides the whole path and skips that naming. Mode
   `0600`, never committed, deleted after the demo. The header names the connection and
   database; the old global `credentials.txt` (`LEGACY_PATH`) is named by the seeder in a
   warning and never read, trusted or deleted. The seeder also prints the passwords to the
   console.
5. **A rerun is idempotent** — same record set, same links, same quote totals
   (wipe and rebuild share one transaction). Resetting the instance first means
   `make db-reset` with `CONFIRM=<database name>` plus `KRAYIN_ADMIN_EMAIL` and
   `KRAYIN_ADMIN_PASSWORD`; it refuses on a non-`local` `APP_ENV`, a non-loopback
   host, and while `bootstrap/cache/config.php` exists, and it destroys the named
   database with no undo.

**The seeder declares one dashboard setting, insert-only.** The dataset is backdated up to
75 days while the product's default reporting window is one month
(`AbstractReporting::getDefaultStartDate()` falls back to `now()->subMonth()`), so a
freshly seeded instance opened on an empty dashboard. `DemoSeeder::seedDashboardRange()`
now writes `general.settings.dashboard.date_range = 3_months` to `core_config` inside the
seeding transaction, and **only when no such row exists** — an operator's saved range
survives a re-seed, and the product-wide default stays one month for real clients (a
non-demo instance runs no part of this seeder). Presets still override it in both
directions; the run reports what it found or did as `Dashboard default range: …`.

**The credentials file is verified before it exists.** `DemoSeeder::run()` reads each hash
back from `users` and `DemoCredentials::verify()` `Hash::check`s every generated password
against its row — after the transaction commits and before `write()` — so a mismatch
throws naming the account and never the password, and a run that cannot produce a working
login leaves no file. Bead `crm-4ok` (one global file, overwritten by a run against
another database, so every demo login failed) is **fixed in the working tree** by that
plus the database-bound name.

**Do not reach for "reseed" to repair a login.** Read the file named for the database you
are pointed at and check its `# Bound to …` header. If it is missing or does not
authenticate, the two options have different costs: resetting the three demo users'
passwords touches no CRM content but does not rewrite the file, while a reseed rewrites it
and **destroys every CRM content table on that instance**. The consequence of each, and the
verification order, are in [the runbook §4](../../../docs/ops/demo-dataset.md).

## Related

- Operator runbook, counts and the full reset procedure:
  [../../../docs/ops/demo-dataset.md](../../../docs/ops/demo-dataset.md)
- Reset guards and the live/test split: [database.md](database.md),
  `scripts/README.md`
- Install and reinstall: [provisioning.md](provisioning.md)
