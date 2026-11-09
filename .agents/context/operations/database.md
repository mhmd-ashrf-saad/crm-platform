<!-- Context: operations/database.md | Priority: critical | Version: 1.1 | Updated: 2026-11-09 -->

# Database

MySQL 8.4 in container `crm-platform-mysql`, data in the named volume
`crm-platform-mysql-data`. Live database and test database are **separate
databases on the same server**.

## The live/test split

| Database | Name | Used by | Tables |
| -------- | ---- | ------- | ------ |
| Live (development) | `krayin` (`.env` `DB_DATABASE`) | the running app | 61 |
| Test | `krayin_test` (pinned in `phpunit.xml`) | `make test`, Pest | 61 |

The test name is pinned in `phpunit.xml` and is the single source of truth;
`make` reads it from there. Never let a test run against `krayin` — the double
env trap that once allowed it is documented in
[../guides/testing.md](../guides/testing.md).

## Where schema lives

- Root migrations: `database/migrations/` (framework tables: jobs, failed jobs,
  personal access tokens).
- Module migrations: `packages/Webkul/<Module>/src/Database/Migrations/`, one
  directory per module (Lead, Contact, User, Core, Installer, …).
- Seeders: the `Installer` package runs them; `make test-db` and `make test`
  migrate and seed the test database so it matches a fresh install. The seeders
  are idempotent.

All schema changes go through migrations — never edit the database directly.

## Reset

`make db-reset` is the only sanctioned destructive path. Its confirmation must
name the database it destroys — `make db-reset CONFIRM=krayin` — and anything
else is refused, including the old generic `CONFIRM=reset`. A token that names
its target cannot be typed out of habit while testing a guard, which is exactly
how the development database was wiped once. The refusal prints the database it
would have destroyed and the exact token to type instead, and it refuses unless
it can prove the target is the local development database:

- the resolved name is non-empty
- `KRAYIN_ADMIN_EMAIL` and `KRAYIN_ADMIN_PASSWORD` are both set; the refusal
  names the missing variable, and the check runs before anything is destroyed
  (a permitted reset recreates the administrator, so an operator with no
  credentials has no database they should fire this target at)
- `APP_ENV=local` in `.env`
- the resolved database is not the test database and not a MySQL system database
- the resolved host is loopback (`127.0.0.1`, `localhost`, `::1`)
- the resolved connection is `mysql` or `mariadb`
- the container is running
- `bootstrap/cache/config.php` does not exist: when it does, artisan reads the
  database name from the cache while the guards read the environment and `.env`,
  so the printed target and the wiped database can differ. Clear it with
  `php artisan config:clear`.

**`APP_ENV` is read from `.env` only, on purpose.** Setting it in the caller's
environment does not make the guard refuse — a live instance whose `.env` says
`local` is still wiped. That is exactly why a guard must never be tested against
`krayin`: test it against a scratch database, or the test destroys real data.

It resolves `DB_DATABASE`, `DB_HOST`, `DB_PORT` and `DB_CONNECTION` once, inside
its own recipe, at run time — the caller's environment wins, `.env` is the
fallback — and hands exactly those values to `artisan`, so the guard and the
wipe cannot disagree about which database is at stake. That also makes a scratch
target safe:
`KRAYIN_ADMIN_EMAIL=... KRAYIN_ADMIN_PASSWORD=... DB_DATABASE=krayin_scratch
CONFIRM=krayin_scratch make db-reset` destroys only that database. It wipes,
migrates, re-seeds and then runs `scripts/create-admin.php`, so every permitted
reset ends fully installed: one administrator row for the supplied email,
`storage/installed`, and `core_config installation.completed=1` — never a
half-installed instance. Equivalent route with a new `APP_KEY`:
`bash scripts/provision-dev.sh --reinstall`.

## Backup and restore

**`make backup` is the sanctioned dump; `make backup-verify` checks it; `make
backup-rehearse` proves it restores.** The three targets wrap
`scripts/backup-db.sh` and `scripts/restore-rehearsal.sh`, and the operator
procedure (schedule, retention, install commands, the rehearsal result and its
measured timings) is `docs/ops/incident-response.md` §5-§6. What matters here:

- **Where.** `$HOME/backups/crm-platform/<db>-<UTC timestamp>.sql.gz`, plus a
  sibling `.manifest`. Outside the checkout and outside `/tmp` and
  `/var/tmp/pi-scratch/`, mode 700/600, because a dump holds live client rows and
  every user's bcrypt password hash. The script refuses a `BACKUP_DIR` inside the
  checkout unless `git check-ignore` proves it is ignored (`storage/backups/` is,
  at `.gitignore:37`).
- **Retention is explicit and enforced**: the newest dump per day for 14 days,
  per ISO week for 8 weeks and per calendar month for 6 months — the union, as
  restic does it, so about 20-24 nightly files settle. Only files matching the
  naming pattern are ever pruned; a hand-taken dump is never touched.
- **The manifest is what makes a restore provable**: sha256, both byte sizes, the
  database name, the table count, and the row counts of `leads`, `persons`,
  `organizations`, `products`, `quotes`, `quote_items`, `activities`, `emails` and
  `users`. It also records `app_key_sha256` — a fingerprint of `APP_KEY`, not the
  key — so an operator can prove they hold the key that decrypts encrypted columns.
- **Schedule**: `scripts/systemd/crm-platform-backup.timer`, a systemd **user**
  timer at 02:40 with `Persistent=true`, following this host's existing precedent
  (`homelab-pull.timer`). Nothing in this repository installs Laravel's
  `schedule:run`, so there is no second scheduling mechanism.
- **A backup nobody restored is a hypothesis**: `make backup-rehearse` restores
  into a database whose name must end in `_rehearsal`, compares the counts,
  proves an administrator can sign in with `Hash::check()` and
  `Auth::guard('user')->attempt()` against the restored rows, proves the live
  database did not change, and drops the scratch database. It was last run on
  2026-11-09 against the live `krayin`: PASS.
- **Still open**: no dump is copied off this host. The database and its backups
  share one disk, so a disk fault still takes both. And **a Docker volume is not a
  backup** — it lives on the same disk as the container and is deleted whole by a
  prune.

The raw command shapes, for a machine with no `make` (the application user lacks
`PROCESS`, so the tablespace warning is noise, not failure — silence it with
`--no-tablespaces`, and verify by restoring, never by trusting the file size):

```bash
docker exec -e MYSQL_PWD="$DB_PASSWORD" crm-platform-mysql \
  mysqldump -u"$DB_USERNAME" --no-tablespaces --databases krayin | gzip -9 > krayin-$(date -u +%Y%m%dT%H%M%SZ).sql.gz

gzip -dc <dump>.sql.gz | docker exec -i -e MYSQL_PWD="$DB_PASSWORD" crm-platform-mysql mysql -u"$DB_USERNAME"
```

Keep `APP_KEY` with the dump: restoring data without the matching key leaves
encrypted columns unreadable.

## Related

- Container, volume, port → [environment-and-deployment.md](environment-and-deployment.md)
- Backup regimen, retention and the rehearsed restore → `docs/ops/incident-response.md` §5-§6
- Test database → [../guides/testing.md](../guides/testing.md)
