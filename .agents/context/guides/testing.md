<!-- Context: guides/testing.md | Priority: high | Version: 1.0 | Updated: 2026-10-16 -->

# Testing

Pest 3 (`^3.0`) on PHPUnit, run against a dedicated database, never the live
one.

## How to run

```bash
make test      # migrate + seed krayin_test, then php artisan test --compact
make test-db   # -> bash scripts/prepare-test-db.sh (idempotent)
bash scripts/prepare-test-db.sh --check   # read-only: resolved db/host/port/user + action
```

`make test-db` and CI call the **same** script, `scripts/prepare-test-db.sh`. It reads
`krayin_test` from `phpunit.xml` (the pin, not the command line), resolves the connection from the
caller's environment then `.env`, creates the database and grants the `.env` user, then migrates and
seeds it. Because both gates run one path, adding a database name to `phpunit.xml` without
provisioning it is the exact failure mode that turned the CI Pest job red once — do not repeat it.

`make test` refuses to start when the test database is missing, when
`phpunit.xml` pins the live database name or pins nothing, when the container is
stopped, or when `bootstrap/cache/config.php` exists (a cached config beats both
`.env` and `phpunit.xml`; clear it with `php artisan config:clear`). `make
test-db` refuses for the same cached-config reason, before it creates or migrates
anything — otherwise it would create, migrate and seed whatever database the
cache names while its guards read `phpunit.xml`.

Current result on this machine (2026-10-16): `make test` exits **0**,
`207 deprecated, 1 passed (412 assertions)`. "Deprecated" is Pest's marker for
tests that trigger framework deprecation notices; it is not a failure. Exit
code is what matters.

## The double-env pin

`phpunit.xml` pins the connection to `krayin_test` and **each key appears
twice**:

```xml
<server name="DB_DATABASE" value="krayin_test" force="true" />
<env    name="DB_DATABASE" value="krayin_test" force="true" />
```

`<env>` sets the process environment and `$_ENV`; `<server>` sets `$_SERVER`.
Laravel's dotenv repository reads `$_SERVER` first, so with `<env>` alone an
inherited `DB_DATABASE=krayin` still won and `RefreshDatabase` would have run
`migrate:fresh` against the **live** database. `force="true"` stops PHPUnit
deferring to an already-set variable, so the pin is not overridable from the
shell — change it by editing `phpunit.xml`. There is deliberately no
`DB_PASSWORD` or `DB_USERNAME` in the pin; those still come from `.env`.

A second checkout must edit its **own** `phpunit.xml`, or its tests run against
the first install's test database.

## Creating the test database

`scripts/prepare-test-db.sh` is the single provisioning path. It needs a privileged MySQL user:
as `root` with `DB_ROOT_PASSWORD` from `.env` on this machine, or as the application user itself
where that user is root (the CI service container sets `DB_USERNAME=root`/`DB_PASSWORD=root` and a
matching `DB_ROOT_PASSWORD`). Creation runs over PDO, which behaves the same on a CI runner and
locally; only if every PDO attempt fails does it fall back to `docker exec` into the MySQL
container. No host `mysql` client is required, and when no route works the script exits non-zero
naming what is missing.

The suite uses `DatabaseTransactions` against a migrated, seeded baseline — the schema and seed data
a fresh install has, without its administrator row, which is deliberately a smaller baseline than
`make db-reset` now produces — which is why both `make test-db` and `make test` migrate and seed
before running.

Bypassing the Makefile with a bare `php artisan test` is safe but not loud:
Laravel's `migrate --force` creates a missing MySQL database itself, so the
suite silently creates `krayin_test` rather than falling back to `krayin`.

## The tracked cache file

`.phpunit.cache/test-results` is **tracked by git** and rewritten by every test
run (Pest's `cacheDirectory` setting). A clean `make test` therefore always
leaves the working tree dirty. Expect it, do not "fix" it, and do not include it
in a commit. See [../errors/lands-from-experience.md](../errors/lands-from-experience.md).

## Related

- Live vs test database → [../operations/database.md](../operations/database.md)
- Pest conventions → `.github/skills/pest-testing/SKILL.md`
