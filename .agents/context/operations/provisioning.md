<!-- Context: operations/provisioning.md | Priority: high | Version: 1.0 | Updated: 2026-10-16 -->

# Provisioning

Two scripts do all setup: `scripts/provision-dev.sh` (headless) and
`scripts/create-admin.php` (admin account). Neither invents a credential.

## `scripts/provision-dev.sh`

```bash
bash scripts/provision-dev.sh                 # http://127.0.0.1:8000
bash scripts/provision-dev.sh --port 8001
bash scripts/provision-dev.sh --check-env     # dry run, writes nothing
bash scripts/provision-dev.sh --reinstall     # DESTRUCTIVE: erases and rebuilds
```

Steps, in order: prerequisite check → `.env` → MySQL container/volume →
`composer install` → `APP_KEY` if empty → unattended schema install → admin
account → detached dev server.

Guards an agent must know:

- **Fresh install requires `KRAYIN_ADMIN_EMAIL` and `KRAYIN_ADMIN_PASSWORD`.**
  Neither has a default; a fresh install without them fails before writing
  anything. There is no credential literal in the repository, by design.
- **The reuse path never rewrites `.env`.** If `.env` and the container both
  exist, `.env` is left byte-for-byte untouched and disagreements between
  `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME` and the container are printed
  as warnings.
- **`APP_KEY` is not regenerated** on an existing install (that would
  invalidate encrypted data and all sessions).
- **The installer is skipped** whenever `storage/installed` exists, so re-running
  provisioning cannot reset a live admin password.
- **MySQL is published on loopback only** (`-p 127.0.0.1:$KRAYIN_DB_HOST_PORT:3306`),
  never on `0.0.0.0`, so a provisioned instance is not exposed to the LAN.

## The interactive installer is bypassed

`php artisan krayin-crm:install` cannot be driven unattended: its
`Laravel\Prompts` TUI pre-fills defaults and never receives EOF, so a scripted
call blocks forever. Provisioning uses the known-good two-step path:

```bash
php artisan krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction
KRAYIN_ADMIN_EMAIL='admin@client.test' KRAYIN_ADMIN_PASSWORD='<chosen>' \
  php scripts/create-admin.php
```

`scripts/create-admin.php` reproduces the installer's admin step (`users` row
with `role_id=1, status=1`, bcrypt cost 10; the `storage/installed` marker; the
`core_config` row `installation.completed=1`). It **updates** an existing
account instead of inserting a second one, takes both values from the
environment, requires both, and exits 1 without writing when either is missing.
`KRAYIN_ADMIN_NAME` is the only optional one.

## The installer rotates `APP_KEY`

`krayin-crm:install` runs `key:generate` itself, silently overwriting the key
written one step earlier. On a fresh install that is harmless. On
`--reinstall` it also wipes the database. On an instance that holds data, key
rotation makes encrypted values undecryptable and logs every user out. Never run
the installer against a client instance with data.

## A second client install

One client, one directory, one `.env`, one database, one container, one volume,
one port. Run the provisioning script with `KRAYIN_APP_PORT`,
`KRAYIN_DB_CONTAINER`, `KRAYIN_DB_VOLUME`, `KRAYIN_DB_HOST_PORT`,
`KRAYIN_DB_NAME`, `KRAYIN_DB_USER` and the three `KRAYIN_ADMIN_*` values set; see
`scripts/README.md` for the full recipe. On the create path the script also
writes `SESSION_COOKIE` derived from the database name, because browsers scope
cookies by host and ignore the port — without it two installs on `127.0.0.1`
evict each other's login.

## Related

- Full runbook and all `KRAYIN_*` variables → `scripts/README.md`
- Database names and reset → [database.md](database.md)
