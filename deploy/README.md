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

This directory is also the provisioning path for a **second** instance on the
same host (another client, a test copy, a staging stack). Every name a second
stack would collide on is an `SIJILNA_*` key in `deploy/.env` whose default is
the value in the table above, so the live deployment resolves exactly as it
always has. See [Second instance](#second-instance).

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
#    "The .env must belong to the deploy user AND to uid 33" below, because
#    skipping this line is what makes the app container restart-loop on the very
#    first boot.
sudo chown mohamed:www-data .env
sudo chmod 640 .env
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

## The .env must belong to the deploy user AND to uid 33

`.env` is mounted read-only at `/var/www/html/.env` and every artisan command in
the entrypoint runs as `www-data`, so **uid/gid 33 must be able to read it**. It
also has to be readable by **`mohamed`**, because `deploy.sh` runs as that user
and `docker compose` interpolates `deploy/.env` (a symlink to this file) before it
talks to the daemon — with `33:33 0600` every deploy dies with
`open /opt/sijilna/app/deploy/.env: permission denied`. Owner `mohamed`, group
`www-data`, mode `0640` satisfies both and still keeps the file off the world.

The first real deploy proved what happens when the container's user cannot read
it: the container restart-looped with

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
sudo chown mohamed:www-data /opt/sijilna/app/.env
sudo chmod 640 /opt/sijilna/app/.env
```

Do not widen the mode to `644` to "fix" it: the file holds `APP_KEY`, the
database passwords and the first-boot administrator pair. Owner-plus-group at
mode `640` is the intended state, and group `www-data` is what the container's
worker reads it through. The entrypoint
**cannot** correct this itself — the mount is read-only and the ownership lives
on the host inode — so it is a one-time host step that then survives every
restart, rebuild and `up -d`. If it is ever reset to `www-data:www-data`, the
container still boots and the *deploy* stops working, which is a more confusing
symptom than the restart loop; `ls -l /opt/sijilna/app/.env` should show
`mohamed www-data`.

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

## Second instance

A second instance is another client, a test copy, or a staging stack on the same
host. It is the same application, the same image build and the same three
services — what differs is the identity, and the identity is seven `SIJILNA_*`
keys in `deploy/.env`:

| `deploy/.env` key | Compose uses it for | Live instance (default) | `crm-test` |
| ----------------- | ------------------- | ----------------------- | ---------- |
| `SIJILNA_PROJECT` | compose project name (and the `sijilna_default` network) | `sijilna` | `sijilna-test` |
| `SIJILNA_DB_CONTAINER` | `db` container name | `sijilna-db` | `sijilna-test-db` |
| `SIJILNA_APP_CONTAINER` | `app` container name | `sijilna-app` | `sijilna-test-app` |
| `SIJILNA_WEB_CONTAINER` | `web` container name | `sijilna-web` | `sijilna-test-web` |
| `SIJILNA_IMAGE` | image name (the tag is always `:latest`) | `sijilna-app` | `sijilna-test-app` |
| `SIJILNA_DB_VOLUME` | the MySQL data volume | `sijilna-db-data` | `sijilna-test-db-data` |
| `SIJILNA_WEB_PORT` | published loopback port | `3102` | `3103` |

On the live instance all seven keys are **absent** from `deploy/.env`, and that
is the contract: their defaults — the third column — are exactly what
`/opt/sijilna` resolves to today. Do not fill them in "for clarity": an `.env`
that names them differently starts a different stack against a different
volume. `deploy/deploy.sh` keeps rolling back onto containers whose names it
hardcodes (`sijilna-db`, `sijilna-app`, `sijilna-web`), so those defaults are
load-bearing in more than one place.

That contract is enforced rather than merely documented. A run of
`/opt/sijilna/deploy.sh` **refuses to do anything** when any `SIJILNA_*` key
appears in `/opt/sijilna/app/.env` — or when one of those seven is exported into
its own environment — naming the keys and the file before it fetches, dumps,
builds or restarts anything, and alerting once on Telegram. Every compose call it
makes is run as `docker compose -p sijilna …`, so an exported
`COMPOSE_PROJECT_NAME` (which outranks the file's `name:`) cannot move the
stack either. Commenting the keys out is the fix; there is no flag that skips the
check, deliberately — the check is the margin between one stray key and an
`up -d` that brings the project up on an empty volume next to the live one.

### Provision it

```bash
# 1. Resolve and check. Writes nothing, clones nothing, starts nothing.
sudo SIJILNA_INSTANCE_HOSTNAME=crm-test.mohamedlabs.com \
     SIJILNA_INSTANCE_DIR=/opt/sijilna-test/app \
     SIJILNA_PROJECT=sijilna-test \
     SIJILNA_WEB_PORT=3103 \
     SIJILNA_DB_NAME=sijilna_test \
     SIJILNA_DB_USER=sijilna_test \
     KRAYIN_ADMIN_EMAIL='admin@client.test' \
     KRAYIN_ADMIN_PASSWORD='<chosen>' \
     bash deploy/provision-instance.sh --dry-run

# 2. The same command without --dry-run provisions the instance.
```

Run it from a checkout of this repository (any clone works — the script clones
its own checkout into `SIJILNA_INSTANCE_DIR`, so it does not matter where you
invoke it from). Values come from the environment and never from argv, because a
password in argv is a password in `ps`, in `/proc/<pid>/cmdline` and in the
shell history. Missing ones are reported together, before anything is written.

Required: `SIJILNA_INSTANCE_HOSTNAME`, `SIJILNA_INSTANCE_DIR`, `SIJILNA_PROJECT`,
`SIJILNA_WEB_PORT`, `SIJILNA_DB_NAME`, `SIJILNA_DB_USER`, plus
`KRAYIN_ADMIN_EMAIL` / `KRAYIN_ADMIN_PASSWORD` **while the instance has no
`storage/installed` marker and no pair recorded in `.env`** — on a re-run the
pair is neither asked for nor used.

Optional: `SIJILNA_DB_PASSWORD` and `SIJILNA_DB_ROOT_PASSWORD` (otherwise 48
hex characters are generated), `SIJILNA_REPO_URL` and `SIJILNA_GIT_REF`
(defaults `git@github.com:mhmd-ashrf-saad/crm-platform.git` and `main`, so a
private clone needs the deploy key described under "What lives where"),
`SIJILNA_HEALTH_ATTEMPTS` / `SIJILNA_HEALTH_INTERVAL`, `SIJILNA_MIN_FREE_MB`,
`SIJILNA_STOP_AFTER_ENV=1` (write and harden `.env`, then stop before building),
and `SIJILNA_ALLOW_MAIN_INSTANCE=1`.

What it does, in order: validate every input → refuse any identity that already
belongs to someone else → clone or reuse the checkout → write `.env` from
`deploy/.env.deploy.example` (fresh `APP_KEY`, fresh database passwords,
`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<hostname>`, a
per-instance `SESSION_COOKIE`) → symlink `deploy/.env` → hand `.env` to
`uid:<deploy user> gid:33` at mode `640` → `docker compose up -d --build` →
health-gate `http://127.0.0.1:<port>/admin/login` for up to six minutes →
prove the running application is in production with debug off and that its
error page carries no stack trace → print every value it resolved.

It refuses, before writing anything, when: a required variable is missing; the
project name is not a valid compose project name; the database name or user
contains anything but `[A-Za-z0-9_]`; the port is not a number in range; the
hostname carries a scheme or a path; `SIJILNA_INSTANCE_DIR` is not an absolute
path or is `/opt/sijilna`; the project name is `sijilna` (the live instance);
`COMPOSE_PROJECT_NAME` is set in the environment (it outranks both
`SIJILNA_PROJECT` and the compose file's `name:`, so it would start a different
project than the one just validated — `unset` it); `KRAYIN_ADMIN_EMAIL` is not a
plain dotted email address (it is the one value written into `.env` that an
operator types freely, and a newline in it would inject a second `.env` line); a
container name, the data volume or the host port is already owned by **another**
compose project, or a container of the same project name was created from a
**different checkout** (ownership is read from the
`com.docker.compose.project` and `com.docker.compose.project.working_dir` labels,
so a stopped container and a bare volume count too); or an existing `.env` records
`APP_DEBUG=true`, an `APP_ENV` other than `production`, or an `APP_FORCE_HTTPS`
that is none of `true`, `True`, `TRUE` or `1` — the spellings the check accepts
as true.

It never runs `docker volume prune`, `docker system prune`, `docker compose
down -v`, or `rm -rf` on a volume, and it never rewrites an existing `.env`: the
`APP_KEY` and the generated database passwords are the ones the existing data
volume was created with.

### Re-running is the update path

Re-running the same command is safe and is how this instance gets new code:
the checkout is reused (never fetched, checked out or cleaned — it may be
running), `.env` is left byte-identical, `storage/installed` keeps the installer
from running again, and the administrator is not recreated. `up -d --build`
rebuilds the image only when its inputs changed; the `app` container is moved
onto the new image explicitly, because on this host's containerd image store
`up -d` alone can leave the old container running (same defect `deploy.sh`
handles — see "The containerd image-store trap").

### The tunnel rule and the DNS record are yours to add

The script does not touch Cloudflare, and must not: the zone and the tunnel
belong to the vault, not to this repository. Two host-level things make
`https://crm-test.mohamedlabs.com` resolve to this instance.

**1. A DNS record.** A proxied `CNAME` for the hostname's first label in zone
`mohamedlabs.com`, pointing at the tunnel:

```text
crm-test  CNAME  9de8ce79-ea20-4cb0-9720-ee5d54272a46.cfargotunnel.com  (proxied)
```

**2. An ingress rule above the catch-all**, in tunnel `homelab-demos`. Order is
the whole semantics — the first rule whose hostname matches wins, and a rule
below `service: http_status:404` never fires:

```yaml
# config.yml form (a config-file-managed tunnel)
  - hostname: crm-test.mohamedlabs.com
    service: http://127.0.0.1:3103
```

```text
# dashboard form (tunnel homelab-demos -> Public Hostnames -> Add a public hostname)
Subdomain:   crm-test
Domain:      mohamedlabs.com
Path:        (empty)
Service:     HTTP
URL:         127.0.0.1:3103
```

This tunnel is managed remotely, so the dashboard is the live surface: adding
the rule there is version 3 of the configuration, and the existing
`crm.mohamedlabs.com` → `http://localhost:3102` and `demo1` rules stay as they
are. `localhost` and `127.0.0.1` are interchangeable here because `cloudflared`
runs on the same host as the containers.

The tunnel forwards plain HTTP, so nginx never terminates TLS; that is why the
script writes `APP_FORCE_HTTPS=true` — without it Laravel emits `http://` asset
URLs and the browser blocks them as mixed content.

### Verify the second instance

```bash
# 1. loopback only, on its own port
ss -ltnp | grep ':3103'                    # 127.0.0.1:3103, never 0.0.0.0:3103

# 2. it answers locally
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3103/admin/login    # 200
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3103/up             # 200

# 3. three containers, and only this instance's. The -p is not optional: an
#    exported COMPOSE_PROJECT_NAME outranks the compose file's name: (and the
#    --env-file value), so a command without -p would show -- or act on -- the
#    wrong project.
sudo docker compose -p sijilna-test --env-file /opt/sijilna-test/app/.env \
  -f /opt/sijilna-test/app/deploy/docker-compose.yml ps

# 4. it did not disturb the live instance: its three containers are untouched
sudo docker inspect -f '{{.Name}} started={{.State.StartedAt}}' \
  sijilna-db sijilna-app sijilna-web

# 5. the tunnel leg, once the rule and the DNS record exist
curl -s -o /dev/null -w '%{http_code}\n' https://crm-test.mohamedlabs.com/admin/login   # 200
curl -s -o /dev/null -w '%{redirect_url}\n' https://crm-test.mohamedlabs.com/          # https://...

# 6. rows, not tables: the installer created one administrator. Same -p rule as
#    step 3: without it an exported COMPOSE_PROJECT_NAME resolves this to the
#    live project, where the `db` service name is the live database.
sudo docker compose -p sijilna-test --env-file /opt/sijilna-test/app/.env \
  -f /opt/sijilna-test/app/deploy/docker-compose.yml exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u"$MYSQL_USER" -N -e "select count(*) from users;" "$MYSQL_DATABASE"'
```

Then log in at `https://crm-test.mohamedlabs.com/admin/login`. A page that
renders is not proof: log in, then open Dashboard and one record.

### What is NOT automated for a second instance

All four of these are per-instance host work, and none of them is done by the
script:

1. **Memory.** This host is 4 GB and one stack measures about 470 MB RSS. A
   second stack is another ~470 MB, and the ceilings in `deploy/docker/` were
   sized for one. Before adding an instance here, lower `pm.max_children` (4) in
   `deploy/docker/php-fpm.conf` **in the repository** and commit it — do not
   hand-edit `/opt/sijilna/app`, a dirty tracked file is a hard stop for
   `deploy.sh` — then rebuild both stacks. The script warns when less than
   `SIJILNA_MIN_FREE_MB` (default 1500) of memory is available; it does not
   refuse, because that judgement belongs to the operator. Watch `free -m`
   after the first boot: swap pressure is what makes the OOM killer choose a
   victim, and the victim can be the live instance's PHP-FPM workers.
2. **Backups.** The nightly restic run stages `/opt/sijilna` and dumps the
   `sijilna` database. It does not know about `/opt/sijilna-test` or
   `sijilna_test`, so a second instance is **not backed up at all** until
   `/opt/homelab/homelab-backup.sh` is extended. Do that before it holds
   anything worth keeping.
3. **The deploy pipeline.** `deploy/deploy.sh` is written for exactly one
   instance: it hardcodes `sijilna-db` / `sijilna-app` / `sijilna-web`, the
   `sijilna-app` image, `HEALTH_URL=http://127.0.0.1:3102/admin/login`, the state
   file `/opt/sijilna/deployed-tag` and a `sijilna-deploy.timer`. A second
   instance has **no automatic deploy**; re-running
   `deploy/provision-instance.sh` is its update path, and it is safe to run at
   any time for exactly the reasons listed under "Re-running is the update
   path". Nothing about `crm.mohamedlabs.com` changes when it runs.
4. **Monitoring.** Uptime Kuma monitor 11 covers `crm.mohamedlabs.com` only. Add
   a monitor for the new hostname if it matters.

### Destroying a second instance, deliberately

There is no non-destructive way to remove an instance: its database lives in
its own named volume. Export what matters first, then — and only then — from the
instance's own checkout:

```bash
cd /opt/sijilna-test/app
sudo docker compose -p sijilna-test --env-file .env -f deploy/docker-compose.yml down -v   # deletes sijilna-test-db-data
```

Never run this (or any other `docker compose` command here) without `-p`: an
exported `COMPOSE_PROJECT_NAME` outranks the compose file's `name:` and the
`--env-file` value, so a `-p`-less command would act on a different project than
the one you are looking at — and `down -v` is the one command that deletes a
volume.

`-v` is the whole point (the volume is the data) and there is no undo. Confirm
the volume name in the command's output before you type it, and never run it in
`/opt/sijilna`: that volume is the live demo's database.

## Mail: sending as a real domain

An instance that cannot send mail still looks healthy: it boots, it logs in, and
every email-shaped feature reports success. What made that possible here was not
one bug but three layers of silence, all now removed:

| Layer | What it did | Where it is fixed |
| ----- | ----------- | ----------------- |
| `MAIL_MAILER=log` in this template | the `log` driver **cannot fail**: the message is written to `storage/logs/laravel.log`, the send returns success, and nothing is delivered | this template now ships `smtp`, and a production instance booted with `log`/`array`/`null` writes a **critical** log line on every boot |
| `MAIL_HOST=mailhog`, a container that was never created | every send was a connection refusal | the development `.env` points at a real relay |
| `catch (\Exception $e) {}` around `Mail::queue()` in `packages/Webkul/Automation/src/Helpers/Entity/*` | the workflow "send email to person / sales owner" action threw the failure away: no log line, no failed job, no session flash | `app/Support/Mail/ReportingTransport` reports the failure at error level *before* re-throwing it, so a swallow can no longer hide it. The vendor files stay untouched |

`storage/logs/laravel.log` is now where a broken transport shows up, at `ERROR`, with
the mail server's own words — the same text `make mail-test` prints.

### Configure a provider

`.env` holds the mail configuration; `deploy/.env.deploy.example` documents each key.
The short version, for Brevo:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=        # Brevo -> SMTP & API -> SMTP: account email, or xxxxxxxx@smtp-brevo.com
MAIL_PASSWORD=        # the SMTP *key* from that screen (xsmtpsib-...), not the account password
MAIL_FROM_ADDRESS=support@mohamedlabs.com
MAIL_DOMAIN=mohamedlabs.com
```

Three things have to be true before a message is accepted, and they fail in this
order — which is why the exact reply matters more than "mail is broken":

1. **The sending IP is allowed to use the key.** Brevo keys can be restricted to
authorized IPs; a connection from anywhere else is refused at `AUTH` time with
`525 5.7.1 Unauthorized IP address`. Fix it at Brevo -> SMTP & API -> SMTP ->
Authorized IPs, adding the instance's public egress IP. Nothing about the
domain matters until this passes.
2. **The credentials are right.** A wrong or revoked key gives
`535 Authentication failed`.
3. **The sender is verified.** `550`/`553` mean `MAIL_FROM_ADDRESS` is not a
verified sender at the provider, or its domain is not authenticated yet (next
section).

Keep `QUEUE_CONNECTION=sync`. With a real queue driver, `Mail::queue()` only
enqueues: a send that fails later lands in `failed_jobs`, which nobody reads, and
the failure stops being loud again.

### Prove delivery

```bash
# from the checkout (reads this checkout's .env)
make mail-test MAIL_TO=you@example.com

# inside the deployed stack (reads /var/www/html/.env)
sudo docker compose -f deploy/docker-compose.yml exec app \
  env MAIL_TO=you@example.com php scripts/mail-test.php
```

A mail `.env` edit is invisible to both commands until the app container is
**recreated** — `restart app` reuses the old environment and `config:clear` does
not touch it. See "A `.env` edit does nothing until the app container is
recreated" under Update before you conclude that the provider is refusing you.
`scripts/mail-test.php` sends through the configured transport, not a fake. It
prints the resolved host, port, encryption and From address, then the SMTP
conversation with the credential lines stripped (verified: the script counts
remaining occurrences of the username and password in its own output and refuses
to call the run clean if either is present), and it exits non-zero when the
server refuses the message. Exit `2` means no valid recipient was given, so
nothing was attempted.

A run that reaches the relay ends with the relay's queue id, for example
`250 2.0.0 Ok: queued as <id>`. A run that does not ends with the server's code:
`525`/`535`/`550` per the list above, or `Connection could not be established`
when nothing is listening / DNS or egress is blocked.

Do not treat a green `make mail-test` as "the client will see it": it proves the
relay accepted the message. Landing in the inbox is the records below.

### Deliverability records at Cloudflare

Measured on `mohamedlabs.com` on 2026-10-23, **after** the domain was
authenticated in Brevo. Brevo created most of the records below itself through
its Cloudflare integration, and its "Authenticate this domain" check reports
every one of them green. What Brevo does **not** touch is an SPF record that
already exists — it will not de-authorize a domain's other senders — so SPF is
the one record a human still changes by hand.

| Record | Created by | State now |
| ------ | ---------- | --------- |
| `brevo-code` TXT at `@` | Brevo | published — proves domain ownership, signs nothing |
| `brevo1._domainkey` CNAME | Brevo | published; target serves a live 2048-bit key |
| `brevo2._domainkey` CNAME | Brevo | published; target serves a live 2048-bit key |
| `_dmarc` TXT | Brevo | published, `p=none`, reports go to Brevo |
| SPF TXT at `@` | a human, by hand | includes `spf.brevo.com` |

**What this does and does not prove.** The only delivery claim proven here is
that the relay accepted a message — an SMTP `250 2.0.0 Ok: queued as <id>`.
These records make inbox placement possible; nobody has yet observed one of these
messages arriving in an inbox, so placement stays unproven until someone reads
the received message's own headers.

Read the current state with:

```bash
dig +short TXT mohamedlabs.com                       # SPF, plus this account's brevo-code TXT
dig +short CNAME brevo1._domainkey.mohamedlabs.com   # b1.mohamedlabs-com.dkim.brevo.com.
dig +short CNAME brevo2._domainkey.mohamedlabs.com   # b2.mohamedlabs-com.dkim.brevo.com.
dig +short TXT _dmarc.mohamedlabs.com                # v=DMARC1; p=none; rua=mailto:rua@dmarc.brevo.com
dig +short MX mohamedlabs.com                        # route1/2/3.mx.cloudflare.net (Email Routing)
```

**1. SPF — add Brevo to the existing record, never replace it.**

| Type | Name | Value |
| ---- | ---- | ----- |
| TXT | `@` | `v=spf1 include:_spf.mx.cloudflare.net include:spf.brevo.com ~all` |

Why: the record lists the servers allowed to send as this domain. The existing
`include:_spf.mx.cloudflare.net` is what authorizes Cloudflare Email Routing's
own outbound mail; `include:spf.brevo.com` adds Brevo's relays. A domain must
have **exactly one** SPF TXT record — a second one is a permanent
`permerror`, and every sender on the domain then fails — so this is an edit of
the existing value, not a new record. On this domain the include is already
present; on a fresh domain, append it. Copy the include Brevo prints in its
"Authenticate this domain" panel if it differs from `spf.brevo.com`.

**2. DKIM — two CNAMEs Brevo delegates, not a TXT at `mail._domainkey`.**

| Type | Name | Value |
| ---- | ---- | ----- |
| CNAME | `brevo1._domainkey` | `b1.mohamedlabs-com.dkim.brevo.com` |
| CNAME | `brevo2._domainkey` | `b2.mohamedlabs-com.dkim.brevo.com` |

For this account Brevo delegates DKIM by **CNAME**, and the target is Brevo's
per-domain name: the domain with its dots replaced by dashes. There is **no**
`mail._domainkey` TXT here, and a record at that name is wrong — an earlier
version of this page told you to publish a `brevo-code:<value>` TXT there, which
signs nothing and only makes a domain check look satisfied for the wrong reason.
The two CNAME targets serve the public half of Brevo's 2048-bit RSA key pair
(`k=rsa;p=MIIB…`, about 430 characters), and Brevo can rotate the key behind the
CNAME without any change in this zone. Do not copy key material into this
repository and do not invent a placeholder: a made-up key fails the signature
check exactly like a missing record. Keep both records **DNS only** (grey cloud)
— a proxied CNAME is answered by Cloudflare's edge, not by Brevo.

**3. DMARC — published at `p=none`, so read the reports.**

| Type | Name | Value |
| ---- | ---- | ----- |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:rua@dmarc.brevo.com` |

Why: it tells receivers what to do when SPF and DKIM disagree with the From
domain, and `rua` sends them aggregate reports. `p=none` only measures — with a
mailbox that also receives forwarded mail, moving straight to `quarantine` or
`reject` can silently bin legitimate messages. Watch the reports for a week or
two, then tighten to `p=quarantine`. The `rua` address here is the one Brevo
publishes for a domain it manages; if you would rather read the reports yourself,
add a second `mailto:` — and that mailbox must exist, because `rua` to a dead
address drops the reports while the record still looks fine.

**DKIM alone satisfies DMARC here, so SPF is reputation, not a blocker.** Brevo
signs with `d=mohamedlabs.com`, and this DMARC record uses the default relaxed
alignment (`adkim=r`). A validating DKIM signature from that domain is therefore
enough on its own: a message whose SPF result is `softfail` — all `~all` can
return for an IP the record does not name — still **passes DMARC**. That is why
the SPF include never surfaced as a failure: it cost an `spf=pass`, not the DMARC
verdict, and nothing is being rejected today for want of it. Add it anyway — it
makes the two signals agree instead of leaving receivers to weigh a softfail —
but treat it as a reputation improvement, not urgent breakage.

**The risk in editing SPF here.** `mohamedlabs.com` already sends mail through
Cloudflare Email Routing (its MX points at `route*.mx.cloudflare.net`). Email
Routing does not need the domain's own SPF to forward, but anything else that
sends as this domain does: an "send as" account in Gmail, a form-to-email
plugin, a printer, `mail()` on another host. Replacing the SPF value instead of
appending to it de-authorizes all of them at once, and the symptom appears in
someone else's inbox days later. Append, keep one record, and note who else was
sending before the change.

**Verify, record by record:**

```bash
# DKIM: both selectors are CNAMEs, and each target serves a live RSA key.
for s in brevo1 brevo2; do
  t=$(dig +short CNAME "$s._domainkey.mohamedlabs.com" | head -1)
  k=$(dig +short TXT "$t" | tr -d '"' | grep -c 'k=rsa;p=')
  printf '%-8s target=%s rsa-key-lines=%s\n' "$s" "${t:-MISSING}" "$k"
done
# want: b1./b2.mohamedlabs-com.dkim.brevo.com. and rsa-key-lines=1 for each

# There must be nothing at mail._domainkey — an answer here is a leftover error.
dig +short TXT mail._domainkey.mohamedlabs.com        # want: no output

# DMARC: published, and still only measuring.
dig +short TXT _dmarc.mohamedlabs.com

# SPF: exactly one record, and it names both includes.
dig +short TXT mohamedlabs.com | grep -c 'v=spf1'     # want: 1
dig +short TXT mohamedlabs.com | grep spf1            # want: both includes, then ~all

# The brevo-code TXT Brevo generated for this account. It is a public DNS value,
# but it identifies the account: read it with this command, never paste it here.
dig +short TXT mohamedlabs.com | grep -o 'brevo-code:[a-f0-9]*'

# MX is still Cloudflare Email Routing — why SPF is appended, never replaced.
dig +short MX mohamedlabs.com
```

Then re-run Brevo's domain check: with these records in place it reports SPF,
DKIM and DMARC green, and its ownership check finds the `brevo-code` TXT. What
remains is an observation, not a record — send `make mail-test` to a mailbox you
can read, open the message source, and look for `spf=pass`, `dkim=pass` with
`d=mohamedlabs.com`, `dmarc=pass`, and for the message sitting in the inbox rather
than in spam. That observed header block is the end of this work; a green Brevo
check on its own is not, and neither is the relay's `250`.

## Update

An update is a **green `main` and nothing else**. A commit on `main` whose checks
all pass gets a `deploy-<sha>` tag from CI, and `sijilna-deploy.timer` on this
host deploys the newest such tag within three minutes -- dump, checkout, build,
health gate, rollback on failure. See "The deploy pipeline" below for the whole
flow and for how to deploy by hand, change the cadence, or turn it off.

The manual path still exists and is what to use when the pipeline itself is the
thing being debugged:

```bash
cd /opt/sijilna/app
git fetch --tags
git checkout --detach deploy-49995a8        # a tag, never a branch
sudo docker compose -p sijilna -f deploy/docker-compose.yml up -d --build
```

Migrations run automatically at container start (`migrate --force`), and the
installer never runs again because `storage/installed` survives in the
bind-mounted `storage/`. Configuration is deliberately **not** cached, but a
`.env` edit still needs the app container **recreated**, not restarted:

```bash
cd /opt/sijilna/app
sudo docker compose -p sijilna -f deploy/docker-compose.yml \
  up -d --force-recreate --no-deps app
```

`restart app` reuses the environment the container was *created* with and
silently ignores the edit -- the single most confusing thing about this stack,
and the subject of "A `.env` edit does nothing until the app container is
recreated" below.

**Restarting the stack is safe and idempotent.** Every step in `entrypoint.sh`
checks before it acts: it re-applies `storage/` and `bootstrap/cache/`
ownership, skips `storage:link` when the link exists, re-runs
`package:discover` and `migrate --force` (a no-op when the schema is current),
skips the installer while `storage/installed` exists, and re-seeds the brand
rows idempotently (`sijilna:brand` rewrites the footer credit and creates every
other row only when it is absent). `restart`, `stop && start`, `up -d` and
`up -d --build` therefore all land in the same state; none of them re-runs
`migrate:fresh`. Rebuilding is only needed when the **image** changes (code,
`composer.lock`, `deploy/docker/*`) — a `.env` edit never needs a rebuild, only
an app-container recreate.

### A `.env` edit does nothing until the app container is recreated

`deploy/docker-compose.yml` gives the `app` service `env_file: .env`. Compose
reads that file **once, at container creation**, and copies every key into the
container's process environment. Process environment outranks the `.env` file
Laravel reads, so the running process keeps the values it was created with:

- `restart app` / `docker compose start app` reuse the environment captured at
  creation. The edit is not seen, and nothing warns you.
- `php artisan config:clear` does not help. There is no config cache to clear
  (this stack deliberately runs uncached), and no artisan command can rewrite a
  process environment. It is not a fix, it is a red herring.
- Only **recreating** the container re-reads the file.
- `deploy.sh` recreates `app` only when the **image** changes, so a stale value
  survives restarts, and every later deploy, for as long as the image does not
  move — while the site answers 200 the whole time.

Measured on this host: `.env` said `MAIL_MAILER=smtp` while the running container
still carried `MAIL_MAILER=log` from its creation minutes earlier;
`config:clear` changed nothing and only a recreate fixed it.

**The rule.** After any edit to `/opt/sijilna/app/.env`, recreate `app` with the
project pinned:

```bash
cd /opt/sijilna/app
sudo docker compose -p sijilna -f deploy/docker-compose.yml \
  up -d --force-recreate --no-deps app
```

`--no-deps` is deliberate: the database and nginx stay untouched, so the cost is
the ~3.6 s of 502 measured for an app recreate, not a stack bounce. `-p` is not
decoration either — see the note on project resolution under "Second instance".

**Prove the running container actually has the new value.** Never trust the
file, and never quote the unfiltered environment: it also carries `APP_KEY` and
the database passwords.

```bash
# 1. what compose injected at creation -- grep the keys you changed, never dump
#    the whole list into a ticket or a chat
sudo docker inspect sijilna-app \
  --format '{{range .Config.Env}}{{println .}}{{end}}' \
  | grep -E '^(MAIL_MAILER|MAIL_HOST)='

# 2. what the framework resolves in the live container, as the FPM user
sudo docker compose -p sijilna -f deploy/docker-compose.yml exec -T app \
  setpriv --reuid=www-data --regid=www-data --clear-groups \
  php artisan tinker --execute="echo config('mail.default'), PHP_EOL;"

# 3. the file itself, for the components that read it directly
sudo docker compose -p sijilna -f deploy/docker-compose.yml exec -T app \
  setpriv --reuid=www-data --regid=www-data --clear-groups \
  php -r 'echo preg_match("/^MAIL_MAILER=(.*)$/m", file_get_contents("/var/www/html/.env"), $m) ? trim($m[1]) : "missing", PHP_EOL;'
```

Step 1 is what the container was created with, step 2 is what the application
sees. If 1 and 2 disagree, the container was not recreated. A repo-side edit to
`deploy/.env.deploy.example` reaches no running instance at all: it is the
template a *new* instance's `.env` is written from, never a live config file.

## The deploy pipeline (green `main` -> tag -> timer)

Deploying is not something a human does any more. A commit is deployable when CI
says so and not before, and the host **pulls**: nothing on GitHub can reach into
this box, so there is no deploy credential in CI and no SSH key on a runner.

```text
  push to main
      |
      | .github/workflows/deploy-tag.yml        GitHub Actions
      |   woken by workflow_run for CI, Linting Tests, Admin | Playwright
      |   Tests and Skills Consistency. Refuses to tag until EVERY check-run
      |   on the commit is completed and green, and no workflow run for the
      |   commit is still in flight. Creates the lightweight tag
      |   deploy-<first 7 characters of the sha>. Idempotent.
      v
  deploy-49995a8          a ref on origin -- that is the entire CI-side effect
      |
      | sijilna-deploy.timer                  this host, every 3 minutes
      v
  /opt/sijilna/deploy.sh
      1  flock     one run at a time; a second run exits, it does not queue
      2  fetch     git fetch --tags, then the newest deploy-* tag reachable
                   from origin/main. Not newer than the state file -> exit 0
                   without writing a log line. That is the 3-minute path.
      3  dirty     tracked local modifications -> stop loudly. Never resets.
      4  dump      mysqldump inside sijilna-db -> /opt/sijilna/backups/.
                   No dump, no deploy: the restore point comes first.
      5  deploy    git checkout --detach <tag>; rebuild only when the image
                   inputs changed; docker compose up -d; then force-recreate
                   `app` if its image is not what sijilna-app:latest is.
      6  gate      HTTP 200 from http://127.0.0.1:3102/admin/login, 60 probes
                   2 s apart, and all three containers running (db healthy)
      7  either    commit: state file = the tag, runner refreshed, exit 0
         or        rollback: previous ref + previous image, restore the
                   pre-deploy dump if this deploy added migrations, re-verify,
                   one Telegram alert, exit 1
```

### What lives where

| Thing | Path | What it is |
| ----- | ---- | ---------- |
| the tag | `origin`, `deploy-<sha>` | the only thing that makes a commit deployable; nothing else publishes |
| the state file | `/opt/sijilna/deployed-tag` | one line, the tag currently deployed. A run whose target equals it exits in milliseconds |
| the runner | `/opt/sijilna/deploy.sh` | installed copy of `deploy/deploy.sh`, `0750 mohamed:`, refreshed from the checkout after every successful deploy |
| the service | `/etc/systemd/system/sijilna-deploy.service` | `Type=oneshot`, `User=mohamed`, `TimeoutStartSec=1800` |
| the timer | `/etc/systemd/system/sijilna-deploy.timer` | `OnBootSec=3min` + `OnUnitInactiveSec=3min` |
| the lock | `/opt/sijilna/deploy.lock` | `flock -n`; a second run exits instead of queueing |
| the dumps | `/opt/sijilna/backups/sijilna-<utc>-pre-<tag>.sql` | `0600`, last 10 kept, written before anything is touched |
| the log | `/var/log/sijilna-deploy.log` | `0640 mohamed:adm`, size-capped in place, credentials masked |
| the deploy key | `/opt/sijilna/.ssh/sijilna-deploy` | ed25519, registered on the repo as a **read-only** deploy key. Deliberately not in `~/.ssh` |
| the alert | Telegram, credentials from `/opt/homelab/backup.env` | one message per failure; the same reason is suppressed for 30 minutes |

The deploy key is read-only on purpose: it can fetch and it cannot push, so a
compromised host cannot rewrite `main`. It is pinned in the checkout as well as
in the runner, so a plain `git fetch` in `/opt/sijilna/app` works for `mohamed`
without any environment:

```bash
cd /opt/sijilna/app
git config --get core.sshCommand     # ssh -i /opt/sijilna/.ssh/sijilna-deploy ...
```

`deploy.sh` also exports the same thing as `GIT_SSH_COMMAND`, so the key keeps
working even if that config line is lost.

### What the drills on 2026-10-20 measured

| Drill | Result |
| ----- | ------ |
| tag -> timer -> deploy, docs-only tag | 2 s end to end: no rebuild, no container restart, health gate green on probe 1 |
| full rebuild (`FORCE_BUILD=1`, `composer install` re-run) | 366 s and 500 s in two runs, and the site kept answering the whole time: the build happens before anything is replaced |
| `app` container recreate | 3.6 s of 502 at 250 ms probe resolution, no gap above ~1 s; `sijilna-db` and `sijilna-web` untouched |
| forced health-gate failure, deploy of a newer tag | gate fails on probe 3/3; rollback to the previous tag **and** the previous image in 7 s; state file unchanged; exit 1; one Telegram message |
| rollback whose injected failure also breaks the rollback gate | logged as `rollback: the health gate still fails` and alerted; exit 1. It does not pretend to have succeeded |
| re-run with nothing new | exit 0 in 2.5 s, **no log line written**, no dump, no restart (`docker inspect .State.StartedAt` identical before and after) |
| `refresh_runner` | the installed copy is replaced by the checkout's copy, so the next run executes the newer script |
| admin session after all of that | real cookie-jar login: `POST /admin/login` 302 -> `https://.../admin/dashboard` 200, 519 KB dashboard, authenticated `GET /admin/login` bounces to the dashboard |

### Why the runner is an installed copy, not the file in the checkout

The timer runs `/opt/sijilna/deploy.sh`, and that is not laziness. The checkout
is what the script rewrites: after it checks out an older tag, `deploy/deploy.sh`
may not exist at all — every tag published before that file landed is like that,
including `deploy-7e8e64a` and `deploy-49995a8`. A runner that lives inside the
thing it rewrites can be deleted by its own successful run. So the repo copy is
the source of truth, the installed copy is what runs, and `refresh_runner()`
installs the checkout's copy (atomically, only if `bash -n` accepts it) after a
deploy that succeeds. A change to `deploy/deploy.sh` therefore takes effect on
the *next* run.

### Rebuilding: only when it can possibly matter

`docker compose build` is **skipped** when nothing inside the image's build
context changed between the deployed ref and the target tag. That is not an
optimisation for its own sake — measured on this host, a rebuild that has to
re-run `composer install` (because `COPY . .` sits before it in
`deploy/docker/php.Dockerfile`) takes **six to eight minutes** (366 s and 500 s
in two measured runs), while a rebuild with an identical context finishes in
**about three seconds** on the layer cache. A docs-only commit deploy should not
cost that much network work to produce the same bytes.

The classification is conservative by design: paths matching
`NON_IMAGE_GLOBS` in the script (mirroring `.dockerignore`, plus every `*.md`)
are "not an image input", and **everything else counts as changed** (or was built
the same way before). A false "changed" costs a cached rebuild; a false
"unchanged" would leave the old code running. `FORCE_BUILD=1` overrides it
either way.

The two lists are kept in step, and both carry the deploy-only files under
`deploy/`: `deploy/deploy.sh`, `deploy/docker-compose.yml`,
`deploy/provision-instance.sh`, `deploy/README.md` and every other `*.md`.
Nothing in the image reads them — `php.Dockerfile` runs `COPY . .`, but no PHP
code opens a compose file, the runner or a provision script — so an ops-only
commit (a compose comment, a provisioner tweak, a docs fix) deploys in seconds
instead of costing a `composer install`. `deploy/docker/*` stays an image input
on purpose: the Dockerfile copies `php.ini`, `php-fpm.conf` and `entrypoint.sh`
into the image, and `docker-compose.yml` bind-mounts `nginx.conf` into `web`.
When you move a path between the two lists, move it in **both** the script and
`.dockerignore`; one without the other is worse than neither.

### Rollback, and why it is not a rebuild

Before the forward step the script tags the running image aside:

```bash
docker tag "$(docker inspect -f '{{.Image}}' sijilna-app)" sijilna-app:pre-deploy
```

On failure it checks the previous ref out, points `sijilna-app:latest` back at
`sijilna-app:pre-deploy`, brings the stack up, and only rebuilds if that tag is
gone. A rollback therefore costs seconds, not the six minutes a rebuild would —
which matters, because the moment a rollback runs is the moment the site is
already unhealthy.

If the failed deploy **added migration files**, the rollback also restores the
dump taken before it and restarts `app`, because the new code has already run
`migrate --force`. A dump that will not restore turns the rollback into a loud
failure rather than a silent half-migrated state. The database volume is never
touched by a rollback: `down -v`, `volume prune` and `rm -rf` do not appear in the
script at all.

### Deploy now, without waiting for the timer

```bash
# the same run the timer does, but immediately
sudo systemctl start sijilna-deploy.service
systemctl status sijilna-deploy.service --no-pager       # oneshot, so it blocks

# by hand, with the output in front of you
/opt/sijilna/deploy.sh

# deploy the newest tag again even though the state file already names it --
# the "something is wrong and I want the whole flow re-run" button
/opt/sijilna/deploy.sh --redeploy

# ...and rebuild the image while doing it
FORCE_BUILD=1 /opt/sijilna/deploy.sh --redeploy

# a drill: force the health gate to fail (any URL that is not 200 will do)
HEALTH_URL=http://127.0.0.1:3102/admin/dashboard HEALTH_ATTEMPTS=3 \
  /opt/sijilna/deploy.sh --redeploy

tail -f /var/log/sijilna-deploy.log
journalctl -u sijilna-deploy.service -n 50 --no-pager
```

`/opt/sijilna/app/deploy/deploy.sh` is the *same script* and works too — it
re-executes itself from a private copy first, so it cannot be corrupted by the
`git checkout` it is about to do — but the installed path is the one that cannot
disappear, so prefer it.

### Change the cadence, or turn automation off

```bash
sudo systemctl edit sijilna-deploy.timer      # drop-in, no file to hand-edit
# [Timer]
# OnUnitInactiveSec=10min

sudo systemctl daemon-reload
sudo systemctl restart sijilna-deploy.timer

# stop deploying entirely (nothing is lost; the state file and tags stay)
sudo systemctl stop sijilna-deploy.timer
sudo systemctl disable sijilna-deploy.timer   # and not on the next boot either
```

`OnUnitInactiveSec` rather than `OnCalendar`: the next run starts three minutes
after the previous one *finished*, so a six-minute rebuild or a rollback can
never overlap itself or stack up missed ticks.

### The thing that bites: only tags deploy

**A red commit is undeployable, by design.** The timer deploys tags and nothing
else, so if `main` goes red, the host keeps serving the last green tag and CI
never publishes a new one. That is the whole point — but it has three
consequences worth knowing before they surprise you:

1. **Nothing happens after a red push.** No tag, no deploy, no alert: the deploy
   pipeline never wakes up, so the site is not "broken", it is *unchanged*. Look
   at the Actions tab, not at the server.
2. **A docs-only commit still needs its checks.** A commit that only touches
   `docs/` or `.agents/` still has to pass CI before it gets a tag, because the
   gate is "every check-run on the commit is green", not "every check-run that
   ran was green". (`Skills Consistency` is path-filtered, so for most commits it
   does not run at all — that is why the gate accepts a workflow that never ran,
   but not one that ran and failed.)
3. **You cannot hotfix the server.** Pushing a tag by hand is possible
   (`gh api --method POST repos/.../git/refs -f ref=refs/tags/deploy-<sha> -f
   sha=<sha>`), but it deliberately bypasses the gate. If you need to un-break a
   live demo *now*, deploy the last good tag by hand
   (`git checkout --detach deploy-<sha>` then `up -d`) and let the next green
   commit catch up. Do not move a published `deploy-*` tag: the workflow refuses
   to (`refusing to move a published deploy tag`), and the host's
   `git fetch --tags` would silently keep serving the old commit otherwise.

A tag that fails the health gate behaves the opposite way: it stays the newest
tag, so the timer re-tries it every three minutes and the same alert is
suppressed for 30 minutes between messages. To stop that, either fix forward with
a green commit (whose newer tag wins) or `sudo systemctl stop sijilna-deploy.timer`.

### Two host permissions the pipeline needs, and why

Both are one-time host state, both are deliberate, and both are narrower than the
"sud" they replace.

**`.env` is `mohamed:www-data 0640`.** `docker compose` must interpolate
`deploy/.env` (a symlink to `../.env`) as `mohamed`, and the container's worker
(uid 33) must be able to read the same file — the installer reads it directly with
`file()`. Owner `mohamed`, group `www-data`, mode `0640` satisfies both without
making the file world-readable. `33:33 0600`, which this README used to prescribe,
works for the container and makes `docker compose` fail with
`open /opt/sijilna/app/deploy/.env: permission denied`.

```bash
sudo chown mohamed:www-data /opt/sijilna/app/.env
sudo chmod 640 /opt/sijilna/app/.env
```

**`/opt/homelab/backup.env` is `root:adm 0640`.** The alert reuses the homelab's
Telegram bot rather than introducing a second set of credentials, so `mohamed`
must be able to source that file. `adm` is the log-reading group and `mohamed` is
already in it. This grants no new privilege: `mohamed` has passwordless `sudo` on
this host and could already read the file, or anything else, at any time — and
`root` keeps its access, so `homelab-backup.service` is unaffected.

```bash
sudo chown root:adm /opt/homelab/backup.env
sudo chmod 640 /opt/homelab/backup.env
```

### The containerd image-store trap

`docker compose up -d` on this host does **not** reliably recreate a container
whose image was rebuilt. Docker 29 runs the containerd image store: compose
records the image a container was created from as a *manifest-list* digest, and a
locally built image that was never pushed has no such digest to compare against,
so `up -d` reports `Container sijilna-app Running` while the container is still
executing the previous code — and answering 200, which no health check can tell
apart from a good deploy. Measured here: the container was on image `e83c169`
while `sijilna-app:latest` was `3036801`, and `up -d --dry-run` still said
`Running`.

`deploy.sh` therefore compares `docker inspect -f '{{.Image}}' sijilna-app` with
`docker image inspect -f '{{.Id}}' sijilna-app:latest` and, when they differ,
runs `up -d --force-recreate --no-deps app`. `--no-deps` keeps the database and
nginx out of it, so the price is one `app` restart: **502 for under four seconds**
at 250 ms probe resolution, with no single gap above about one second — that is
`php-fpm` coming back, not `nginx*`, and `sijilna-db` is never restarted at all.

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
| **500 on PDF export while the UI is in Arabic** (dashboard report, quote PDF) | mPDF serves the RTL locales and mkdirs `mpdf/` and `mpdf/ttfontdata` under its default `tempDir` `vendor/mpdf/mpdf/tmp`, which `composer install` leaves as `root:root` while FPM runs as uid 33 | The image now creates and chowns it; for a container built before that fix the stopgap is `docker exec -u root sijilna-app sh -lc 'mkdir -p /var/www/html/vendor/mpdf/mpdf/tmp && chown -R 33:33 /var/www/html/vendor/mpdf/mpdf/tmp'`, which is lost on the next recreate. The English branch uses dompdf and is unaffected. |
| **500 on every page** | Empty `APP_KEY`, unwritable `storage/`, or a database error | `docker compose logs app \| head -40`, then `storage/logs/laravel.log` on the host. The entrypoint refuses to boot with an empty `APP_KEY` and says so; permission errors are `chown -R 33:33 storage bootstrap/cache`. |
| **`app` restart-loops, log says `.env: Failed to open stream: Permission denied`** | `.env` is unreadable by uid 33 — the installer reads it with `file()` while Laravel boots from the container environment | `sudo chown mohamed:www-data /opt/sijilna/app/.env; sudo chmod 640` — the container reads it through group `www-data`. See "The .env must belong to the deploy user AND to uid 33". |
| **A `.env` edit "took" but the app still uses the old value** (`MAIL_MAILER` still `log`, `APP_URL` still old, a new credential still rejected) | `env_file: .env` is read at container **creation** and becomes process environment, which outranks the `.env` file Laravel reads; `restart` reuses the old environment and `config:clear` cannot fix process env | Recreate the container: `sudo docker compose -p sijilna -f deploy/docker-compose.yml up -d --force-recreate --no-deps app`, then confirm with `docker inspect sijilna-app --format '{{range .Config.Env}}{{println .}}{{end}}' \| grep ^MAIL_MAILER=`. See "A `.env` edit does nothing until the app container is recreated". |
| **Deploy log says `instance-identity key(s) present in the live deployment, refusing to deploy: SIJILNA_… in /opt/sijilna/app/.env`** | A second-instance `.env` (or a stray `SIJILNA_*` key) reached the live environment. Those keys re-resolve the project, containers, image, volume and port, so `up -d` would stand up an empty stack beside the real one | Comment out / delete the named keys — the live instance is defined by their absence — then `/opt/sijilna/deploy.sh --redeploy`. The refused run changed nothing: it refuses before the fetch, the dump and the checkout. See "Second instance". |
| **Deploy fails with `open /opt/sijilna/app/deploy/.env: permission denied`** | `.env` is `www-data:www-data 0600`, so `docker compose` cannot interpolate it as `mohamed` | `sudo chown mohamed:www-data /opt/sijilna/app/.env; sudo chmod 640`, then `/opt/sijilna/deploy.sh --redeploy` |
| **A deploy "succeeds" but the old code is still running** | `docker compose up -d` did not recreate the container after a rebuild (containerd image store) | `deploy.sh` handles it with an explicit `--force-recreate app`; if you see it after a manual `up -d`, compare `docker inspect -f '{{.Image}}' sijilna-app` with `docker image inspect -f '{{.Id}}' sijilna-app:latest` |
| **Mixed content / forms post to `http://`** | `APP_FORCE_HTTPS` missing | The tunnel forwards plain HTTP, so Laravel must be told. Set `APP_FORCE_HTTPS=true` in `.env`, then **recreate** `app` (`up -d --force-recreate --no-deps app`, see "A `.env` edit does nothing until the app container is recreated") — a `restart` keeps the old value. |
| **Login works on `127.0.0.1:3102`, not through the tunnel** | Cookies/URLs bound to the wrong host | `APP_URL=https://crm.mohamedlabs.com`, `SESSION_COOKIE=sijilna_session`, and `APP_FORCE_HTTPS=true`; then recreate `app`, not merely restart it. |
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
