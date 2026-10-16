<!-- Context: operations/database.md | Priority: critical | Version: 1.0 | Updated: 2026-10-16 -->

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

`mysqldump` as the application user prints an "Access denied … PROCESS
privilege … tablespaces" line and **still exits 0**; the dump is complete. Add
`--no-tablespaces` to silence it. Verify a backup by restoring it, never by
trusting the file size.

```bash
docker exec -e MYSQL_PWD="$DB_PASSWORD" crm-platform-mysql \
  mysqldump -u"$DB_USERNAME" --no-tablespaces --databases krayin > krayin-$(date +%Y%m%dT%H%M%S).sql
```

Keep `APP_KEY` with the dump: restoring data without the matching key leaves
encrypted columns unreadable. **A Docker volume is not a backup** — it lives on
the same disk as the container and is deleted whole by a prune. Copy dumps off
the machine.

## Related

- Container, volume, port → [environment-and-deployment.md](environment-and-deployment.md)
- Test database → [../guides/testing.md](../guides/testing.md)
