# Sijilna

**Every deal, on the record.**

Sijilna is a self-hosted CRM and sales platform. You install it on your own
server, the data stays in your own database, and no third party sits between you
and your customer records.

It is **Arabic-first**. Arabic is not a translation layer bolted onto an
English-shaped interface: the admin panel, the customer-facing forms, the
generated documents and the locale data are designed and shipped in Arabic
alongside English, and the Arabic typography is set to its own standards rather
than inheriting the Latin one.

The name comes from **سجل** — record, register, ledger. *Sijilna* (سجلنا) is "our
register": every lead, deal, quote and activity is a dated entry in one ledger
the business owns.

## Who it is for

- **Small and mid-sized sales teams** who want a CRM they run themselves, on
  their own hardware or their own cloud account, without a per-seat subscription.
- **Arabic-speaking businesses** whose CRM has to speak Arabic properly — leads,
  quotes, invoices and email in the language the team actually works in.
- **Companies with data-residency or confidentiality constraints** that rule out
  a hosted CRM: the database is yours, on your network, reachable by nobody else.
- **Agencies and integrators** delivering a CRM to a client: it installs from a
  standard PHP stack and can be branded per client.

## Requirements

| Requirement | Version | Source of truth |
| ----------- | ------- | --------------- |
| PHP | `^8.3` | `composer.json` (`require.php`) |
| Laravel | `^12.0` | `composer.json` (`require.laravel/framework`) |
| Composer | 2.x | `composer.lock` (`plugin-api-version: 2.9.0`) |
| MySQL | 8.x | development container runs `mysql:8.4` |
| Node.js + npm | a current LTS | no version is pinned in this repository |

The installer additionally offers MariaDB, PostgreSQL and SQL Server as the
database connection (`packages/Webkul/Installer/src/Console/Commands/Installer.php`,
`askForDatabaseDetails()`); MySQL is what this project is developed and tested
against.

Node is used only to build the frontend bundle. It is not required to run an
existing installation, because the compiled assets are committed under
`public/admin/build/`, `public/installer/build/` and `public/webform/build/`.

## Installation

```bash
composer install

cp .env.example .env
php artisan key:generate
```

Then open `.env` and set, at minimum:

- `APP_NAME` — the name shown in the interface and in outgoing mail.
- `APP_URL` — the address the installation is reached at.
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
  `DB_PASSWORD` — the database the CRM will use.
- `MAIL_*` — the mailer used for transactional email.

Run the installer:

```bash
php artisan krayin-crm:install
```

It prompts for the application name, the default locale and currency, the
database connection/host/port/name/prefix/username/password, and the
administrator's name, email and password. The administrator password is
requested without a default and must be at least 8 characters. Two flags skip
parts of it: `--skip-env-check` and `--skip-admin-creation`.

Rebuild the frontend bundle only if you have changed frontend sources:

```bash
npm install
npm run build
```

### Scripted installation

This checkout ships a provisioning path that performs the whole sequence above
and starts the database container: `make setup` (which runs
`scripts/provision-dev.sh`). On a first install it requires `KRAYIN_ADMIN_EMAIL`
and `KRAYIN_ADMIN_PASSWORD` and fails before writing anything if either is
missing. `make admin` creates or updates the administrator later on, from the
same two environment variables. `scripts/README.md` is the full runbook.

## Running it

`make` with no arguments lists the whole command surface. The server targets:

```bash
make serve     # start the dev server on the port in APP_URL (default 8000)
make stop      # stop this checkout's server (port owner + /proc/<pid>/cwd)
make restart   # stop, then start again
make status    # listener, HTTP codes, database counts, container state
make logs      # tail storage/logs/serve.log
```

`make serve` runs
`php artisan serve --host=127.0.0.1 --port=<port>` detached, rooted at `public/`,
logging to `storage/logs/serve.log`. It is idempotent — it never starts a second
server and never kills one — and it refuses if the port is held by a process
whose working directory is outside this checkout. Overrides:
`make serve PORT=8001`, `make logs LOG=storage/logs/laravel.log`. The plain
equivalent, without `make`, is `php artisan serve`.

Once it is running:

- **Administrator panel:** `/admin/login`. `/` redirects there
  (`packages/Webkul/Admin/src/Routes/Front/web.php`). The `admin` segment comes
  from `APP_ADMIN_PATH` (`config/app.php`, default `admin`), so an installation
  that overrides it is reached under that path instead.

There is no built-in default administrator password. The account is created
during installation (or by `make admin`) with credentials you choose.

Database helpers: `make db-shell` opens a MySQL shell on the live database as the
`.env` user; `make db-reset` is the only destructive target — it wipes,
re-migrates, re-seeds and recreates the administrator, and refuses unless
`CONFIRM` is exactly the name of the database it would destroy
(`make db-reset CONFIRM=<database>`), the administrator credentials are supplied,
and the target is proven to be this checkout's local development database.

## Module layout

Sijilna is built on a modular Laravel application. Each module lives in its own
directory under `packages/` and follows the same internal shape:

```text
packages/Webkul/{Module}/
├── src/
│   ├── Config/            # module configuration (menus, ACL, system config)
│   ├── Console/           # artisan commands
│   ├── Contracts/         # interfaces, including repository contracts
│   ├── Database/
│   │   ├── Migrations/    # the only place schema changes are made
│   │   ├── Seeders/
│   │   └── Factories/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   ├── Providers/         # service providers, binding contracts to repositories
│   ├── Repositories/      # the only way models are reached
│   └── Resources/
│       ├── lang/          # only when the module also ships views
│       └── views/
└── routes/                # module route files
```

The shipped modules are: `Activity`, `Admin`, `Attribute`, `Automation`,
`Contact`, `Core`, `DataGrid`, `DataTransfer`, `Email`, `EmailTemplate`,
`GoogleContact`, `Installer`, `Lead`, `Marketing`, `Product`, `Quote`, `Tag`,
`User`, `Warehouse` and `WebForm`.

Conventions worth knowing before changing anything:

- Models are accessed through repositories, bound to contracts in the module's
  service provider.
- Every schema change goes through a migration — never a direct database edit.
- A module gets its own `Resources/lang/` **only** if it also has
  `Resources/views/`. View-less modules keep their strings in the `Admin`
  package.
- Backward compatibility is preserved so a deployment can be upgraded in place.

`.github/skills/` documents the reusable development skills for this codebase:
`crm-package-development` for creating or extending a module without touching
the modules a future upstream merge will replace, and `pest-testing` for writing
the tests. Read the relevant `SKILL.md` before working in that area.

## Testing

The suite is Pest 3 on PHPUnit 11 and runs against a dedicated test database,
never against a live one. The database is pinned in `phpunit.xml`, and both the
suite and the provisioning script read that pin, so they cannot disagree about
which database is at stake.

```bash
make test            # migrate + seed the test database, then run the suite
make test-db         # create/grant/migrate/seed the test database (idempotent)
make lint            # format PHP with Pint
make lint-check      # check formatting without changing files
```

`make test` checks that the test database exists, migrates and seeds it, then
runs `php artisan test --compact`. `make test-db` is the target that creates and
grants it, through `scripts/prepare-test-db.sh`. `make test` refuses to start
when the test database is missing, when `phpunit.xml` pins the live database or
pins nothing, when the database container is not running, or when a cached
configuration file would make the suite ignore the pin. A cached config is
cleared with `php artisan config:clear`.

Add tests for new behaviour. Run `make lint` before committing — CI applies Pint
formatting.

## Support

Support is part of the annual maintenance contract attached to the licence; it is
not a free channel. While an installation is under maintenance it gets updates and
security patches for the `2.2` line, upgrade assistance when moving between
releases, and an answer when something breaks. The written Arabic manual in
`docs/client-guide/ar/` — 17 chapters, from first login to troubleshooting — is
the self-service reference shipped with the product.

The licence is perpetual, so an installation whose maintenance has lapsed keeps
running and keeps its data; what stops is the patches and the answer, not the
software.

Support is given by whoever supplied the installation, under that contract. There
is no public issue tracker, forum or community for this product, and none of the
upstream project's channels apply to it.

## Licence and attribution

Sijilna is an independent fork of [Krayin CRM](https://github.com/krayin/laravel-crm),
which is MIT licensed. The upstream copyright notice is retained in full and
unmodified — see [LICENSE](LICENSE), which carries the MIT copyright of Webkul
Software (`Copyright 2010-2025, Webkul Software`). Modifications in this fork are
distributed under the same MIT terms.

The third-party components bundled with this product, including the
copyleft-licensed PHP dependencies and how they are licensed, are listed in
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

This product is not affiliated with, endorsed by, or sponsored by Webkul Software.
The MIT grant conveys no trademark rights, so the upstream name and marks are
used here only to describe the origin of the code, never to imply that this
product is official, endorsed or sponsored by Webkul Software.

Upstream fixes are still pulled in: `upstream` points at the Krayin repository,
the `2.2` branch tracks upstream's `2.2`, and an upstream release arrives by
merging `2.2` into `main`. Those remotes and the merge rule are documented in
`AGENTS.md` ("What this repository is"); nothing is pulled
automatically.
