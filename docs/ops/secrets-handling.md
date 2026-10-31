# Secrets — what this product holds, who can read it, and how to rotate it

> **Status:** internal runbook for operator review. **Not shareable with a client**
> as written: §1 and §4 describe the operator's own custody, and §6 is meant to be
> read at 02:00 by one tired person.
> **Applies to:** every instance of this product — a client instance, the vendor's
> own demo, and a development checkout. Where the three differ, the difference is
> named rather than smoothed over.
> **Companions:** [incident-response.md](incident-response.md) (what happens when it
> is bad, and §9 for the security case), [security-review.md](security-review.md)
> (F8, F15, F16, F17 are the findings this file turns into a procedure),
> [support-sla.md](support-sla.md) §6 (what a client must never send us),
> [../legal/dpa.md](../legal/dpa.md) §7 (breach notification),
> [../legal/data-residency.md](../legal/data-residency.md) §3–§4 (access and
> backups).
> **Canonical detail this file does not restate:** `deploy/README.md` (deploy,
> `.env` ownership, the second-instance recipe, the recreate command that makes an
> `.env` edit take effect), `scripts/README.md` (provisioning and the
> installer's key rotation), `.agents/context/operations/provisioning.md`.

## 1. What this product holds

Every secret below is an **environment key read by `config/*.php` or a package
config**, or a credential stored in the database. Nothing here is inferred from a
generic Laravel template: the list was built by grepping every `env()` call in
`config/`, `app/` and `packages/*/*/src|config`, then checking which of those keys
this stack actually sets.

### 1a. Present on every instance, and required

| Secret | Where it lives | Who can read it | What it unlocks if leaked |
| --- | --- | --- | --- |
| **`APP_KEY`** | `.env`, one per instance | root on the host, uid 33 (FPM) through the read-only mount, the deploy user that owns the file — and anyone holding a backup snapshot (§1e) | Decrypts every encrypted column and every encrypted cookie. That is the whole loss: a captured cookie becomes readable, and with an `APP_KEY` an attacker can craft a cookie — but a session cookie still has to name a session that exists in this instance's store, so the practical damage is the encrypted data and the cookies, not a free login as anyone |
| **`DB_PASSWORD`** (the application's MySQL user) | `.env`; the same value is `MYSQL_PASSWORD` in the `db` container's environment | root, uid 33, the deploy user; **inside** the `db` container it is in the process environment | Full read/write on this instance's database: every lead, contact, quote and activity, and the hashed administrator password |
| **`DB_ROOT_PASSWORD`** | `.env`; `MYSQL_ROOT_PASSWORD` in the `db` container | as above, plus whoever runs a restore or a dump | The database, every schema on that server, and `FILE`/`PROCESS` privileges — a dump-and-exfiltrate or a drop-everything credential |
| **`MAIL_USERNAME` / `MAIL_PASSWORD`** (outgoing SMTP) | `.env` only; nothing in the database | root, uid 33, the deploy user | Sending mail **as the verified sending domain**: phishing that passes SPF, DKIM and DMARC, plus the provider account's quota and reputation |
| **The administrator password** — not an env key after first boot | hashed (bcrypt, cost 10) in `users.password`; the **plaintext** is held by whoever typed it | the hash: anyone with database access. The plaintext: the operator and the client's administrator | The admin UI, and through it every record and every configuration screen. There is **no MFA and no invitation flow** ([security-review.md](security-review.md) F10) — this one password is the whole front door |

`DB_PASSWORD` and `DB_ROOT_PASSWORD` are consumed by `deploy/docker-compose.yml`,
and deliberately **not** through an `env_file` for the `db` service — the compose
file says why at `deploy/docker-compose.yml:59-64`: mounting the application
environment into MySQL "would hand MySQL APP_KEY and the first-boot administrator
password for no reason". Only the four `MYSQL_*` names are declared
(`deploy/docker-compose.yml:66-69`).

`APP_KEY` is not optional. The container entrypoint reads it out of the mounted
`.env` itself, and exits 1 with `FATAL: APP_KEY is empty` rather than let a stack
start on an empty key and silently invalidate everything on the next restart
(`deploy/docker/entrypoint.sh:41-66`, the `FATAL` line at `:57`). The mount is
read-only, so the container cannot repair it.

### 1b. Present only if the feature is switched on

Each of these is read by a config file, is empty in both templates, and is
**not** set on the vendor's own instance today. They are listed so that a rotation
or a leak does not get missed because the operator forgot one was live.

| Secret | Read by | When it becomes live |
| --- | --- | --- |
| **`IMAP_USERNAME` / `IMAP_PASSWORD`** | `config/imap.php` (`IMAP_*` keys) via `webklex/laravel-imap` | When inbound mail is pulled over IMAP instead of the webhook. The templates ship a placeholder host and no credential (`.env.example:83-88`, `deploy/.env.deploy.example:146-151`) |
| **`MAIL_RECEIVER_INBOUND_TOKEN`** | `config/mail-receiver.php:35`, enforced by `VerifyInboundEmailToken` | When a mail provider posts to `POST /{admin}/mail/inbound-parse`. Unset, the endpoint answers **503**, not 403, so the provider retries instead of dropping the message (`packages/Webkul/Admin/src/Http/Middleware/VerifyInboundEmailToken.php:44-55`) |
| **`GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`** | `config/services.php:34-36` | When Google Contacts / Google login is configured. The client factory treats the feature as off until all three of id, secret and redirect URI are filled (`packages/Webkul/GoogleContact/src/Services/GoogleClientFactory.php:25-27`) |
| **`AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`** | `config/filesystems.php` | Only if `FILESYSTEM_DISK` is moved off its default. The default is `public` (`config/filesystems.php:16`), so the AWS keys are dead weight today |
| **`REDIS_PASSWORD`**, **`MEMCACHED_PASSWORD`** | `config/database.php`, `config/cache.php` | Only if a Redis or Memcached container is added. Both templates run `CACHE_DRIVER=file` and `SESSION_DRIVER=file` and deploy no such container |
| **`PUSHER_APP_SECRET`**, **`REVERB_APP_SECRET`**, **`ABLY_KEY`** | `config/broadcasting.php:64-66` and the pusher block | Only if realtime broadcasting is deployed. It is not: `NOTIFICATION_DRIVER=polling` and the template says so in a comment above the empty Reverb block |
| **`MAILGUN_SECRET`**, **`POSTMARK_TOKEN`**, **`LOG_SLACK_WEBHOOK_URL`**, **`PAPERTRAIL_URL`/`PORT`** | `config/mail.php`, `config/logging.php:59` | Only if that transport is chosen. None is set in either template. A Slack webhook URL is a credential: it is a bearer token in the path |

### 1c. Credentials that are not env keys, and are easy to forget

- **The Google OAuth tokens.** `google_contact_accounts.access_token` and
  `refresh_token` are cast `encrypted`
  (`packages/Webkul/GoogleContact/src/Models/GoogleContactAccount.php:39-40`) and
  marked `$hidden`, so they sit **in the database, encrypted with `APP_KEY`**. They
  are a live credential to the client's Google account and they inherit `APP_KEY`'s
  blast radius (§3c). Revoking them happens at Google, not here.
- **The first-boot administrator pair, `KRAYIN_ADMIN_EMAIL` /
  `KRAYIN_ADMIN_PASSWORD`.** Read by the container and by
  `scripts/create-admin.php`, never by the application. They are bootstrap
  credentials, and the template instructs deleting both lines after the first
  successful boot (`deploy/.env.deploy.example:181-187`) precisely so a password
  does not keep sitting in a file that the container goes on reading.
- **The MySQL users themselves.** The `MYSQL_*` environment is applied **only when
  MySQL initialises an empty data volume** — the template's own words:
  "`DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` are also what the `db` service and
  its healthcheck are created from, and `DB_ROOT_PASSWORD` is consumed by MySQL's
  first initialisation only" (`deploy/.env.deploy.example:67-70`). This is the single
  most important operational consequence in this document: **editing `DB_PASSWORD`
  in `.env` does not change the database password.** §3d is the procedure that does.

### 1d. Things that look like secrets and are not

Recorded because treating them as credentials produces false confidence, and one
of them was assumed to be a credential before this document was written.

| Not a secret | Why |
| --- | --- |
| **`DB_USERNAME`, `DB_DATABASE`, `DB_ROOT`'s username** | Identifiers. `deploy/provision-instance.sh` even restricts them to `[A-Za-z0-9_]`, which is a shell-safety rule, not a secrecy one |
| **`APP_ADMIN_PATH`** | The admin path segment (`config/app.php:67`, default `admin`). Obscurity only — [security-review.md](security-review.md) F11 already records that "not in the menu" is not "not accessible", and the monitor polls `/admin/login` by name |
| **`MAIL_FROM_ADDRESS`, `MAIL_DOMAIN`** | Public by design: they are the SPF/DKIM/DMARC domain |
| **`GOOGLE_CLIENT_ID`** | Sent to Google in a browser redirect. The **secret** half is `GOOGLE_CLIENT_SECRET` |
| **`SESSION_COOKIE`** | A per-instance cookie *name*, written by provisioning so two instances on one host do not evict each other's sessions (`config/session.php:130`) |
| **A web form's `form_id`** | **This is the one the bead was wrong about.** This codebase ships **no API key, no client-side token and no shared secret for web forms.** `form_id` is `Str::random(50)` (`packages/Webkul/WebForm/src/Repositories/WebFormRepository.php:44`) and it is *published*: `GET web-forms/forms/{id}/form.js` serves the embed script that contains it, to anyone, with no authentication (`packages/Webkul/WebForm/src/Routes/routes.php:7`, inside a group that carries only `web` and `admin_locale` at `:6`, and the submission route at `:11`). It is an identifier inside a public URL, so it must not be treated as a credential and it buys no protection. What actually limits a form is which attributes the admin placed on it — the controller reduces a submission to exactly those codes (`WebFormController::restrictSubmissionToExposedAttributes`). Spam protection is absent entirely ([security-review.md](security-review.md) F14) |

`git ls-files | grep -E '(^|/)\.env'` returns exactly two files, `.env.example` and
`deploy/.env.deploy.example`, and neither contains a key literal
(`git grep -nI 'base64:'` over both returns nothing). `.env` is ignored
(`.gitignore:1`). Neither template carries a real credential: every password field
is empty on purpose, and the IMAP entries are placeholders
(`.env.example:71-72,81,88`; `deploy/.env.deploy.example:137-138,144,151`).

### 1e. Where the secrets are also *copied*, unintentionally

Three copies exist that are not `.env`:

1. **The database backup.** The nightly restic snapshot of `/opt/sijilna` now
   includes `.env` — deliberately, because `APP_KEY` is what makes a restored
   database readable — next to the SQL dump. So a copy of the backup repository is
   **the data and the key together** (`deploy/README.md:1120-1124`;
   `.agents/context/operations/deployment-homelab.md`, "Open risks" #1;
   [security-review.md](security-review.md) F16). Treat a lost repository copy, or
   the 03:30 laptop copy, as a credential compromise, not just a data leak.
2. **The pre-deploy dumps** in `/opt/<project>/backups/` — unencrypted SQL on the
   same host ([security-review.md](security-review.md) F16).
3. **A restored staging copy.** `restore` instructions copy the whole staged tree,
   `.env` included, onto a fresh target (`deploy/README.md:1126-1134`). A
   staging copy therefore carries production credentials and production data.

**DECISION** — where the per-instance `.env` (or `APP_KEY` alone) is kept as a
recovery copy outside the host, and whether that copy is encrypted. The repository
records the *backup* content but not the custody of an off-host key. This is
adjacent to D3 in [decisions-to-confirm.md](decisions-to-confirm.md) (backup
policy) and must be closed with it, not separately.

## 2. Provisioning a new client instance — its own set, never shared

**One instance, one client, one set.** The provisioning script exists so this is
mechanical rather than remembered: `deploy/provision-instance.sh` writes a fresh
`.env` for a new instance from `deploy/.env.deploy.example` and generates the
per-instance secrets itself.

### 2a. What the script generates, and how

| Value | Generated by | Shape |
| --- | --- | --- |
| `APP_KEY` | `generate_app_key()`, `deploy/provision-instance.sh:267` — `openssl rand -base64 32`, printed as `base64:…` | Byte-for-byte what `php artisan key:generate --show` emits, and generated this way because that command needs `vendor/`, which exists only inside the image |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | `generate_secret()`, `deploy/provision-instance.sh:276` — `openssl rand -hex 24` | Hex, not base64, on purpose: the value lands in `.env`, in `docker compose config` output and in a MySQL CLI, and hex cannot be mangled by any of them |
| `SESSION_COOKIE` | Derived from the instance's own database name | So two instances on one host never share a session namespace |

The `.env` is built with `umask 077` while it already holds the generated
passwords (`deploy/provision-instance.sh:764`), and ends at **mode 640, owned by
the deploy user with group 33** (`deploy/provision-instance.sh:836,841`). Anything
world-readable is wrong; do not widen the mode to "fix" a container that cannot
read it — the fix is the ownership, and `deploy/README.md:92-134` is the long
form of why.

> **A contradiction already in this repository, resolved here.** Two documents give
> different target states for the file: `deploy/README.md:52-66` ends at
> `mohamed:www-data`, mode 640 (and the provisioning script agrees), while
> `.agents/context/operations/deployment-homelab.md:17,93` records mode **600**
> owned by **uid 33**, and [security-review.md](security-review.md) F15 repeats the
> 600 form. Both are non-world-readable and both work — the FPM user needs either
> ownership or group 33. **What the code writes is 640, deploy-user:33.** Use that
> for new instances; leave an existing 600 uid-33 file alone rather than churn it
> during an incident.

Two guards matter when re-running provisioning, and both protect secrets:

- **An existing `.env` is never rewritten.** `adopt_env_file()`
  (`deploy/provision-instance.sh:662`) reads the recorded `APP_KEY`, and if `.env`
  exists with an **empty** key it refuses to generate one: *"a new key makes every
  encrypted column and every session in the existing database unreadable. Restore
  the key from a backup, or start from an empty instance directory."* The same
  function refuses an empty `DB_PASSWORD` or `DB_ROOT_PASSWORD`, because MySQL
  inside the existing volume was initialised with a password an empty one cannot
  connect to.
- **Nothing is taken from `argv`.** Values come from the environment only, because
  a password in `argv` is a password in `ps`, in `/proc/<pid>/cmdline` and in the
  shell history (`deploy/README.md:241-243`).

### 2b. What the operator must supply, per instance

Provisioning generates the key material; it cannot invent a provider relationship.
Per new client instance the operator supplies, and these must be **new for that
instance**:

| Value | Source | Must not be |
| --- | --- | --- |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | The sending provider's SMTP screen | The previous instance's key. The template's own rule: *"One key per instance, revoked in Brevo when the instance is retired"* (`deploy/.env.deploy.example:109`) |
| `MAIL_FROM_ADDRESS`, `MAIL_DOMAIN` | A domain the provider has verified | A domain nobody owns — the template records `no-reply@example.invalid` as the bug this replaced (`deploy/.env.deploy.example:117`) |
| `MAIL_RECEIVER_INBOUND_TOKEN` (if inbound mail is used) | Generated by the operator, long and random | Empty in production, which leaves the webhook at 503 and no mail arrives |
| `IMAP_USERNAME` / `IMAP_PASSWORD` (if IMAP is used) | The client's mailbox provider, usually an app-specific password | The client's **main** mailbox password. An app password is revocable on its own |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` (if Google Contacts is used) | A Google Cloud OAuth client | A shared client across clients — one revocation would break every instance |
| The administrator password | Chosen by the operator, typed into `KRAYIN_ADMIN_EMAIL`/`KRAYIN_ADMIN_PASSWORD` for the first boot, or set later with `make admin` | A recycled password, and **not** left in `.env` after the first boot |

**The never-list for a second instance**, each of which has a named consequence:

- **Never copy the previous instance's `.env`.** Reusing `APP_KEY` means one
  leaked key makes **every** instance's encrypted columns readable and every
  instance's encrypted cookies decryptable (§3c). Reusing `DB_PASSWORD` means one
  database credential opens two databases.
- **Never copy the data volume** to "start from the same data". That is a second
  copy of another client's personal data with no lawful basis, and
  [../legal/dpa.md](../legal/dpa.md) §3 makes every processing act an instruction
  from the controller.
- **Never reuse a sending key or a sending domain** across clients: a spam
  complaint against one client then degrades the other's deliverability, and the
  provider's abuse action hits both.
- Do not fill the `SIJILNA_*` identity keys in on the live instance "for clarity":
  they are absent on purpose, their absence *is* the live identity, and naming them
  differently starts a second stack against a different volume
  (`deploy/README.md:203-212`).

### 2c. Verify the instance's own set before handing it over

```bash
# 1. The key is present, base64, and unique to this instance.
#    Compare prefixes only -- never print a whole key.
for f in /opt/*/app/.env; do
  printf '%s ' "$f"
  sudo sed -n 's/^APP_KEY=//p' "$f" | cut -c1-11
done                                        # every prefix must differ

# 2. The file is not world-readable and the FPM user can read it.
sudo ls -l /opt/<project>/app/.env          # expect 640, deploy-user:33

# 3. The application resolves a key at all (it refuses to boot without one).
sudo docker compose -p <project> -f deploy/docker-compose.yml logs app | head -20

# 4. Two instances never share a database password. Compare digests, never values.
for f in /opt/*/app/.env; do
  printf '%s ' "$f"
  sudo sed -n 's/^DB_PASSWORD=//p' "$f" | sha256sum | cut -c1-16
done                                        # two different digests = two different passwords

# 5. Mail actually sends. A green send is the only proof; a boot is not.
sudo docker compose -p <project> -f deploy/docker-compose.yml exec -T app \
  env MAIL_TO=<a-mailbox-you-can-read> php scripts/mail-test.php
```

`scripts/mail-test.php` (wrapped by `make mail-test`, `Makefile:274`) sends through
the real mailer, prints the SMTP conversation **with the credentials stripped**, and
exits non-zero when the server refuses. Run it **inside** the instance's container
as above — `make mail-test` on the development host tests the development `.env`,
not a client instance.

## 3. Rotation

### 3a. The shape every rotation has

Three steps, in this order, always:

1. **Change it where it is authoritative** — the provider, or MySQL itself. Not in
   `.env` first, except for the two whose authority *is* `.env`: `APP_KEY` (§3c) and
   an administrator password (§3h). Everything else has a second side that has to
   be changed first, and §3g is the one where the two sides disagree for a while and
   mail is lost meanwhile.
2. **Then change `.env`**, and remember that an `.env` edit does nothing until the
   app container is recreated (`deploy/README.md:698`):

   ```bash
   sudo docker compose -p <project> -f deploy/docker-compose.yml \
     up -d --force-recreate --no-deps app
   ```

   `--no-deps` keeps the database and nginx untouched; the cost is the ~3.6 s of 502
   measured for an app recreate, not a stack bounce.
3. **Prove it, from inside the running container, not from the file.** Two checks,
   because the `.env` and the container's injected environment are different
   sources and they can disagree:

   ```bash
   sudo docker inspect <project>-app \
     --format '{{range .Config.Env}}{{println .}}{{end}}' \
     | grep -E '^(MAIL_HOST|MAIL_USERNAME)='      # compose injected what you think
   sudo docker compose -p <project> -f deploy/docker-compose.yml exec -T app \
     setpriv --reuid=www-data --regid=www-data --clear-groups \
     php artisan tinker --execute='echo config("mail.mailers.smtp.username");'
   ```

   **Never quote the unfiltered environment** to a ticket, a chat or a screenshot:
   it also carries `APP_KEY` and the database passwords
   (`deploy/README.md:733-734`).

### 3b. Blast radius, in one table

| Secret | What a rotation breaks, honestly | Downtime |
| --- | --- | --- |
| `APP_KEY` | Encrypted columns become unreadable; every session is invalidated; datagrid ids stop decrypting. **Permanent data loss unless the old key is kept or the data is re-encrypted — and there is no re-encryption command in this repository** | Total, for encrypted data. See §3c |
| `DB_PASSWORD` | The application cannot connect until `.env` and MySQL agree. Every request 500s while they disagree | Seconds if done in the right order; total if done in the wrong one |
| `DB_ROOT_PASSWORD` | Nothing the application uses at request time. Breaks restores, dumps and the documented incident commands unless they read the new value | None for users, if only dumps are affected |
| `MAIL_PASSWORD` | Only mail. The CRM boots and renders; the send fails with `535 Authentication failed`, which is logged at error level | None visible. **Mail failing looks like nothing** |
| `IMAP_PASSWORD` | Only inbound mail polling. With the scheduler running, mail quietly stops arriving | None visible — [support-sla.md](support-sla.md) §4a's silent failure |
| `MAIL_RECEIVER_INBOUND_TOKEN` | The webhook. A mismatch is **403**, so the provider **drops** the message rather than retrying. A blank token is 503, which the provider retries | None visible; mail is lost in the 403 window |
| `GOOGLE_CLIENT_SECRET` | New Google authorisations fail. Existing `access_token`/`refresh_token` rows keep working until the client secret is revoked at Google | One feature |
| Administrator password | That account's login only. Existing sessions are **not** invalidated by a password change (sessions are file-backed and keyed by `APP_KEY`) | None |
| Any provider key (AWS, Pusher, Reverb, Slack) | Only the feature using it — none of which is live today (§1b) | — |

### 3c. `APP_KEY` — the one that can destroy data

**Read this before touching it.** `APP_KEY` is not a "rotate it yearly" secret in
this product, and the repository gives no safe in-place path:

- **The only code that rotates it is the installer.** `krayin-crm:install` calls
  `key:generate` itself (`packages/Webkul/Installer/src/Console/Commands/Installer.php:137`)
  and the very next step is `migrate:fresh` (`:140`). So the supported rotation path
  is also the destructive path. The web installer's key generation is worse: it
  swallows the exception (`packages/Webkul/Installer/src/Helpers/DatabaseManager.php:214-220`),
  so a failure there is silent.
- **There is no re-encryption command.** A repository-wide search for
  `reencrypt|re-encrypt` returns nothing, so nothing in this product walks the
  encrypted columns and re-encrypts them under a new key.
- **What actually breaks, verified by reading the code, not guessed:**
  1. **`google_contact_accounts.access_token` / `refresh_token`** — the only
     `encrypted` casts in the codebase
     (`packages/Webkul/GoogleContact/src/Models/GoogleContactAccount.php:39-40`).
     After rotation these are undecryptable: the rows survive and the tokens are
     unrecoverable. The fix is to re-authorise Google, per connected account.
  2. **Every logged-in session.** The session cookie is encrypted by
     `$middleware->encryptCookies()` (`bootstrap/app.php:20-23` — only `dark_mode`
     and `sidebar_collapsed` are exempt), so after a key change the cookie no
     longer decrypts and every user is logged out on their next request. This is
     the harmless part — people log back in.
  3. **Datagrid ids.** The grid id in a response is
     `Crypt::encryptString(get_called_class())`
     (`packages/Webkul/DataGrid/src/DataGrid.php:549`) and is decrypted on the
     lookup request (`packages/Webkul/Admin/src/Http/Controllers/DataGridController.php:26`).
     A page left open across the rotation gets a decrypt error on its next column
     lookup; reloading the page mints a fresh id. **Nothing is persisted**, so this
     is transient — but it looks like a broken screen and will be reported as one.
  4. **Any backup restored without the matching key** is a site that runs and shows
     garbage ([incident-response.md](incident-response.md) §4 #6, §5).

**The safe order, when rotation is unavoidable** (a leak, or a key that must be
retired):

1. **Copy everything first.** Database dump, `storage/`, and the current `.env`.
   This is [incident-response.md](incident-response.md) §3 and it is not optional:
   after step 3 the old key is the only thing that can read the old data.
2. **Keep the old key, written down, somewhere you can reach it.** A file next to
   the dump is the honest minimum; it is also the discovery risk named in §1e.
3. **Record which Google connections exist** so they can be re-authorised:
   `SELECT id, user_id, email FROM google_contact_accounts;` — then expect each to
   be reconnected.
4. **Set the new key in `.env` and recreate the app container** (§3a). Do not run
   the installer: it also runs `migrate:fresh`.
5. **Verify in this order:** the container boots (`APP_KEY is set (not shown)` in
   its log), an administrator can log in, an encrypted column is still readable —
   or, if the key changed and no re-encryption was done, it is **expected
   unreadable** and the Google accounts are reconnected from step 3.
6. **Warn the client before the window**, because step 4 logs everyone out. This is
   the one rotation that is a maintenance announcement, not an operator action.
7. **Destroy the old key only after the data it protects has been re-created or
   declared lost.** Keeping it is what preserves the option to read the old data;
   destroying it early is irreversible.

> **Reconciliation with [incident-response.md](incident-response.md) §9.** That
> section says rotation "is documented in
> `.agents/context/operations/provisioning.md`". What that file actually contains
> is the *warning* — "the installer rotates `APP_KEY`", "never run the installer
> against a client instance with data" — not a procedure. **This section is the
> procedure.** The two do not disagree; the pointer was to a boundary, not a
> recipe.

**DECISION** — whether `APP_KEY` is rotated at all on a client instance, and under
what trigger (leak only, or a scheduled interval). This repository records rotation
as destructive and gives no safe procedure; if a periodic policy is wanted, it needs
a re-encryption step that does not exist yet, so the honest choice today is
**leak-only**. Nothing in [decisions-to-confirm.md](decisions-to-confirm.md) covers
this; the row needs to be added there.

### 3d. `DB_PASSWORD` and `DB_ROOT_PASSWORD`

The trap, stated once more because it is the one that costs an afternoon:
**`MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD` are honoured only when MySQL first
initialises an empty volume.** Editing `.env` afterwards changes what the
application *tries* to use and nothing about what MySQL *accepts*.

```bash
# 0. Dump first. A mistyped ALTER on the wrong host locks the application out.
#    Command shape: .agents/context/operations/database.md (use --no-tablespaces).

# 1. Change it in MySQL FIRST, as root, inside the db container. Open a session
#    and type the statement at the prompt -- not in the command line, so neither
#    the password nor the root password lands in argv or in the shell history.
sudo docker compose -p <project> -f deploy/docker-compose.yml exec -T db \
  sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root'

#   mysql> SELECT user, host FROM mysql.user;              -- confirm the pattern
#   mysql> ALTER USER '<db-user>'@'<host>' IDENTIFIED BY '<new-password>';
#   mysql> exit

# 2. Then put the same value in .env (DB_PASSWORD=...), and recreate the app.
sudo docker compose -p <project> -f deploy/docker-compose.yml \
  up -d --force-recreate --no-deps app

# 3. Prove it: one HTTP request that touches the database, not just a boot.
curl -s -o /dev/null -w '%{http_code}\n' https://<hostname>/admin/login   # expect 200
sudo docker compose -p <project> -f deploy/docker-compose.yml logs --tail=50 app
```

Notes that matter:

- **Order is not stylistic.** Changing `.env` first means the app 500s on every
  request until MySQL catches up — and during an incident that 500 hides the real
  problem.
- **`DB_ROOT_PASSWORD` rotation has no application impact**, so pick the window
  freely — but then update every documented command that uses it
  ([incident-response.md](incident-response.md) §5) and the backup runbook, which
  reads the password from the container's own environment through `MYSQL_PWD` so it
  never lands in `argv` (`deploy/README.md:1110-1111`).
- **If the `ALTER` changes nothing**, the host pattern was wrong: the MySQL image
  may have created the account as `@'%'` and your instance has it as `@'localhost'`.
  Step 1's `SELECT` is the answer, not a guess.

**DECISION** — whether a client instance keeps a usable `DB_ROOT_PASSWORD` at all,
or rotates it after first initialisation to a value held only in the operator's
store rather than in `.env`. Both are defensible; the repository records neither.
Propose keeping it, because the documented restore path needs it.

### 3e. SMTP credentials

1. **Create the new key at the provider before revoking the old one.** Two valid
   keys briefly is what makes this a no-outage rotation. The template records that
   the provider (Brevo on the vendor's own instance) shows the SMTP key **once**
   and it looks like `xsmtpsib-` plus about 60 characters
   (`deploy/.env.deploy.example:105-109`, `MAIL_HOST=smtp-relay.brevo.com` at
   `:134` for that instance only — a client instance's provider is its own).
2. **Update `MAIL_USERNAME` and `MAIL_PASSWORD` in `.env`** and recreate the app
   container.
3. **Prove delivery with a real send**, not with a boot: the container form in §2c
   (`exec -T app env MAIL_TO=… php scripts/mail-test.php`). Empty or wrong
   credentials do **not** stop the application: the CRM boots and renders, the
   provider answers `535 Authentication failed`, and only mail is broken — by
   design, logged at error level (`.env.example:49-55`,
   `deploy/.env.deploy.example:119-121`).
4. **Revoke the old key at the provider**, after a green send.
5. **If the key was IP-restricted**, re-check the restriction: a rotated key
   restricted to the wrong address fails with `525 5.7.1 Unauthorized IP address`
   (`deploy/README.md:468-470`).

**DECISION** — which SMTP provider and which sending domain each client instance
uses, and who owns that account (the vendor or the client). The repository records
Brevo for the vendor's own demo only; a client's provider is nowhere recorded and
must not be assumed.

### 3f. IMAP credentials

Rotate at the mailbox provider **first** (an app-specific password, if the provider
offers one — never the client's main mailbox password), then `.env`, then recreate
the app container. Verification is not a login test but an arrival test: send one
message to the monitored address and confirm it appears as a lead or a conversation.
**If scheduled tasks are not running, no inbound mail arrives and every screen still
looks healthy** — check the scheduler before blaming the credential
([support-sla.md](support-sla.md) §4a).

**DECISION** — the monitored mailbox address per client instance. Not recorded in
this repository, and not to be invented here.

### 3g. `MAIL_RECEIVER_INBOUND_TOKEN`

The one rotation with a loss window, so it is a two-sided change:

1. **Generate a new long random token** (e.g. `openssl rand -hex 32`).
2. **Update the provider's webhook configuration** — the token travels as an
   `X-Inbound-Token` header, or as the password of HTTP basic credentials embedded
   in the webhook URL where the provider only supports that
   (`packages/Webkul/Admin/src/Http/Middleware/VerifyInboundEmailToken.php:18-20,27,73-82`).
3. **Set the same value in `.env` and recreate the app container.** During the window
   in which the two disagree the endpoint answers **403** and the provider
   **drops** the message — unlike a blank token, which answers **503** so the
   provider retries (`:44-55`). Do this in a quiet mail period, or accept that the
   messages in the window are lost.
4. **Verify with one provider-side test delivery** (SendGrid's inbound parse has a
   test send), then confirm the message appears in the CRM.

The comparison is `hash_equals`, so the token does not leak through timing — worth
knowing, because it means the failure you see is a clean 403 and not a slow one.

### 3h. Administrator passwords

`make admin` / `scripts/create-admin.php` **updates** the existing account rather
than inserting a second one, and requires both `KRAYIN_ADMIN_EMAIL` and
`KRAYIN_ADMIN_PASSWORD` with no defaults (`Makefile:263-272`,
`scripts/create-admin.php:29-33`). So rotation is safe and idempotent:

```bash
KRAYIN_ADMIN_EMAIL='admin@client.test' KRAYIN_ADMIN_PASSWORD='<new>' make admin
```

Two consequences to state rather than discover:

- **A password change does not invalidate existing sessions.** Sessions are
  file-backed and the cookie is encrypted with `APP_KEY`
  (`bootstrap/app.php:20-23`), not tied to the password. A stolen session survives
  a password rotation — which is why §5 contains a forced-logout step.
- **There is no invitation flow and no MFA** ([security-review.md](security-review.md)
  F10). The operator types the client administrator's password and it travels
  out-of-band. Until that changes, the password is a shared secret between two
  people, and the handover should say so.

**Force everyone out without touching `APP_KEY`.** The file session driver keeps
sessions in `storage/framework/sessions` (`config/session.php:62`), which is
bind-mounted from the checkout, so clearing it is a logout for every user and
does **not** touch encrypted data:

```bash
sudo docker compose -p <project> -f deploy/docker-compose.yml exec -T app \
  sh -c 'rm -f storage/framework/sessions/*'
```

Use this after a leaked session cookie or a leaked administrator password. Do not
reach for `APP_KEY` rotation to achieve a logout: it costs the encrypted columns
as well (§3c).

**DECISION** — whether the operator retains the client administrator password after
handover. Nothing records this today. Propose: hand it over and stop keeping it,
and hold a **separate** named support account (`D2` in
[decisions-to-confirm.md](decisions-to-confirm.md)) — which does not exist yet.

## 4. Access control

### 4a. Who may read what

| Who | May read | Must not |
| --- | --- | --- |
| **The operator (one person)** | Everything on the instances they run, when the task requires it | Paste any of it into a chat, a ticket, a commit or a screenshot (§4b) |
| **Root on the host** | Everything: `.env`, the database, the volumes | — (it is the trust anchor; there is no higher control) |
| **uid 33 / group 33 (PHP-FPM)** | `.env` through the read-only mount, and the database through `DB_PASSWORD` | Anything beyond the application's own configuration |
| **The deploy user** | `.env` as its owner | — |
| **The client's administrator** | Their own password, and everything the admin UI shows | Any `.env` value. The admin UI exposes no credential screen |
| **Anyone holding a backup repository or the laptop copy** | **Data and key together** (§1e) | — |
| **A support person, a contractor, a second operator** | **Nothing, today** — there is no second identity, no named support account and no audit trail. That gap is `D2` in [decisions-to-confirm.md](decisions-to-confirm.md), and [../legal/data-residency.md](../legal/data-residency.md) §3 states it to the client | Must not be given a shell "for now" |

### 4b. Where a secret must never appear

| Channel | Why it is not recoverable |
| --- | --- |
| **Chat** (WhatsApp, Telegram, any group) | Forwarded and synced to devices that are not ours; no deletion that means anything |
| **A support ticket or email** | Archived on both sides, searchable forever, and it is the one file [support-sla.md](support-sla.md) §6 already forbids — *"Do not send credentials, `APP_KEY`, `.env` contents or a database dump … Refer to a key by name"* |
| **A commit, a PR, a CI log** | Git history is forever and a force-push does not un-leak it. [ci-workflows.md](ci-workflows.md) is the CI reference; a secret printed by a failing step is a leak even if the step is red |
| **A screenshot** | Crops are accidents: the `.env` behind the terminal window, the `docker inspect` output above the line you meant. This is why §3a greps for named keys instead of dumping the environment |
| **`docker compose config`** | It **prints every interpolated value**, including `DB_PASSWORD` and `DB_ROOT_PASSWORD` — which is exactly why provisioning uses hex (`deploy/provision-instance.sh:274`) |
| **argv / shell history** | A password in `argv` is a password in `ps`, in `/proc/<pid>/cmdline` and in the history file (`deploy/README.md:241-243`). Use `MYSQL_PWD` or a prompt, never `-p<password>` |
| **A log line** | Provisioning and mail-test strip credentials on purpose — `mail-test` prints the SMTP conversation "with the credentials stripped", and the instance report prints only `set (base64:ab…)… not shown` (`deploy/provision-instance.sh:990-991`) |

**When a value has to be transferred to a client**, transfer it through something
the client controls: their own password manager, or a one-time link from a service
they can audit. Not a chat message, however convenient.

### 4c. What the deploy path needs, versus what the operator holds

They are different sets, and conflating them is how a CI runner ends up with a
database root password:

| The **deploy path** needs | The **operator** needs |
| --- | --- |
| The deploy SSH key for the box (or the pipeline's own access) | — |
| `.env` **readable at runtime** by uid 33 — not its contents | The values, and a recovery copy (§1e) |
| The ability to recreate the `app` container after an `.env` edit (§3a) | The provider consoles that own each credential |
| Nothing else | Root on the database, for restores |

**A deploy never needs `APP_KEY`, `DB_ROOT_PASSWORD`, a mail key or an IMAP
password as a *value*.** If a pipeline step starts asking for one, that is the
signal that the pipeline is about to become a second place the secret lives —
[ci-workflows.md](ci-workflows.md) and [security-review.md](security-review.md) §4
are where that decision belongs. Note also that `deploy/deploy.sh` is written
for exactly one instance and hardcodes its container names
(`deploy/README.md:402-406`); a second instance has no automated deploy, and
therefore no second copy of anything.

**DECISION** — where the operator stores each client's secret values (a named
password manager, an encrypted file, or "only on the host and in the backup"). The
repository records the *locations* and no custody tool. Until it is written down,
the true answer is "on the host and in the restic snapshot", which §1e already
describes as holding the data and the key together.

## 5. Leak response

This is the security branch of [incident-response.md](incident-response.md) §9, and
the first step there is the one people skip: **preserve, do not clean.** In full:

### 5.1 Detect

The paths that have already happened here, or are one action away:

- **A backup copy in the wrong hands** — the restic repository, or the 03:30 laptop
  copy (§1e; [security-review.md](security-review.md) F16).
- **The demo administrator credential on a public login page** — already true, and
  it is the highest practical risk on the only public instance
  ([security-review.md](security-review.md) F17,
  `.agents/context/operations/deployment-homelab.md`, "Open risks" #3).
- **A `.env` pasted into a ticket or a chat** (§4b).
- **A rotated secret that was never revoked at the provider** — a leak that keeps
  working.
- **A session cookie captured off the wire.** On an instance reached over plain
  HTTP the cookie travels in clear, because `SESSION_SECURE_COOKIE` is unset in
  both templates (`config/session.php:171`, [security-review.md](security-review.md)
  F8). Behind the tunnel the browser leg is HTTPS, which is why the demo is not
  exposed — but a LAN instance is.
- **Unexplained admin activity**, a mail send nobody made, or a provider complaint
  about the sending domain.
- **A database dump outside the host** — a bare dump already gives every plaintext record; with `APP_KEY` it gives the encrypted columns too, and the nightly snapshot provides both (§1e).

### 5.2 Contain (before rotating anything)

1. **Preserve.** Copy the database, `storage/` and `.env` per
   [incident-response.md](incident-response.md) §3. Rotate `APP_KEY` only after the
   copy exists, because after rotation the old key is the only thing that can read
   the old data.
2. **Cut the access the leaked secret grants**, at the authority, not in `.env`:
   revoke the provider key, disable the mailbox app password, `ALTER USER` to lock
   the database account out, remove the Google connection.
3. **Stop the corpus.** If the leak was a device or a repository copy, stop the
   copies — the laptop snapshot, the staging tree, the pre-deploy dumps.
4. **Do not delete the evidence.** The after-action review
   ([incident-response.md](incident-response.md) §8) is impossible without the log
   of what the leaked credential did.

### 5.3 Rotate

Follow §3 for every secret the leak reaches — and **follow the reach**, not the
list:

| Because a leak of this | Also rotates |
| --- | --- |
| `.env`, or the host, or a backup repository copy | **All of it**, in the order §3 lays out: both database passwords (§3d), SMTP (§3e), IMAP (§3f), the inbound token (§3g), every administrator password (§3h) — and **`APP_KEY` last** (§3c), because it is the only irreversible one. Include every provider key in §1b that happens to be set |
| `APP_KEY` alone | `APP_KEY`, and then re-authorise Google (§3c). The database password is *not* implied |
| `DB_PASSWORD` | The application database user, and assume every record was read, including `users.password` hashes |
| `DB_ROOT_PASSWORD` | The root account, and assume every database on the host was read |
| `MAIL_PASSWORD` | The sending key only. Check the provider's send log for mail nobody sent |
| `MAIL_RECEIVER_INBOUND_TOKEN` | The token. A leak only allows forged inbound mail — a data-pollution path, not an authorisation one |
| The client's administrator password | That password **and force the sessions out** — a password change alone does not, §3h |

### 5.4 Verify

A rotation is not done when the file is edited. It is done when:

- the app container is on the new value **and** the framework resolves it (§3a);
- `/admin/login` answers 200 and an administrator can log in;
- **the old credential no longer works** — that is the check people skip: try the
  old SMTP key, the old database password, the old token, and confirm refusal;
- mail is proven by a real send from inside the container (§2c), not by a boot;
- if `APP_KEY` moved, the encrypted columns are accounted for and the Google
  connections are re-authorised or explicitly declared lost.

### 5.5 Disclose

1. **Start the clock from awareness, not from cause.** [../legal/dpa.md](../legal/dpa.md)
   §7.1 sets a **72-hour** personal-data notification clock that starts when the
   vendor becomes aware.
2. **The client is told first**, through the named contact who may open a severity 1,
   on the cadence in [incident-response.md](incident-response.md) §2 — before any
   other client, any status page or any social media.
3. **The client is the controller and normally notifies the authority**
   ([../legal/dpa.md](../legal/dpa.md) §7.3, and `D19` in
   [decisions-to-confirm.md](decisions-to-confirm.md) is still open). The vendor does
   not notify an authority unless instructed in writing.
4. **Say what the leak reaches, not what it might.** "The backup repository holds the
   database and the key" is a sentence a client can act on. "We don't think there is
   any impact" is not, and it is the sentence that ends a contract.
5. **A lawyer** on any personal-data breach or any loss that may be contractual —
   facts only, no speculation ([incident-response.md](incident-response.md) §2 #5).
6. **Record it as a bead** and close it with the after-action review
   ([incident-response.md](incident-response.md) §8), including the change of
   custody this document asked for in §1e and §4c.

## 6. The 02:00 checklist

One column of do-this-now. Each line names the file that has the detail; nothing
here needs reading twice.

**If you think a secret is out:**

1. ☐ **Do not clean anything yet.** Copy the database, `storage/` and `.env` —
   `incident-response.md` §3.
2. ☐ Write the time, and every action from here on, into the ticket.
3. ☐ **Revoke at the source**: provider key, mailbox app password, database user,
   Google connection.
4. ☐ **Rotate, in this order:** `DB_PASSWORD` → SMTP → IMAP → inbound token →
   administrator passwords → **`APP_KEY` last** (it is the only destructive one,
   §3c).
5. ☐ After each `.env` edit: recreate the app container
   (`up -d --force-recreate --no-deps app`), then prove the value inside the
   container (§3a).
6. ☐ Prove mail with a real send, not a boot (§2c): `make mail-test MAIL_TO=…` on a
   development checkout, `php scripts/mail-test.php` inside the instance's
   container.
7. ☐ Confirm **the old credential is refused** now.
8. ☐ Tell the client's named contact — within 1 hour in business hours, and the
   72-hour clock in `dpa.md` §7.1 has already started.
9. ☐ Rotate the **demo administrator** password if the demo is the instance
    involved. It is a known demo credential on a public login page
    (`security-review.md` F17).

**If you are rotating on purpose (no leak):**

1. ☐ Check the trigger is real: a leak, a departed holder, a provider requirement.
   A scheduled `APP_KEY` rotation with no re-encryption step is data loss for no
   gain (§3c).
2. ☐ Two valid keys at the provider **before** revoking one (§3e).
3. ☐ Dump before any `ALTER USER` (§3d).
4. ☐ Change the authority first, `.env` second, recreate third, verify fourth.
5. ☐ Warn the client if `APP_KEY` or an administrator password changes — everyone is
   logged out.
6. ☐ Update `deploy/README.md` and this file if the procedure needed a step it
   did not have.

**Three sentences to keep in mind at 02:00:**

- **`APP_KEY` is not rotatable without loss in this product.** Copy first, keep the
  old key, rotate last.
- **Editing `DB_PASSWORD` in `.env` does not change the database password.**
  `ALTER USER` first.
- **Mail failing looks like nothing.** Only a real send proves a mail credential.

## 7. What this document does not claim

- No secret has been rotated following this procedure. It is written from the code
  and the existing runbooks, and the same acceptance rule as
  [incident-response.md](incident-response.md) §6 applies: **the first real rotation
  or leak is the rehearsal**, and the steps that turned out to be wrong belong in
  this file the same day.
- The inventory covers what the application's code reads. It does not cover the
  host: SSH keys, the Cloudflare Tunnel credential, the restic repository password
  and the DNS account are infrastructure secrets outside this checkout, and
  [../legal/data-residency.md](../legal/data-residency.md) §3 is where that access
  is described to a client.
- No vendor was contacted and no provider's current UI was checked. Where a provider
  is named (Brevo), it is named only for the vendor's own demo instance, from
  `deploy/.env.deploy.example` and `.env.example`.

## Sources

- **Code read for this document:** `config/app.php`, `config/cache.php`,
  `config/broadcasting.php`, `config/database.php`, `config/filesystems.php`,
  `config/imap.php`, `config/logging.php:59`, `config/mail-receiver.php`,
  `config/services.php`, `config/session.php:130,171`,
  `packages/Webkul/Admin/src/Http/Middleware/VerifyInboundEmailToken.php`,
  `packages/Webkul/Admin/src/Http/Controllers/DataGridController.php:26`,
  `packages/Webkul/DataGrid/src/DataGrid.php:549`,
  `packages/Webkul/GoogleContact/src/Models/GoogleContactAccount.php:39-40`,
  `packages/Webkul/GoogleContact/src/Services/GoogleClientFactory.php:25-27`,
  `packages/Webkul/Installer/src/Console/Commands/Installer.php:137,140`,
  `packages/Webkul/Installer/src/Helpers/DatabaseManager.php:214-220`,
  `packages/Webkul/WebForm/src/Routes/routes.php:6-7,11`,
  `packages/Webkul/WebForm/src/Repositories/WebFormRepository.php:44`,
  `packages/Webkul/WebForm/src/Http/Controllers/WebFormController.php`.
- **Deployment and provisioning:** `deploy/docker-compose.yml:59-69,112,139`,
  `deploy/docker/entrypoint.sh:41-66`, `deploy/provision-instance.sh:267,276,662,764,836,841,990-991`,
  `deploy/.env.deploy.example`, `.env.example`, `.gitignore:1`,
  `deploy/README.md` (§First deploy, §The `.env` must belong to the deploy user
  AND to uid 33, §Second instance, §Mail, §A `.env` edit does nothing until the app
  container is recreated, §Backups), `scripts/README.md`
  (§The installer rotates `APP_KEY`), `Makefile:263-285`,
  `scripts/create-admin.php`, `scripts/mail-test.php`.
- **Knowledge base:** `.agents/context/operations/provisioning.md`,
  `.agents/context/operations/deployment-homelab.md` (§First-boot failure, "Open
  risks" #1 and #3), `.agents/context/operations/database.md`.
- **Companion documents reconciled against:** [incident-response.md](incident-response.md)
  §3, §4 #6, §5, §8, §9; [security-review.md](security-review.md) F8, F10, F14, F15,
  F16, F17 and §4 items 12–13; [support-sla.md](support-sla.md) §4a, §6;
  [decisions-to-confirm.md](decisions-to-confirm.md) D2, D3, D19;
  [../legal/dpa.md](../legal/dpa.md) §3, §7; [../legal/data-residency.md](../legal/data-residency.md)
  §3, §4.
