# Sijilna: Agent Instructions

This repository ships reusable **agent skills** for developing Sijilna, a
self-hosted Arabic-first CRM. These
instructions apply to all AI coding agents (Claude Code, Codex, Copilot, Cursor,
Kilo Code, etc.).

---

## Framework Context

- **Product:** Sijilna — a self-hosted, Arabic-first CRM built on Krayin CRM (Webkul), MIT licensed.
- **Stack:** Laravel `^12.0`, PHP `^8.3`, Pest `^3.0`, Laravel Pint `^1.18`.
- **Follow Krayin's own conventions** — use the patterns already present in this
  repository's modules; do not import patterns from other Laravel products.
- **Module location:** `packages/Webkul/{ModuleName}/src/`
- **Core modules (reference implementations):** `Lead`, `Contact`, `Activity`,
  `Quote`, `Product`, `User`, `Attribute`, `Core`, `Admin`, `DataGrid`.

---

## Skills

Workspace skills live in `.github/skills/` (canonical source) and are symlinked
into every agent runtime (`.ai`, `.claude`, `.codex`, `.cursor`, `.kilocode`).
See [.github/skills/README.md](.github/skills/README.md) for the standard.

| Skill | Use when |
|-------|----------|
| `crm-package-development` | Creating a new CRM package/module or extending CRM functionality without touching core. |
| `pest-testing` | Writing or debugging unit/feature tests with Pest. |

Read the relevant `SKILL.md` before starting work in its domain. Do not restate
skill content here — keep details in the skills.

---

## Critical Conventions (Never Deviate)

- **Never modify core Krayin files** unless explicitly required. Extend behavior
  through a package under `packages/Webkul/`.
- **All schema changes go through migrations** — never edit the database directly.
- **Follow the existing module layout** (Providers, Models, Contracts,
  Repositories, Http/Controllers, Routes, Database/Migrations, Resources/views,
  Config). The `crm-package-development` skill documents the full structure.
- **Repository pattern:** models are accessed through repositories bound via
  contracts in the service provider — match the surrounding modules.
- **Translations follow the views:** a package gets its own `Resources/lang/`
  **only if** it also has `Resources/views/`. Every view-less package keeps its
  strings in the Admin package under `admin::app.*` — no lang directory, no
  `loadTranslationsFrom()`. See the `crm-package-development` skill.
- **Every fix and feature updates `CHANGELOG.md`** in the same change, under the
  topmost unreleased version heading. Never invent a `#PR` number and never bump
  `KRAYIN_VERSION` outside a release commit. See the `crm-package-development`
  skill for the entry format and tags.
- **Preserve backward compatibility** so the CRM stays upgrade-safe.

---

## Development Cycle

```bash
composer install            # install PHP dependencies
php artisan migrate          # run migrations
php artisan test --compact   # run the Pest test suite
./vendor/bin/pint            # format code (PSR-12 via Laravel Pint)
```

- Add tests for new behavior; follow the `pest-testing` skill.
- Run `./vendor/bin/pint` before committing — CI applies Pint formatting.
- Validate the skills setup with `bash bin/validate-skills.sh`.

<!-- =====================================================================
     BEGIN crm-platform project section (fork-specific).
     Everything below this line is this checkout's operational reality, not
     upstream Krayin's. Upstream content above ends here and is not repeated.
     ===================================================================== -->

---

# crm-platform: Project Operations

This section is fork-specific. It describes the machine this checkout runs on
and the rules that keep it intact. Read it before running anything.

## What this repository is

This is a **private commercial fork of Krayin CRM** (upstream is MIT licensed).

- `main` is **our product**. Client work lands here and is never force-pushed.
- `2.2` tracks upstream's `2.2` branch.
- Upstream updates arrive by **merging `2.2` into `main`**. History is never
  rewritten and `main` is never force-pushed, because a client's data and
  review trail live on `main`.
- Remotes: `origin` = `github.com/mhmd-ashrf-saad/crm-platform.git`,
  `upstream` = `github.com/krayin/laravel-crm.git`. Do not push to `upstream`.

## Rebrand boundary

Rebranding covers user-facing surfaces (login, header/footer, page titles,
dashboard text, PDFs, email templates and signatures, installer banner, error pages, favicon)
and repo identity (README, `composer.json` package name, GitHub description and
topics, `.env.example` comments). It excludes PHP namespaces, `packages/Webkul/**`
paths, composer package and dependency names, upstream issue templates and CI
workflows, `config/krayin-vite.php` without an override path, and `vendor/` —
those stay upstream so `2.2` keeps merging. Apply branding through an override or
configuration surface, never a core edit. Upstream is MIT: forking is permitted,
trademark rights are not. See
[.agents/context/guides/rebrand-boundary.md](.agents/context/guides/rebrand-boundary.md).

## The running system

- **MySQL** runs in the Docker container `crm-platform-mysql` (`mysql:8.4`),
  published on `127.0.0.1:3306` only. The port is bound to loopback
  (`-p 127.0.0.1:<port>:3306`), not to `0.0.0.0`, so the database is not
  reachable from the LAN.
- Its data lives in the **named volume `crm-platform-mysql-data`** mounted at
  `/var/lib/mysql`. The volume, not the container, is what survives a rebuild.
- **Never run `docker volume prune` or `docker system prune`.** Either one
  deletes the named volume and every client database in it. There is no undo.
- The app is served by `php artisan serve` on `http://127.0.0.1:8000`, detached
  (`setsid nohup ... < /dev/null &`) and rooted at `public/`, logging to
  `storage/logs/serve.log`. `make serve` / `make stop` wrap this.
- **All secrets live in the gitignored `.env`** (`APP_KEY`, `DB_PASSWORD`,
  `DB_ROOT_PASSWORD`, mail and IMAP credentials). Never print a value; refer to
  keys by name. `.env.example` holds placeholders only.

## Command surface

`make` (equivalently `make help`) is the documented surface and prints every
target with a one-line description, resolved against the live `.env` and
`phpunit.xml`. Run `make` with no arguments to rediscover the surface.

| Target | Purpose |
| ------ | ------- |
| `make setup` | Provision this checkout (`scripts/provision-dev.sh`) |
| `make serve` / `make stop` / `make restart` | Dev server lifecycle |
| `make status` | Listener pid, HTTP codes, database counts, container state |
| `make test` | Pest suite against the test database |
| `make test-db` | Create/grant/migrate/seed the test database |
| `make lint` / `make lint-check` | Pint format / check |
| `make admin` | Create or update the admin account (credentials required) |
| `make logs` | Tail the dev server log |
| `make db-reset` | Destructive; guarded wipe + re-migrate + re-seed + admin recreation (needs `CONFIRM=<database name>` and the `KRAYIN_ADMIN_*` credentials) |
| `make db-shell` | MySQL shell on the live database as the `.env` user |

Overridable: `make serve PORT=8001`, `make logs LOG=storage/logs/laravel.log`.

## Tests

The suite runs against a dedicated **`krayin_test`** database, never the live
one. The pin is committed in `phpunit.xml`:

```xml
<server name="DB_DATABASE" value="krayin_test" force="true" />
<env    name="DB_DATABASE" value="krayin_test" force="true" />
```

Run it with **`make test`** (which migrates and seeds `krayin_test` first).

**The double-env trap:** `phpunit.xml` must set **both** the `<env>` and the
`<server>` form of each connection key. `<env>` sets the process environment and
`$_ENV`; `<server>` sets `$_SERVER`. Laravel's dotenv repository reads
`$_SERVER` first, so with `<env>` alone an inherited `DB_DATABASE=krayin` still
won and `RefreshDatabase` would have run `migrate:fresh` against the **live**
database. `force="true"` stops PHPUnit deferring to an already-set variable, so
the pin cannot be overridden from the shell — change it by editing
`phpunit.xml`. `make test` additionally refuses when `phpunit.xml` pins the live
database, pins nothing, or when `bootstrap/cache/config.php` exists (a cached
config beat both `.env` and `phpunit.xml`; clear it with `php artisan config:clear`).
`make test-db` refuses for the same cached-config reason, and so does `make
db-reset`.

## Provisioning

`bash scripts/provision-dev.sh` provisions a fresh clone or machine. Flags:
`--port N`, `--reinstall`, `--check-env` (dry run), `--help`. It is
idempotent — it reuses the container, does not rewrite `.env`, does not
re-generate `APP_KEY`, and skips the installer when `storage/installed` exists.

A fresh install **must** supply `KRAYIN_ADMIN_EMAIL` and
`KRAYIN_ADMIN_PASSWORD`; neither has a default and provisioning fails before
writing anything if either is missing. The interactive
`php artisan krayin-crm:install` is bypassed because its prompts pre-fill
defaults and never receive EOF, so a scripted call blocks forever. Provisioning
uses the unattended path instead:

```bash
php artisan krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction
KRAYIN_ADMIN_EMAIL='admin@client.test' KRAYIN_ADMIN_PASSWORD='<chosen>' \
  php scripts/create-admin.php
```

`scripts/create-admin.php` takes the email and password from the environment,
requires both, and updates the existing account instead of inserting a second
one. See `scripts/README.md` for the full runbook.

## Hard rules (each one is here because it already went wrong)

- **Never run `migrate:fresh`, `db:wipe` or `migrate --force` by hand against the
  live database.** Use `make db-reset`, whose confirmation must be the name of
  the database it destroys — `make db-reset CONFIRM=krayin` — and which also
  requires `KRAYIN_ADMIN_EMAIL` and `KRAYIN_ADMIN_PASSWORD`, refusing before it
  destroys anything when either is missing. The old generic `CONFIRM=reset` is
  refused: a token that names its target cannot be fired by accident while
  testing a guard, and that accident is what destroyed the development database
  once. It still refuses unless it proves the target is the local development
  database (`APP_ENV=local` from `.env`, loopback `DB_HOST`, non-system
  `DB_DATABASE`, running container), resolving the connection once and handing
  the same values to `artisan` so the guard and the wipe cannot disagree. It
  also refuses while `bootstrap/cache/config.php` exists, because a cached
  config beats `.env` and artisan would wipe whatever database the cache names
  while the guard read the environment; clear it with
  `php artisan config:clear`. A permitted run ends fully installed — one
  administrator row for `KRAYIN_ADMIN_EMAIL`, `storage/installed`, and
  `core_config installation.completed=1`, all written by
  `scripts/create-admin.php` — so a reset never leaves a half-installed instance.
- **`APP_ENV` is read from `.env` only, deliberately.** Setting `APP_ENV` in the
  caller's environment does not make the guard refuse: a live instance whose
  `.env` says `local` is still wiped. That is exactly why an agent testing the
  guard must never run `db-reset` against `krayin` — test it against a scratch
  database.
- **Never modify core Krayin files.** Extend through a package under
  `packages/Webkul/`. A core edit collides on every `2.2` merge and cannot be
  upgraded safely; package code rarely touches upstream paths.
- **Never commit `.env` or any credential.** `.env` is gitignored; a hardcoded
  credential in a committable script was already found and removed once. If a
  default is convenient, that is the reason it is forbidden.
- **Never use a blanket `pkill -f 'php artisan serve'`.** The pattern is
  unanchored and re-reads the full command line of every process, so it also
  matches — and kills — whatever shell or script merely mentions that text,
  including the one running the command. That is a measured failure here. Use
  `make stop`, which matches the socket owner by port regardless of bind
  address and confirms
  `/proc/<pid>/cwd` is inside this checkout.
- **Never "fix" file permissions or line endings.** The checkout is on an NTFS
  `fuseblk` mount (`mount | grep /mnt`) where everything reports mode `777` and
  `core.filemode` is deliberately `false`. A permission cleanup produces a
  thousand phantom changes with no real effect.
- **The mount can go read-only** if Windows boots or hibernates with Fast
  Startup enabled. If git suddenly fails strangely, run `mount | grep /mnt`
  **before** debugging anything else: a `ro` flag is the cause, and it is fixed
  by rebooting into Linux cleanly, not by touching the repository.

## One writer at a time

This checkout has **one working tree**. Two agents editing it concurrently have
overwritten each other's work multiple times. Serialize on this directory, or
give parallel lanes **separate worktrees** or **strictly disjoint paths**. Do
not assume "different files" is safe when both writers also run migrations,
builds, or `pint`.

## Beads

`bd` works from this directory with **no flags**. The configuration that makes
that true is fragile and non-obvious:

- `git config --local beads.role` must be `maintainer`.
- `~/.beads` must be a symlink to `/home/mohamed/.local/share/beads/crm-platform`
  (the same store `.beads` points at).
- `.beads/config.yaml` must contain **no `repos:` block**.

**Never run a bare `bd init`.** It detects the `upstream` remote, restores
`beads.role=contributor`, re-adds a read-only planning repo, and appends to
`.gitignore`. Every issue mutation then fails with `embeddeddolt: store is
read-only`, which reads like a disk fault. If re-initialising is genuinely
required, the only sanctioned command is:

```bash
bd init --non-interactive --prefix crm --role maintainer --skip-agents --skip-hooks
```

followed by restoring `.gitignore` from `HEAD` and deleting the `repos:` block.

## How to prove work here

"It should work" has repeatedly been wrong on this checkout. Every claim in a
report must be reproducible by a command:

- **Check HTTP status codes**, do not assume a 200: `curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/admin/login`.
- **Log in for real** when a change touches auth or the admin UI; a static page
  that renders is not proof a session works.
- **Run the suite:** `make test`.
- **Count rows in the database**, not just tables:
  `make db-shell` then `SELECT COUNT(*) FROM users;`.
- `make status` runs the HTTP, table-count and container checks in one place.

<!-- END crm-platform project section -->
