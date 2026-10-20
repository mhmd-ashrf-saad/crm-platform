<!-- Context: operations/deployment-homelab.md | Priority: high | Version: 1.0 | Updated: 2026-10-20 -->

# Homelab Deployment

The one instance that is neither this checkout nor a client machine: a demo
deployment of Sijilna on the user's homelab, public at
`https://crm.mohamedlabs.com`. Verified live on 2026-10-20 from commit
`7e8e64af`. Runbook: `deploy/README.md` (canonical detail, kept in the repo).

## Where it runs

| What | Value |
| ---- | ----- |
| Host | the homelab desktop (WSL2 Ubuntu, RAM capped at 4 GB), reached as `ssh homelab` |
| Checkout | `/opt/sijilna/app`, `main`, commit `7e8e64af` |
| Stack file | `/opt/sijilna/app/deploy/docker-compose.yml`, compose project `sijilna` |
| Env file | `/opt/sijilna/app/.env`, owned by **uid 33 (www-data)**, mode `600`; `deploy/.env` is a symlink to it |
| Database volume | named volume `sijilna-db-data` — never a bind mount to `storage/` |

This is a *different machine* from the one this checkout lives on. Nothing here
runs `php artisan serve`: the app runs only inside containers, and every command
against it goes through `docker compose -f /opt/sijilna/app/deploy/docker-compose.yml`.

## Containers and the single published port

| Container | Image | Port |
| --------- | ----- | ---- |
| `sijilna-web` | `nginx:alpine` | **`127.0.0.1:3102:80`** — the only published port |
| `sijilna-app` | `sijilna-app:latest`, built from `deploy/docker/php.Dockerfile` (PHP-FPM 8.3) | none (`expose: 9000` on the compose network) |
| `sijilna-db` | `mysql:8.4` | **none** — reachable only as `db:3306` |

`sijilna-web` is bound to **loopback only**. `0.0.0.0:3102` would put a
customer-tracked CRM on the whole LAN; that is a hard rule on this host. `app`
and `db` publish nothing at all — `DB_HOST=db` is the compose service name, and
a leftover `127.0.0.1` from a development `.env` is the most common cause of an
app container looping on "no TCP connection".

## Public ingress

Zone and tunnel belong to the vault, not to this repo: the tunnel is
`homelab-demos` (UUID `9de8ce79-ea20-4cb0-9720-ee5d54272a46`), remotely managed.
Two pieces were added for this deployment and nothing else was touched:

- an ingress rule **above the catch-all**: `crm.mohamedlabs.com` →
  `http://localhost:3102` (tunnel configuration version 2). The existing
  `demo1.mohamedlabs.com` rule still answers 200.
- a proxied **CNAME** `crm` → `9de8ce79-ea20-4cb0-9720-ee5d54272a46.cfargotunnel.com`
  in zone `mohamedlabs.com`.

The tunnel forwards **plain HTTP**, so nginx never terminates TLS and Laravel
would emit `http://` asset URLs the browser blocks as mixed content. That is
what `APP_FORCE_HTTPS=true` in `.env` exists for — it forces the `https` scheme.
Zone-level `Always Use HTTPS` and `min_tls_version` were deliberately left unset
because changing them affects `demo1` too.

## Install and boot sequence

`deploy/docker/entrypoint.sh` runs on every container start, in order:
require a non-empty `APP_KEY` → `chown -R 33:33 storage bootstrap/cache` → wait
for MySQL TCP (60 × 2 s) → `package:discover` → `storage:link` if missing →
`migrate --force` → install only if `storage/installed` is absent → `sijilna:brand`
→ `exec php-fpm`. Artisan always runs as `www-data` via
`setpriv --reuid=www-data`, so everything it writes is owned by the FPM user.

**`storage/installed` is the idempotency marker and the destructive-install
kill-switch.** It lives in the bind-mounted `storage/`, so it survives image
rebuilds and container recreations. While it exists the installer — whose
internal step is `migrate:fresh` — never runs again. Deleting that one file (or
wiping the host's `storage/`, which `git clean -xfd` will do) re-arms a full
schema wipe against the live database on the next start, with no `down -v` and
no warning. Because every step checks before it acts, `restart`, `up -d` and
`up -d --build` are all safe repeats.

The install itself uses the same two-step path as
[provisioning.md](provisioning.md): unattended
`krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction`,
then `scripts/create-admin.php` from `KRAYIN_ADMIN_EMAIL` /
`KRAYIN_ADMIN_PASSWORD`. Result on 2026-10-20: 61 tables, exactly 1 administrator
row, `storage/installed` present, `core_config installation.completed=1`.

## First-boot failure that actually happened

The app container **restart-looped** with
`file(/var/www/html/.env): Failed to open stream: Permission denied`. Cause: the
installer's `getEnvAtRuntime()` reads the file directly with PHP's `file()`
(`packages/Webkul/Installer/src/Console/Commands/Installer.php`), and the
mounted `.env` was mode `600` owned by the host user, not uid 33. Laravel itself
had booted fine from the container environment (`env_file` injects the same
keys), which is why the database connected while the installer died — a healthy
DB with a restart-looping app is this bug, not a database problem.

Fix, host-side and one-time:
`sudo chown 33:33 /opt/sijilna/app/.env`, keeping mode `600`. Do not widen it to
`644`; the file holds `APP_KEY` and the database passwords. The entrypoint
cannot repair this itself — the mount is read-only.

## Memory budget

Three containers measured at roughly **470 MB RSS** together, leaving about
**1.9 GB available** on the 4 GB host. The ceilings that make that hold: MySQL
`--innodb-buffer-pool-size=128M --innodb-log-buffer-size=16M
--max-connections=60` (~350–400 MB), PHP-FPM `pm.max_children = 4` ×
`memory_limit = 256M` (a 1 GB worst case, 60–100 MB per worker typically),
nginx a few MB, OPcache 128 MB shared once. If the box swaps, lower
`pm.max_children` to 3 before touching MySQL's buffer pool.

## Open risks

1. **Backups now cover this deployment, with two caveats.** Since 2026-10-20 the
   nightly restic run stages `/opt/sijilna` *and* a `mysqldump` of the `sijilna`
   database (`/var/lib/homelab-staging/sijilna/` and `…/sijilna-mysql.sql`), so the
   earlier gap is closed. Caveats: a night on which `sijilna-db` was stopped has
   no SQL in its snapshot, and the snapshot now holds `.env` (with `APP_KEY`) next
   to the dump, so a copy of the restic repo — including the 03:30 laptop copy —
   is both the data and the key. Treat a lost or leaked repository copy as a
   credential compromise, not just a data leak.
2. **No uptime monitor.** Uptime Kuma has no check for `crm.mohamedlabs.com`, so
   a dead tunnel on a URL a client was shown produces no alert.
3. **The administrator password is a demo credential** on a public login page.
   Rotate it before the instance is described to anyone as a real CRM.
4. **`storage/installed` is a one-file kill-switch** (see above). It is also the
   reason a `git clean` in `/opt/sijilna/app` is a destructive operation there,
   not a tidy-up.

## Related

- Canonical runbook, troubleshooting table, reset → `deploy/README.md`
- Installer/admin step → [provisioning.md](provisioning.md)
- Local runtime (dev server, local MySQL, NTFS caveats) → [environment-and-deployment.md](environment-and-deployment.md)
- Backup/restore reality → [database.md](database.md)
- Tunnel, domain and host itself → Obsidian vault: `Servers/Cloudflare Tunnel.md`, `Servers/mohamedlabs.com.md`, `Servers/Desktop Homelab.md`
