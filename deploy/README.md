# Deploying Sijilna on the homelab

The production stack lives in this directory: `docker-compose.yml`, the
`.env.deploy.example` template, and `docker/` — `php.Dockerfile`, `php.ini`,
`php-fpm.conf`, `nginx.conf` and `entrypoint.sh`. It runs the same application
this checkout runs locally, as three containers instead of `php artisan serve`
plus a MySQL container.

| What | Value |
| ---- | ----- |
| Host path | `/opt/sijilna/app` on the homelab |
| Loopback port | `127.0.0.1:3102` (nginx) — **loopback only** |
| Public URL | `https://crm.mohamedlabs.com` |
| Tunnel | Cloudflare Tunnel `homelab-demos`, public hostname → `http://127.0.0.1:3102` |
| Database volume | named volume `sijilna-db-data` (not a bind mount) |
| Containers | `sijilna-web` (nginx), `sijilna-app` (PHP-FPM 8.3), `sijilna-db` (MySQL 8.4) |

Only `sijilna-web` publishes a port, and the mapping is `127.0.0.1:3102:80`.
Publishing it on `0.0.0.0` would put the CRM on the whole LAN — that is a hard
rule on this host, not a preference.

The front-end assets are committed (`public/build`,
`public/{admin,installer,webform}/build`), so the image has **no Node build
step**: `docker compose up -d --build` is the whole deploy.

## First deploy

```bash
sudo mkdir -p /opt/sijilna
sudo git clone git@github.com:mhmd-ashrf-saad/crm-platform.git /opt/sijilna/app
cd /opt/sijilna/app

# 1. One env file, two names: compose interpolates deploy/.env, and the app
#    container mounts ../.env read-only. A symlink keeps them byte-identical
#    forever; copying twice is how DB_USERNAME drifts and the app cannot log in.
cp deploy/.env.deploy.example .env
ln -s ../.env deploy/.env

# 2. APP_KEY. `php artisan key:generate --show` cannot be used here: artisan
#    needs vendor/, which is not committed and is only installed inside the
#    image. The key is 32 random bytes, base64-encoded, which is exactly what
#    Laravel's own generator produces for AES-256-CBC.
KEY=$(docker run --rm php:8.3-fpm-bookworm php -r 'echo "base64:".base64_encode(random_bytes(32));')
sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env

# 3. Secrets. DB_PASSWORD, DB_ROOT_PASSWORD and the first-boot administrator
#    pair KRAYIN_ADMIN_EMAIL / KRAYIN_ADMIN_PASSWORD. Nothing is defaulted
#    anywhere in this stack; an empty APP_KEY stops the container on purpose.
$EDITOR .env
chmod 600 .env

# 4. Hand both mounted paths to the FPM user, uid/gid 33 (www-data).
#    storage/ is bind-mounted, so the image's ownership does not apply to it;
#    the entrypoint re-applies that one on every start, but doing it once here
#    avoids a first boot spent failing. .env has no such safety net -- see
#    "The .env must belong to uid 33" below, because skipping this line is what
#    makes the app container restart-loop on the very first boot.
sudo chown 33:33 .env
sudo chown -R 33:33 storage bootstrap/cache

# 5. Build and start. The first build compiles six PHP extensions (several
#    minutes); later builds reuse those layers.
sudo docker compose -f deploy/docker-compose.yml up -d --build
```

On that first start the `app` container waits for MySQL, runs
`package:discover`, `migrate --force`, then — because `storage/installed` does
not exist yet — the unattended installer
(`krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction`)
followed by `scripts/create-admin.php`. If the administrator pair was left empty
the container logs a warning and keeps running; create the account deliberately:

```bash
sudo docker compose -f deploy/docker-compose.yml exec app \
  env KRAYIN_ADMIN_EMAIL='admin@example.com' KRAYIN_ADMIN_PASSWORD='<chosen>' \
  php scripts/create-admin.php
```

After a successful first boot, delete `KRAYIN_ADMIN_EMAIL` and
`KRAYIN_ADMIN_PASSWORD` from `.env` and restart the app container: the
credentials have done their job and do not need to sit in a file the container
keeps reading.

## The .env must belong to uid 33

`.env` is mounted read-only at `/var/www/html/.env` and every artisan command in
the entrypoint runs as `www-data`, so **uid/gid 33 must be able to read it**. The
first real deploy proved what happens otherwise: the container restart-looped
with

```text
file(/var/www/html/.env): Failed to open stream: Permission denied
```

thrown from the installer's `getEnvAtRuntime()`, which does
`file(base_path('.env'))` (`packages/Webkul/Installer/src/Console/Commands/Installer.php`).
A `chmod 600 .env` owned by the human who edited it fails exactly this way.

What makes the symptom confusing is that **Laravel itself still booted**: the
compose `env_file` injects the same keys into the container environment, so the
app connected to MySQL and the rest of the boot progressed while only the
installer — the one component that reads the file directly — died. "The database
is fine but the container is in a restart loop" is this bug.

```bash
sudo chown 33:33 /opt/sijilna/app/.env
```

Do not widen the mode to `644` to "fix" it: the file holds `APP_KEY`, the
database passwords and the first-boot administrator pair. Ownership by the
container's user at mode `600` is the intended state. The entrypoint
**cannot** correct this itself — the mount is read-only and the ownership lives
on the host inode — so it is a one-time host step that then survives every
restart, rebuild and `up -d`.

## Verify

```bash
# 1. nginx is on loopback only, and it is the expected one
ss -ltnp | grep ':3102'          # must show 127.0.0.1:3102, never 0.0.0.0:3102

# 2. The app answers locally
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3102/admin/login   # 200
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3102/up            # 200
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3102/build/manifest.json  # 200

# 3. The tunnel leg works, and every URL it serves is https
curl -s -o /dev/null -w '%{http_code}\n' https://crm.mohamedlabs.com/admin/login   # 200
curl -sI https://crm.mohamedlabs.com/admin/login | grep -i '^location:'      # expect https://, not http://
curl -s -o /dev/null -w '%{http_code}\n' https://crm.mohamedlabs.com/             # 302 -> .../admin/login
curl -s -o /dev/null -w '%{redirect_url}\n' https://crm.mohamedlabs.com/          # must start with https://

#    Mixed content is the failure APP_FORCE_HTTPS exists to prevent, so count the
#    schemes the login page actually emits instead of trusting the flag.
curl -s https://crm.mohamedlabs.com/admin/login | grep -c 'http://'          # 0
curl -s https://crm.mohamedlabs.com/admin/login | grep -c 'https://'         # non-zero

# 4. Containers, including the database healthcheck
sudo docker compose -f deploy/docker-compose.yml ps          # all three Up; db (healthy)

# 4b. Monitoring: Uptime Kuma checks https://crm.mohamedlabs.com/admin/login every
#     60s (monitor 11, Telegram on failure). A DOWN there while the stack is up
#     means the tunnel, not the app.

# 5. Count rows, not tables: the installer seeded an admin, so this is >= 1
sudo docker compose -f deploy/docker-compose.yml exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" -N -e "select count(*) from users;" "$MYSQL_DATABASE"'

# 6. Errors from the app container (PHP warnings land here, not on the page)
sudo docker compose -f deploy/docker-compose.yml logs --tail=60 app
```

Log in at `https://crm.mohamedlabs.com/admin/login` with the administrator that
was created. A page that renders is not proof: log in, then open Dashboard and
one record, because auth and session handling touch the database and storage.

What the first real deploy (2026-10-20) produced, as the baseline to compare
against: a fresh schema of **61 tables**, exactly **1** row in `users`, a
`storage/installed` marker, `core_config installation.completed=1`, the Sijilna
brand rows seeded by `sijilna:brand`, `/admin/login` **200**, `/` **302** to
`https://crm.mohamedlabs.com/admin/login`, a real cookie-jar login **302** →
`/admin/dashboard` **200** with **125** `https://` asset URLs and **zero**
`http://` ones — the count that matters, because a tunnel terminates TLS and
hands nginx plain HTTP, which is exactly the mixed content `APP_FORCE_HTTPS`
exists to prevent.

## Update

```bash
cd /opt/sijilna/app
git pull --ff-only
sudo docker compose -f deploy/docker-compose.yml up -d --build
```

Migrations run automatically at container start (`migrate --force`), and the
installer never runs again because `storage/installed` survives in the
bind-mounted `storage/`. Configuration is deliberately **not** cached, so a
`.env` edit takes effect with a restart:

```bash
sudo docker compose -f deploy/docker-compose.yml restart app
```

**Restarting the stack is safe and idempotent.** Every step in `entrypoint.sh`
checks before it acts: it re-applies `storage/` and `bootstrap/cache/`
ownership, skips `storage:link` when the link exists, re-runs
`package:discover` and `migrate --force` (a no-op when the schema is current),
skips the installer while `storage/installed` exists, and re-seeds the brand
rows idempotently (`sijilna:brand` rewrites the footer credit and creates every
other row only when it is absent). `restart`, `stop && start`, `up -d` and
`up -d --build` therefore all land in the same state; none of them re-runs
`migrate:fresh`. Rebuilding is only needed when the **image** changes (code,
`composer.lock`, `deploy/docker/*`) — a `.env` edit never needs a rebuild.

## Reset (destructive — deletes the database)

```bash
cd /opt/sijilna/app
sudo docker compose -f deploy/docker-compose.yml down -v     # -v deletes sijilna-db-data
sudo rm -f storage/installed                                  # lets the installer run again
# re-enter KRAYIN_ADMIN_EMAIL / KRAYIN_ADMIN_PASSWORD in .env first
sudo docker compose -f deploy/docker-compose.yml up -d --build
```

`down -v` destroys the named volume. There is no undo, and it is not covered by
any backup (see below).

**`storage/installed` is the kill-switch, and it is not the database.** The
marker is the only thing standing between a restart and the installer's internal
`migrate:fresh`. Deleting `storage/installed` — or wiping the host's `storage/`
directory, which a `git clean -xfd` will happily do — **re-arms a destructive
install against whatever database the container can reach**. The data loss then
happens on the next container start, without `down -v` and without any warning,
because the entrypoint only sees "no marker yet". Treat deleting that one file
exactly as carefully as `down -v`, and stop the stack before touching `storage/`.

## Troubleshooting

| Symptom | Cause | Check / fix |
| ------- | ----- | ----------- |
| **502 Bad Gateway** | nginx cannot reach PHP-FPM, or FPM is restarting | `docker compose ps` (is `sijilna-app` up?), then `docker compose logs app`. `fastcgi_pass app:9000` only resolves while the app container is on the same compose network — never copy a container out of this file. |
| **504 Gateway Timeout** | A request exceeded `fastcgi_read_timeout 130s` — usually a PDF or spreadsheet export | `docker compose logs app` for the request that hung; the PHP limit is `max_execution_time = 120` in `php.ini`. Raise both together, or fix the export. |
| **500 on every page** | Empty `APP_KEY`, unwritable `storage/`, or a database error | `docker compose logs app \| head -40`, then `storage/logs/laravel.log` on the host. The entrypoint refuses to boot with an empty `APP_KEY` and says so; permission errors are `chown -R 33:33 storage bootstrap/cache`. |
| **`app` restart-loops, log says `.env: Failed to open stream: Permission denied`** | `.env` is unreadable by uid 33 — the installer reads it with `file()` while Laravel boots from the container environment | `sudo chown 33:33 /opt/sijilna/app/.env`; keep the mode at `600`. See "The .env must belong to uid 33". |
| **Mixed content / forms post to `http://`** | `APP_FORCE_HTTPS` missing | The tunnel forwards plain HTTP, so Laravel must be told. Set `APP_FORCE_HTTPS=true` in `.env`, `docker compose restart app`. |
| **Login works on `127.0.0.1:3102`, not through the tunnel** | Cookies/URLs bound to the wrong host | `APP_URL=https://crm.mohamedlabs.com`, `SESSION_COOKIE=sijilna_session`, and `APP_FORCE_HTTPS=true`; then restart `app`. |
| **`db` container healthy, app loops "no TCP connection"** | `DB_HOST` is not `db` (a leftover `127.0.0.1` from a dev `.env`) or the password changed after MySQL first initialised | `DB_HOST=db`, `DB_PORT=3306`. `MYSQL_PASSWORD`/`MYSQL_ROOT_PASSWORD` are only applied when the data directory is empty — changing them later needs the reset above. |
| **`/var/www/html/.env` is a directory** | `up` ran before `../.env` existed; Docker created a directory as the mount target | `docker compose down`, `sudo rm -rf .env` (only if it is the empty directory Docker made), recreate the file, `up -d`. |
| **403/404 on `/storage/...`** | Uploaded file not visible to nginx | `nginx.conf` maps `/storage/` to `/var/www/html/storage/app/public/`; confirm the upload landed there with `ls storage/app/public`. |
| **Assets 404** | `public/build` missing from the checkout | `git status` — assets are committed; a partial clone or an over-eager `.dockerignore` edit is the cause. |

## Memory budget

The host is 4 GB and must keep roughly 2.4 GB free. Nothing in this stack may
claim memory it does not need:

| Component | Cap |
| --------- | --- |
| MySQL 8.4 | `--innodb-buffer-pool-size=128M`, `--innodb-log-buffer-size=16M`, `--max-connections=60` → roughly 350–400 MB resident |
| PHP-FPM | `pm.max_children = 4` × `memory_limit = 256M` = a 1 GB worst case, typically 60–100 MB per worker |
| nginx | a few MB |
| OPcache | 128 MB shared once by all workers, not per worker |

The first real deploy measured the whole stack at roughly **470 MB RSS** across
the three containers, leaving about **1.9 GB available** on the 4 GB host — the
caps above are sized so that stays true.

If the box starts swapping, lower `pm.max_children` in `php-fpm.conf` to 3
before touching MySQL's buffer pool: a slow CRM is better than an OOM kill.

## Backups

The `sijilna-db-data` volume is never copied: a Docker named volume lives under
`/var/lib/docker/volumes/sijilna-db-data/_data`, and copying a live InnoDB
directory is not a backup — it is the exact mistake the homelab backup script was
written to replace.

Since 2026-10-20 the host's nightly restic run covers this deployment
(`/opt/homelab/homelab-backup.sh`, `homelab-backup.timer`, 03:00):

| What | Where it lands in the snapshot |
| ---- | ---- |
| the whole `/opt/sijilna` tree, **including `.env`** | `/var/lib/homelab-staging/sijilna/` |
| a logical dump of the `sijilna` database | `/var/lib/homelab-staging/sijilna-mysql.sql` |

The dump is taken *inside* `sijilna-db` with `mysqldump --single-transaction
--quick --routines --triggers --events --databases`: a consistent InnoDB snapshot
with no table locks, streaming rather than buffering, keeping the objects a plain
data dump drops, and naming the schema so an import needs no `CREATE DATABASE`.
The credentials come from the container's own environment through `MYSQL_PWD`, so
they never appear in argv or in the log. The run fails unless the dump ends with
mysqldump's `-- Dump completed` trailer and is non-empty — exit status alone does
not prove a complete dump.

If `sijilna-db` is stopped at 03:00 the dump is skipped with a loud warning and
the run still succeeds, because the stack may have been stopped deliberately.
That night's snapshot then has the tree but no SQL, so restoring from it needs the
previous night's dump.

`.env` is in the snapshot on purpose: `APP_KEY` is what decrypts the encrypted
columns in the restored database, so a restore without it yields a site that runs
and shows garbage. The corollary is worth stating plainly — whoever holds the
restic repository, or the 03:30 laptop copy of it, holds the database dump and
the key together.

```bash
# restore (fresh target, never over the live tree)
sudo restic restore latest --target /var/tmp/restore
sudo cp -a /var/tmp/restore/var/lib/homelab-staging/sijilna/app/. /opt/sijilna/app/
# then, against a FRESH volume, import the dump and start the stack
sudo docker exec -i sijilna-db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root' \
  < /var/tmp/restore/var/lib/homelab-staging/sijilna-mysql.sql
```

## Working inside the stack

```bash
# shell in the app container
sudo docker compose -f deploy/docker-compose.yml exec app sh

# artisan as the FPM user (the entrypoint runs it this way so file ownership
# stays consistent)
sudo docker compose -f deploy/docker-compose.yml exec app \
  setpriv --reuid=www-data --regid=www-data --clear-groups php artisan about

# follow the app log the container writes
tail -f storage/logs/laravel.log

# rebuild only the image after a code change
sudo docker compose -f deploy/docker-compose.yml up -d --build app
```
