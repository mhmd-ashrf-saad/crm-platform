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

Measured on `mohamedlabs.com` on 2026-10-23 — this is the starting state, and it
is **not authenticated for Brevo**:

```bash
dig +short TXT mohamedlabs.com                     # "v=spf1 include:_spf.mx.cloudflare.net ~all"
dig +short TXT mail._domainkey.mohamedlabs.com     # (nothing)
dig +short TXT _dmarc.mohamedlabs.com              # (nothing)
dig +short MX  mohamedlabs.com                     # route1/2/3.mx.cloudflare.net (Email Routing)
```

Three records are needed. Until all three exist, a verified sender still gets
filtered by Gmail, and Brevo's own "Authenticate this domain" check stays red.

**1. SPF — add Brevo to the existing record, never replace it.**

| Type | Name | Value |
| ---- | ---- | ----- |
| TXT | `@` | `v=spf1 include:_spf.mx.cloudflare.net include:spf.brevo.com ~all` |

Why: the record lists the servers allowed to send as this domain. The existing
`include:_spf.mx.cloudflare.net` is what authorizes Cloudflare Email Routing's
own outbound mail; `include:spf.brevo.com` adds Brevo's relays. A domain must
have **exactly one** SPF TXT record — a second one is a permanent
`permerror`, and every sender on the domain then fails — so this is an edit of
the existing value, not a new record. Copy the include Brevo prints in its
"Authenticate this domain" panel if it differs from `spf.brevo.com`.

**2. DKIM — one record Brevo generates per domain.**

| Type | Name | Value |
| ---- | ---- | ----- |
| TXT | `mail._domainkey` | `brevo-code:<value Brevo generates>` |
| TXT | `mail2._domainkey` | `brevo-code:<value Brevo generates>` |

The value cannot be written here, and must not be invented: it is a key pair
whose private half Brevo holds, so a placeholder simply fails the signature
check. Copy both records exactly from Brevo -> Senders & IPs -> Domains ->
`mohamedlabs.com` -> Authenticate -> DKIM, keeping whatever selector names Brevo
shows (current accounts use `mail._domainkey` and `mail2._domainkey`). Set them
as **DNS only** if the toggle appears: a proxied record is not served as TXT.

**3. DMARC — start at `p=none` and read the reports.**

| Type | Name | Value |
| ---- | ---- | ----- |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:dmarc@mohamedlabs.com; adkim=r; aspf=r; pct=100` |

Why: it tells receivers what to do when SPF and DKIM disagree with the From
domain, and `rua` sends them aggregate reports. `p=none` only measures — with a
mailbox that also receives forwarded mail, moving straight to `quarantine` or
`reject` can silently bin legitimate messages. Watch the reports for a week or
two, then tighten to `p=quarantine`. `dmarc@` must be a mailbox that exists;
`rua` to a dead address means the reports are dropped and the record looks fine.

**The risk in editing SPF here.** `mohamedlabs.com` already sends mail through
Cloudflare Email Routing (its MX points at `route*.mx.cloudflare.net`). Email
Routing does not need the domain's own SPF to forward, but anything else that
sends as this domain does: an "send as" account in Gmail, a form-to-email
plugin, a printer, `mail()` on another host. Replacing the SPF value instead of
appending to it de-authorizes all of them at once, and the symptom appears in
someone else's inbox days later. Append, keep one record, and note who else was
sending before the change.

**Verify the records propagate:**

```bash
for n in @ mail._domainkey mail2._domainkey _dmarc; do printf '%s: ' "$n"; dig +short TXT "$n.mohamedlabs.com" | tr '\n' ' '; echo; done
```

Then re-run Brevo's domain check until SPF and both DKIM selectors are green, and
send one more `make mail-test`. A message that reaches Gmail's inbox — not spam —
with `spf=pass` and `dkim=pass` in its headers (Gmail: "Show original") is the
end of this work; anything less is a record still missing.

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
are "not an image input", and **everything else counts as changed**. A false
"changed" costs a cached rebuild; a false "unchanged" would leave the old code
running. `FORCE_BUILD=1` overrides it either way.

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
| **Deploy fails with `open /opt/sijilna/app/deploy/.env: permission denied`** | `.env` is `www-data:www-data 0600`, so `docker compose` cannot interpolate it as `mohamed` | `sudo chown mohamed:www-data /opt/sijilna/app/.env; sudo chmod 640`, then `/opt/sijilna/deploy.sh --redeploy` |
| **A deploy "succeeds" but the old code is still running** | `docker compose up -d` did not recreate the container after a rebuild (containerd image store) | `deploy.sh` handles it with an explicit `--force-recreate app`; if you see it after a manual `up -d`, compare `docker inspect -f '{{.Image}}' sijilna-app` with `docker image inspect -f '{{.Id}}' sijilna-app:latest` |
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
