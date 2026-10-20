# Deploying Sijilna on the homelab

The production stack lives in this directory: `php.Dockerfile`, `php.ini`,
`php-fpm.conf`, `nginx.conf`, `entrypoint.sh`, `docker-compose.yml` and the
`.env.deploy.example` template. It runs the same application this checkout runs
locally, as three containers instead of `php artisan serve` plus a MySQL
container.

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

# 4. The FPM user is uid/gid 33 (www-data). storage/ is bind-mounted, so the
#    image's ownership does not apply to it. The entrypoint re-applies this on
#    every start, but doing it once here avoids a first boot spent failing.
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

## Verify

```bash
# 1. nginx is on loopback only, and it is the expected one
ss -ltnp | grep ':3102'          # must show 127.0.0.1:3102, never 0.0.0.0:3102

# 2. The app answers locally
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3102/admin/login   # 200
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3102/up            # 200
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3102/build/manifest.json  # 200

# 3. The tunnel leg works
curl -s -o /dev/null -w '%{http_code}\n' https://crm.mohamedlabs.com/admin/login
curl -sI https://crm.mohamedlabs.com/admin/login | grep -i '^location:'      # expect https://, not http://

# 4. Containers, including the database healthcheck
sudo docker compose -f deploy/docker-compose.yml ps

# 5. Count rows, not tables: the installer seeded an admin, so this is >= 1
sudo docker compose -f deploy/docker-compose.yml exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" -N -e "select count(*) from users;" "$MYSQL_DATABASE"'

# 6. Errors from the app container (PHP warnings land here, not on the page)
sudo docker compose -f deploy/docker-compose.yml logs --tail=60 app
```

Log in at `https://crm.mohamedlabs.com/admin/login` with the administrator that
was created. A page that renders is not proof: log in, then open Dashboard and
one record, because auth and session handling touch the database and storage.

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

## Troubleshooting

| Symptom | Cause | Check / fix |
| ------- | ----- | ----------- |
| **502 Bad Gateway** | nginx cannot reach PHP-FPM, or FPM is restarting | `docker compose ps` (is `sijilna-app` up?), then `docker compose logs app`. `fastcgi_pass app:9000` only resolves while the app container is on the same compose network — never copy a container out of this file. |
| **504 Gateway Timeout** | A request exceeded `fastcgi_read_timeout 130s` — usually a PDF or spreadsheet export | `docker compose logs app` for the request that hung; the PHP limit is `max_execution_time = 120` in `php.ini`. Raise both together, or fix the export. |
| **500 on every page** | Empty `APP_KEY`, unwritable `storage/`, or a database error | `docker compose logs app \| head -40`, then `storage/logs/laravel.log` on the host. The entrypoint refuses to boot with an empty `APP_KEY` and says so; permission errors are `chown -R 33:33 storage bootstrap/cache`. |
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

If the box starts swapping, lower `pm.max_children` in `php-fpm.conf` to 3
before touching MySQL's buffer pool: a slow CRM is better than an OOM kill.

## Backups

**The `sijilna-db-data` volume is NOT in the existing `/opt/homelab` restic
backup set.** That set covers host paths, and a Docker named volume lives under
`/var/lib/docker/volumes/sijilna-db-data/_data` — copying that directory out
from under a running MySQL is not a safe backup anyway.

Either add a dump step to the restic run:

```bash
mkdir -p /opt/sijilna/backup
sudo docker compose -f /opt/sijilna/app/deploy/docker-compose.yml exec -T db \
  sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction \
         --routines --events -u root "$MYSQL_DATABASE"' \
  > /opt/sijilna/backup/sijilna-$(date +%F).sql
```

and make sure `/opt/sijilna/backup` is inside the restic `--include` set, or back
the volume up through a small sidecar container. Uploaded files under
`storage/app/public` are on the host and therefore covered by the host backup
already.

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
