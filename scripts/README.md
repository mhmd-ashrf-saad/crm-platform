# Local setup

How this checkout is provisioned, how to run it, and what must change before a client sees an
instance. Assumes Ubuntu-like Linux with PHP, Composer, Docker and Node available.

The scripts referenced below live next to this file in `scripts/`, and they derive the project root
from their own location, so the checkout works from any path on any machine.

## What `scripts/provision-dev.sh` does

One command sets up a fresh clone or a fresh machine:

```bash
bash scripts/provision-dev.sh            # http://127.0.0.1:8000
bash scripts/provision-dev.sh --port 8001
bash scripts/provision-dev.sh --check-env
```

It runs, in order:

1. **Prerequisite check** — PHP >= 8.3, the PHP extensions composer's platform requirements need
   (plus `pdo_mysql` and `intl`, which `Webkul\Core` uses for `NumberFormatter`), `composer`,
   `docker` (and a reachable Docker daemon) and `node`. It lists everything missing and exits 1
   rather than half-provisioning.
2. **`.env`** — copied from `.env.example` only when absent. Whenever this run creates `.env` **or**
   creates the container (the *create path*), the script writes the whole connection block from its
   own values: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=KRAYIN_DB_HOST_PORT`,
   `DB_DATABASE=KRAYIN_DB_NAME`, `DB_USERNAME=KRAYIN_DB_USER` (which itself defaults to
   `KRAYIN_DB_NAME`, so two installs on one MySQL server do not share a username), `DB_PASSWORD`
   (generated when empty), `APP_URL=http://127.0.0.1:$PORT` — or `KRAYIN_APP_URL` when that is set —
   and `SESSION_COOKIE`, derived from the database name (see below). It upserts every
   key; it never falls back to set-if-unset, because `.env.example` ships a value for all of them
   and set-if-unset would keep the placeholders (`DB_USERNAME=root`, `DB_PORT=3306`,
   `DB_DATABASE=laravel-crm`, `APP_URL=http://localhost`) while the container is created from the
   script's values — an application and a database that disagree about user, port and name.
   If `.env` **and** the container both already existed (the *reuse path*, i.e. a live instance),
   `.env` is left byte-for-byte untouched: the container's real published port and its
   `MYSQL_DATABASE`/`MYSQL_USER` are read back with `docker inspect`, and every disagreement among
   `DB_HOST`, `DB_PORT`, `DB_DATABASE` and `DB_USERNAME` is printed as a warning. `SESSION_COOKIE` is
   written on the create path only, so an existing instance keeps whatever cookie name it already has.
3. **MySQL** — creates the container `crm-platform-mysql` on the **named** volume
   `crm-platform-mysql-data` if it does not exist, otherwise reuses (and starts) it. If the existing
   container is not backed by that named volume, the script refuses and prints the rebuild command.
4. **Dependencies** — `composer install`.
5. **`APP_KEY`** — `php artisan key:generate` only when `APP_KEY` is empty. The script never
   re-generates it on an installed app, because that would invalidate encrypted data. The schema
   installer in step 6 does not have that restraint — see [The installer rotates
   `APP_KEY`](#the-installer-rotates-app_key) below.
6. **Schema** — `php artisan krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction`
   when the app is not yet installed.
7. **Admin user** — only on a fresh install. The script counts the `users` table **before** the
   schema step, because the installer seeds a placeholder `admin@example.com` row and a later count
   could not tell a fresh install from a provisioned one. No rows yet (or `--reinstall`) means the
   account is created from `KRAYIN_ADMIN_EMAIL` + `KRAYIN_ADMIN_PASSWORD`; both are **required** and
   have **no default**, so a fresh install without them fails here, before anything is written or
   wiped. If the instance already has users, the step is skipped with a message and provisioning
   still exits 0.
8. **Dev server** — `php artisan serve` detached on `127.0.0.1:$PORT`, logging to `storage/logs/serve.log`.

`bash scripts/provision-dev.sh --check-env` resolves and prints, for the whole connection block
(`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL`) plus
the per-instance `SESSION_COOKIE`, the value and the action the run would take — `upsert` on the
create path, `keep`/`warn` per key on the reuse path, plus the container's real published
host/port/database/username. It then exits without writing anything. It is the dry run for the rules
in step 2.

**Re-running is safe.** The container is reused, `.env` is not rewritten, `APP_KEY` is not
regenerated, the installer — which internally runs `migrate:fresh` — is skipped whenever
`storage/installed` exists, and the admin account is left exactly as it is. Use `--reinstall` only
when you actually want the database erased and rebuilt; that path does require an admin credential,
because the installer destroys the old one.

## Why the interactive installer is bypassed

`php artisan krayin-crm:install` wrapped in `Laravel\Prompts` cannot be driven unattended: the
prompts pre-fill their own defaults and never receive EOF, so a scripted invocation blocks forever
instead of accepting input. There is no supported "answer every question" flag, so provisioning uses
the two-step path that is known to work on this machine:

```bash
php artisan krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction
KRAYIN_ADMIN_EMAIL='admin@client.test' KRAYIN_ADMIN_PASSWORD='<chosen-password>' \
  php scripts/create-admin.php
```

`scripts/create-admin.php` reproduces the admin step of the installer exactly (the `users` row with
`role_id=1, status=1`, bcrypt cost 10; the `storage/installed` marker; the `core_config` row
`installation.completed=1`; clearing `storage/installing`). It updates an existing account instead of
inserting a second one, so running it twice leaves one user — but provisioning runs it only on a
fresh install, so this update path is for deliberate changes. It does **not** dispatch the
`krayin.installed` event, because that event's only listener POSTs the admin name, email and domain
to `updates.krayincrm.com` — telemetry with no effect on the install.

The account it creates takes its email and password from the environment. **Neither has a default**,
and that is deliberate: a default would be a credential literal in this repository, and a bare
re-run would silently reset an existing admin's password. With either variable missing,
`create-admin.php` names both of them, exits 1 and writes nothing. `KRAYIN_ADMIN_NAME` is the only
optional one (default `Mohamed Ashraf`).

```bash
KRAYIN_ADMIN_EMAIL='admin@client.test' KRAYIN_ADMIN_PASSWORD='<chosen-password>' \
KRAYIN_ADMIN_NAME='Mohamed Ashraf' php scripts/create-admin.php
```

## Running the app

```bash
# start (detached)
setsid nohup php artisan serve --host=127.0.0.1 --port=8000 > storage/logs/serve.log 2>&1 < /dev/null &

# which pid is serving: the supervisor, and the child that actually owns the port
pgrep -f '^php artisan serve'
ss -ltnpH | grep -F '127.0.0.1:8000'

# stop - the anchor is not optional: a plain `pkill -f 'php artisan serve'` re-reads the full command
# line of every process, so it also matches - and kills - whatever shell or script merely mentions
# that text, including the one running the command.
pkill -f '^php artisan serve --host=127\.0\.0\.1 --port=8000$'
ss -ltn | grep -q ':8000' && pkill -f '^/usr/bin/php8\.5 -S 127\.0\.0\.1:8000'   # fallback

# restart = stop, then start again
```

Check it: `curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/admin/login` should print
`200`. Logs are in `storage/logs/`; `storage/logs/serve.log` is the dev server's request log, and
`storage/logs/laravel.log` appears only when the application reports an error.

MySQL is the Docker container on `127.0.0.1:3306`:

```bash
docker ps --filter name=crm-platform-mysql
docker exec -it crm-platform-mysql mysql -ukrayin -p krayin
```

## Where credentials live

`.env` is gitignored, so it never leaves the machine, and it holds the database password
(`DB_PASSWORD`), the application key (`APP_KEY`) and — when this script created the container — the
MySQL root password (`DB_ROOT_PASSWORD`). Passwords generated by the script are random 32-hex values
and are written only when the key is empty. If the container predates the script, `DB_ROOT_PASSWORD`
holds whatever it was created with; the root password of an existing container cannot be read back
out of it.

Current local development login:

| Field | Value |
| ----- | ----- |
| URL | http://127.0.0.1:8000/admin/login |
| Email | set with `KRAYIN_ADMIN_EMAIL` when the instance was provisioned |
| Password | set with `KRAYIN_ADMIN_PASSWORD` when the instance was provisioned |

No credential is stored in this repository, and there is no default credential to fall back on.
Provisioning will not change the account on an instance that already has users: the admin step runs
only on a genuinely fresh install, so re-running `provision-dev.sh` cannot reset the password of a
live instance. To change the account deliberately, run the admin step on its own with the values you
want — it updates the existing row instead of adding a second user:

```bash
KRAYIN_ADMIN_EMAIL='you@example.com' KRAYIN_ADMIN_PASSWORD='<chosen-password>' \
KRAYIN_ADMIN_NAME='Mohamed Ashraf' php scripts/create-admin.php
```

Any credential that was ever used here is a development credential. Always set a private
`KRAYIN_ADMIN_PASSWORD` (or change it in the admin UI) for anything a client can reach.

## Adding a second install

Use a separate directory, a separate DB name, a separate `.env`, a separate port and its own
container + volume — one database per client, never a shared one:

```bash
git clone <repo> /srv/crm-client-b
cd /srv/crm-client-b
KRAYIN_APP_PORT=8001 \
KRAYIN_DB_CONTAINER=crm-client-b-mysql \
KRAYIN_DB_VOLUME=crm-client-b-mysql-data \
KRAYIN_DB_HOST_PORT=3307 \
KRAYIN_DB_NAME=krayin_client_b \
KRAYIN_DB_USER=krayin_client_b \
KRAYIN_ADMIN_NAME='Client B Admin' \
KRAYIN_ADMIN_EMAIL=admin@client-b.test \
KRAYIN_ADMIN_PASSWORD='<chosen-password>' \
bash scripts/provision-dev.sh
```

This is a fresh install, so `KRAYIN_ADMIN_EMAIL` and `KRAYIN_ADMIN_PASSWORD` are required here —
there is no default to inherit from the first install.

Every `KRAYIN_*` value above reaches the application and the container, because the create path
upserts the whole connection block: `DB_DATABASE=KRAYIN_DB_NAME`, `DB_USERNAME=KRAYIN_DB_USER`,
`DB_PORT=KRAYIN_DB_HOST_PORT` (the same port is passed to `docker run -p`), and
`APP_URL=http://127.0.0.1:KRAYIN_APP_PORT`. Set `KRAYIN_APP_URL` when the instance is reached
through some other address.

Nothing is shared with the first install: not the directory, not the `.env`, not the database, not
the volume, not the port, not the database username, and not the session cookie.

`KRAYIN_DB_USER` is optional in the recipe because it now defaults to `KRAYIN_DB_NAME`, but setting
it explicitly is clearer — and it must match what the container was created with (`docker run -e
MYSQL_USER=...`), because the application connects with `DB_USERNAME`.

### Session cookies do not follow the port

Browsers scope cookies by host and ignore the port, so both installs would otherwise present themselves
to `127.0.0.1` as `krayin_crm_session` and one login would evict the other. On the create path the
script therefore writes `SESSION_COOKIE` into `.env`, derived from the instance's own database name
(`krayin_crm_session_krayin_client_b` here; `config/session.php` reads the name from
`env('SESSION_COOKIE')`). Set `KRAYIN_SESSION_COOKIE` to override it — an existing instance is on the
reuse path, so it is never rewritten there. The `XSRF-TOKEN` cookie name, by contrast, is hardcoded
in `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` with no config hook, so it stays shared;
that is harmless, because it is bound to the session's CSRF token and the sessions are now distinct.

## The installer rotates `APP_KEY`

`krayin-crm:install` runs `key:generate` itself. It prints `Generating key` and overwrites the
`APP_KEY` written one step earlier, with no confirmation and no warning that anything was replaced.

- On a **fresh install** this is harmless — nothing was encrypted with the previous key yet.
- On `--reinstall` it rotates the key *as well as* wiping the database. That is the part the
  `--reinstall` warning does not mention. `--reinstall` is only safe on a throwaway instance.

For a client, key rotation is destructive even when the database survives: every value encrypted
with the old key (`APP_KEY`-encrypted columns, any `Crypt::encrypt` data) becomes undecryptable, and
every active session is invalidated so all users are logged out. Never run the installer — with or
without `--reinstall` — against a client instance that holds data.

## Backup and restore a per-client database

`mysqldump` run as the application user prints this and still succeeds:

```text
mysqldump: Error: 'Access denied; you need (at least one of) the PROCESS privilege(s) for this
operation when trying to dump tablespaces'
```

The line is **noise, not failure**: the command exits `0` and the dump is complete and restorable
(verified by dumping, dropping the database and restoring it). MySQL 8.4 needs `PROCESS` to dump
tablespace metadata, which the application user does not have — the data itself is unaffected. Add
`--no-tablespaces` to silence it; the dump is otherwise identical.

Dump (substitute the container, application user and database name from the instance's `.env`):

```bash
docker exec -e MYSQL_PWD="<app-password>" <container> \
  mysqldump -u<app-user> --no-tablespaces --databases <db-name> \
  > "<db-name>-$(date +%Y%m%dT%H%M%S).sql"
```

Restore (into the same or a recreated database):

```bash
docker exec -i -e MYSQL_PWD="<app-password>" <container> \
  mysql -u<app-user> <db-name> < "<db-name>-<timestamp>.sql"
```

For the default provisioned instance that is container `crm-platform-mysql`, user `krayin`, database
`krayin`; a second install uses the `KRAYIN_DB_CONTAINER` / `KRAYIN_DB_USER` / `KRAYIN_DB_NAME` values
from its own recipe above. Keep `APP_KEY` with the dump: restoring the data without it leaves
encrypted values unreadable.

## Known gaps in the generated `.env`

These keys keep `.env.example`'s placeholder value after provisioning. None of them stops a fresh
install — the application boots and the admin logs in — but each one leaves the feature below
broken until it is configured:

- `MAIL_HOST=mailhog` / `MAIL_PORT=1025` — there is no `mailhog` container, so anything that sends
  mail (invitations, password resets, notifications) cannot connect. Mail settings are a deliberate
  later decision, not part of provisioning.
- `IMAP_HOST=imap.example.com` with `IMAP_USERNAME=your_username` / `IMAP_PASSWORD=your_password` —
  the IMAP inbox feature cannot connect, so inbound-email sync does not work.
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` and `AWS_BUCKET` — empty, so S3-backed file storage
  is unavailable and local storage is used instead.
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` — empty, so Google sign-in and Calendar integration
  are not configured.
- `PUSHER_APP_ID` / `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` — empty, so broadcasting is not
  configured.

Set them in `.env` for a real instance; provisioning does not invent credentials for third-party
services.

## Before a client sees an instance

A dev-provisioned instance is not production. At minimum:

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL=https://crm.client.example`,
  `LOG_LEVEL=warning` (or `error`), and real mail/queue settings.
- `composer install --no-dev --optimize-autoloader` — this is what removes the debug bar
  (`barryvdh/laravel-debugbar` is a dev dependency) and other development packages.
- `php artisan optimize` (config/route/view caches) after the environment is final.
- `npm ci && npm run build` if you changed anything under `resources/`.
- Serve with a real web server (nginx/Apache + PHP-FPM) against `public/`, not `artisan serve`.
- Keep `APP_KEY` and the database; do not re-run `krayin-crm:install`, which runs `migrate:fresh`.
- Set the admin password to something private:
  `KRAYIN_ADMIN_EMAIL=... KRAYIN_ADMIN_PASSWORD='<chosen-password>' php scripts/create-admin.php`
  (both are required — there is no default).
- Make sure `storage/` survives deploys, or rely on the `installation.completed` flag recorded in
  `core_config` — either one keeps the installer closed.

The dev server also logs every request to `storage/logs/serve.log`; that file should not be shipped
to a production host.

## Known quirk

`composer.json` pins the Composer platform to `php: 8.3.30`, so installing and running on a newer
PHP (this machine runs 8.5.4) is expected and supported. Because of that pin, a bare
`composer check-platform-reqs` reports `phpoffice/phpspreadsheet`'s `< 8.5` bound as **failed** and
Composer prints a "PHP version ... / Run the diagnose command" banner. `composer install` resolves
against the pinned platform and succeeds; the failure is an artifact of the pin, not a broken
checkout.
