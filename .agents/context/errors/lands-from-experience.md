<!-- Context: errors/lands-from-experience.md | Priority: high | Version: 1.0 | Updated: 2026-10-16 -->

# Lands From Experience

Eight failures that actually happened on this checkout. Each entry gives the
symptom, the cause and the check that catches it before it costs another pass.
None of these is hypothetical.

## 1. `public/storage` dangles after a copy

- **Symptom:** uploaded files 404 or the admin UI shows broken images after the
  checkout is copied, moved to another path, or restored from an archive. The
  symlink exists and looks correct.
- **Cause:** `public/storage` was created with an **absolute** target
  (e.g. `/old/path/storage/app/public`). It is gitignored, so the broken link
  is invisible to `git status` and travels with any archive of the directory.
- **Check:** `readlink public/storage` must print the relative
  `../storage/app/public`; `readlink -f public/storage` must resolve to the
  checkout's own `storage/app/public`, and `test -e public/storage` must pass.
  Recreate with a relative link if not.

## 2. A credential hardcoded in a committable script

- **Symptom:** an admin password (or DB password) visible in a tracked file.
  Nothing breaks at runtime; the damage is the leak.
- **Cause:** a setup script was written with a literal default password so a
  bare re-run "just works". A default in a committable script is a credential
  in the repository, and it also silently resets a live account.
- **Check:** `git grep -nE "(password|secret)\\s*=\\s*['\\"]" scripts/` returns
  no literal; the admin script reads `KRAYIN_ADMIN_EMAIL` /
  `KRAYIN_ADMIN_PASSWORD` from the environment and exits 1 when either is
  missing.

## 3. `DB_DATABASE` disagrees between `.env.example` and the container

- **Symptom:** the app connects but the database is empty, or provisioning
  succeeds while artisan writes to a database the container never created.
- **Cause:** `.env.example` ships `DB_DATABASE=laravel-crm` while the container
  was created with `MYSQL_DATABASE=krayin`. A set-if-unset `.env` writer would
  keep the placeholder and disagree with the container on name, user and port.
  The provisioning script now **upserts** the whole connection block on the
  create path.
- **Check:** compare the two directly —
  `docker inspect crm-platform-mysql --format '{{range .Config.Env}}{{println .}}{{end}}' | grep MYSQL_DATABASE`
  against `grep '^DB_DATABASE=' .env`. They must match (`krayin`).

## 4. The interactive installer hangs forever

- **Symptom:** a scripted `php artisan krayin-crm:install` never returns. It
  consumes the whole run budget with no output and no error.
- **Cause:** the installer's `Laravel\Prompts` TUI pre-fills its own defaults
  and never receives EOF, so it blocks instead of accepting piped input. There
  is no "answer everything" flag.
- **Check:** never drive it interactively; provisioning uses
  `php artisan krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction`
  followed by `php scripts/create-admin.php`. If a run hangs on that command,
  this is why.

## 5. The unanchored `pkill` killed its own shell

- **Symptom:** a command that was supposed to stop the dev server also kills
  the shell or script that ran it — often mid-run, with no error.
- **Cause:** `pkill -f 'php artisan serve'` re-reads the full command line of
  every process, so it also matches whatever shell or script merely *mentions*
  that text, including the caller.
- **Check:** `awk '!/^[[:space:]]*#/ && /pkill/' Makefile` returns nothing — the
  word still appears in the comments that warn against it, so a bare
  `grep -n pkill Makefile` matches and proves nothing. Server processes are
  identified by the socket they own (`ss -ltnpH`, matched on the port whatever
  bind address it is on: `127.0.0.1`, `0.0.0.0`, `::`, an interface address)
  plus `/proc/<pid>/cwd` being inside this checkout. Use `make stop`.

## 6. The tracked cache file rewrites on every test run

- **Symptom:** a clean checkout is dirty immediately after `make test`, with
  `.phpunit.cache/test-results` modified. It looks like a test leaked state.
- **Cause:** the file is **tracked by git** and Pest rewrites it every run
  (`cacheDirectory=".phpunit.cache"` in `phpunit.xml`). Expected, not a bug.
- **Check:** `git ls-files | grep phpunit.cache` confirms it is tracked. Do not
  commit it with unrelated changes; do not "fix" it by deleting it.

## 7. The read-only-store illusion

- **Symptom:** every `bd` mutation fails with
  `embeddeddolt: store is read-only`, which reads like a disk or permissions
  fault and sends you debugging the filesystem.
- **Cause:** a bare `bd init` detected the `upstream` remote, reset
  `beads.role` to `contributor`, re-added a read-only planning repo and
  appended to `.gitignore`. The store is fine; the role is wrong.
- **Check:** `git config --local --get beads.role` must print `maintainer`, and
  `grep -nE '^[[:space:]]*repos:' .beads/config.yaml` must find nothing (all
  examples are commented). If re-initialising, use the flag list in
  [../guides/agent-workflow-rules.md](../guides/agent-workflow-rules.md) — never
  a bare `bd init`.

## 8. A cached config makes the guard and the wipe disagree

- **Symptom:** `make db-reset` prints the database it is about to destroy and
  then wipes a different one. Nothing errors, and the guards all passed.
- **Cause:** `bootstrap/cache/config.php` is gitignored and appears whenever
  anyone runs `php artisan config:cache` or `php artisan optimize`. When it
  exists, artisan reads the database name from that cache while `db-reset`'s
  guards read the environment and `.env`. Demonstrated: with a cache baked to
  database A, `DB_DATABASE=B CONFIRM=B make db-reset` printed
  `DESTROYING database 'B'` and then wiped, migrated and seeded A.
- **Check:** `test ! -e bootstrap/cache/config.php` before any destructive or
  test-database target; `db-reset`, `test-db` and `test` all refuse while it
  exists. Clear it with `php artisan config:clear`.

## Related

- Rules derived from these failures → [../guides/agent-workflow-rules.md](../guides/agent-workflow-rules.md)
- Testing specifics → [../guides/testing.md](../guides/testing.md)
