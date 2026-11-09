# The demo dataset — seeding it, resetting it, and what it destroys

> **Status:** operator runbook, written from the code that ships it
> (`database/seeders/Demo/`, `tests/Feature/Demo/DemoSeederTest.php`). Every number
> and boundary below was read out of those files, not estimated.
> **Share with a client?** No — §4 names the credentials file that holds every demo
> password in clear text.
> **Companions:** [incident-response.md](incident-response.md) (when a reset was not
> what you meant), [secrets-handling.md](secrets-handling.md) (custody of generated
> credentials), [../../scripts/README.md](../../scripts/README.md) (the canonical
> `db-reset` runbook — this file cites it rather than restating it).
> **Knowledge base:** `.agents/context/operations/database.md` (live vs test
> database), `.agents/context/operations/provisioning.md` (install and reinstall).

## 1. What it is, and the one command that runs it

`Database\Seeders\Demo\DemoSeeder` wipes and rebuilds a complete Arabic-first
Egyptian-market CRM — users, companies, contacts, products, leads across every
installed stage, quotes with line items, activities, tags, campaigns, email
threads, web forms and attribute values — so the product can be shown to a client
or screenshotted. The content is static fixture data (`DemoFixtures`), never
generated at run time, because the random-data generator is a dev dependency and
the production image is built with `composer install --no-dev`.

```bash
php artisan db:seed --class='Database\Seeders\Demo\DemoSeeder' --force
```

`--force` is required on any instance whose `APP_ENV` is not `local` (the homelab
demo runs `APP_ENV=production`, see `deploy/.env.deploy.example`). Plain
`php artisan db:seed` does **not** seed the demo: `Database\Seeders\DatabaseSeeder`
calls only the installer's seeder.

**A rerun is idempotent.** The wipe and the rebuild happen inside one
`DB::transaction()`, wipe first, so a second run produces the same record set, the
same links and the same quote totals. The suite pins exactly that — identical
counts, identical lead titles, identical `grand_total` values on the second run
(`DemoSeederTest`, "seeds the same record set when it runs twice").

**What it needs first.** The seeder reads installed reference data and creates none
of it: at least one role, the default lead pipeline with all six stages
(`new`, `follow-up`, `prospect`, `negotiation`, `won`, `lost`), the five installed
lead sources and two lead types, and the attribute definitions the web forms name.
Missing any of them throws a `RuntimeException` naming what is missing — and because
the wipe is inside the same transaction, the throw rolls the wipe back, leaving the
instance exactly as it was (pinned by the "refuses to seed an installation with no
role and rolls the partial wipe back" test). The seeded mailbox's outgoing messages
take their From from `config('mail.from.name')` / `config('mail.from.address')`.

## 2. What the wipe removes, and what it does not

`DemoWiper` deletes in dependency order — children before parents, never relying on
`ON DELETE CASCADE` that the migrations do not declare.

**Deleted, every row, not just rows the seeder wrote:**

| Area | Tables |
| --- | --- |
| Activities | `activities`, `activity_participants`, `activity_files`, `lead_activities`, `person_activities`, `product_activities`, `warehouse_activities` |
| Mail | `emails` (replies before roots), `email_tags`, `email_attachments` |
| Quotes | `quotes`, `quote_items`, `lead_quotes` |
| Leads | `leads`, `lead_products`, `lead_tags` |
| Contacts | `persons`, `person_tags`, `contact_export_batch_items` |
| Companies / products | `organizations`, `products`, `product_tags`, `product_inventories` (only rows pointing at a product that is going) |
| Tags | `tags`, `warehouse_tags` |
| Marketing | `marketing_campaigns` (all), `marketing_events` and `email_templates` **matched by the fixture names only** |
| Web forms | `web_forms`, `web_form_attributes` |
| Attribute values | `attribute_values` where `entity_type` is one of `leads`, `persons`, `organizations`, `products`, `quotes` |

This is the boundary that matters most to an operator: **the CRM content tables are
emptied wholesale.** A lead, contact, company, product, quote, activity, tag, web
form or campaign a colleague typed into this instance is deleted too — the wipe is
only row-scoped on `users`, on `attribute_values.entity_type`, and on the two tables
it matches by name. Run it on a demo instance, never on a client instance.

**Survives every run:**

- **Every user whose address does not end in `@sijilna.demo`.** That domain is the
  whole boundary (`DemoIdentity::RESERVED_EMAIL_DOMAIN`; the SQL pattern is
  `%@sijilna.demo`, the PHP rule is `isDemoEmail()`). `DemoSeeder` snapshots every
  other user row before the run and, afterwards, throws if one row's id, column or
  value changed or disappeared — the operator's own login is asserted byte-identical,
  password hash included, not merely still present. The LIKE pattern and the PHP
  rule are cross-checked before the run so they cannot silently disagree.
- **Installed reference data:** pipelines, stages, sources, types, roles, attributes,
  attribute options, and the installer's own two email templates ("Activity created",
  "Activity modified"), which are matched by name and never deleted wholesale.
- **Warehouses and their attribute values**, and marketing events that are not the
  demo's two.

The demo users themselves are deleted together with everything that hangs off them:
`user_groups`, `user_password_resets` (matched by email, not id), `notifications`,
`google_contact_accounts`, `contact_export_batches`; and any `users.created_by` /
`roles.created_by` pointing at a demo user is set to `NULL` rather than cascaded.

## 3. The demo accounts

Addresses are the fixture key plus the reserved domain; the fixture stores only the
local part.

| Key | Email | Name | Job title |
| --- | --- | --- | --- |
| `manager` | `manager@sijilna.demo` | سارة عبد الرحمن | مدير المبيعات |
| `agent` | `agent@sijilna.demo` | كريم مصطفى | مندوب مبيعات |
| `support` | `support@sijilna.demo` | منة الله حسن | مسؤول دعم العملاء |

All three are created with `status = 1`, `view_permission = 'global'` and
`created_by` set to the first non-demo user id.

**Roles are read, never created** (`DemoSeeder::seedUsers()` / `roleFor()`). For each
user the seeder tries an exact, case-insensitive name match against, in order:

- `manager` → `Sales Manager`, `مدير المبيعات`, `Manager`
- `agent` → `Sales Agent`, `مندوب مبيعات`, `Sales`
- `support` → `Support Agent`, `مسؤول دعم العملاء`, `Support`

and falls back to the role whose `permission_type` is `all` (or the first role) when
none matches.

- **A fresh install ships exactly one role (Administrator), so all three demo users
  get Administrator** and the seeder prints a warning saying so — the sales and
  support roles are not invented, because roles are installed reference data.
- **If the operator has created sales or support roles**, each demo user lands on the
  best-named match, so the demo shows different permission scopes.
- **With no role at all**, the seeder refuses and rolls the wipe back.

## 4. The credentials file

Passwords are generated per run (`Str::password(length: 20, symbols: false)`, about
119 bits, alphanumeric so a shell or a chat client cannot eat them) and written once,
after the transaction commits, by `file_put_contents()` + `rename()` on the same
filesystem — so a reader never sees half a file. `write()` also refuses any generated
password shorter than `DemoCredentials::MINIMUM_LENGTH` (16), so a later edit cannot
quietly weaken what the file hands over.

**The file is named after the database it describes.** `DemoCredentials::path()` asks
`provenance()` for the active connection's name and `DB::connection()->getDatabaseName()`,
and `filename()` turns that database name into the filename, so two databases cannot land
on one file — not even two names a filename-safe slug would collapse together.

- **The naming rule, two cases:**
  - **A name that is already filename-safe** — only `[A-Za-z0-9._-]`, no leading or trailing
    `.` or `-` — is used unchanged: `credentials-<database>.txt`. `krayin` is
    `credentials-krayin.txt`, `krayin_test` is `credentials-krayin_test.txt`.
  - **A name that slugging would lose** — anything holding another byte (a `$`, a space, any
    non-ASCII character), or a leading/trailing `.` or `-` — gets the slug *plus a short
    digest of the raw name*: `credentials-<slug>-<digest>.txt`, `digest` being the first
    eight hex characters of `sha1(raw database name)`. So `krayin$test` resolves to
    `credentials-krayin-test-c470a856.txt` — `sha1('krayin$test')` starts with those eight
    characters — while `krayin-test` keeps `credentials-krayin-test.txt`, and two names that
    differ only in non-ASCII bytes (both `unknown` after slugging) keep two digests. The slug
    alone is not one file per database: it collapses every run of disallowed bytes to one `-`.
- **Default path:** `/var/tmp/pi-scratch/demo-seed/` + one of the names above —
  `/var/tmp/pi-scratch/demo-seed/credentials-krayin.txt` on this checkout's default
  instance, which is the exact name an operator is already reading, so a safe name keeps
  its path. Disk, not the small `/tmp` tmpfs. `path()` also takes an optional database name
  and directory, so the derivation can be exercised with the write landing in a directory a
  test owns; `DEFAULT_DIRECTORY` (`/var/tmp/pi-scratch/demo-seed`) is never written by the
  test suite.
- **Override:** the `DEMO_SEED_CREDENTIALS_PATH` environment variable, read by
  `DemoCredentials::path()` before either argument, is a **whole path** and wins outright —
  when it is set, the database-derived name is not used at all (the header still records the
  connection and database the run wrote for). Pass it on the same command line as the seed, e.g.
  `DEMO_SEED_CREDENTIALS_PATH=/var/tmp/pi-scratch/demo-seed-2/credentials.txt php artisan db:seed --class='Database\Seeders\Demo\DemoSeeder' --force`.
- **The old global name is retired.** `DemoCredentials::LEGACY_PATH`
  (`/var/tmp/pi-scratch/demo-seed/credentials.txt`) survives as a constant only so the
  seeder can *name* such a file: it is never read, never trusted and never deleted, and no
  database resolves to it, because every name now written carries the `credentials-`
  prefix. When one is present beside the run's own file, the report says so.
- **Mode `0600`**, directory created `0700`. **It holds every demo password in clear
  text.** It is never committed (it lives outside the repository), and it must be
  deleted when the demo is over.
- Format: five `#` header lines, a blank line, then one `email: password` line per
  demo user. The first header line carries the generation timestamp:
  `# Sijilna demo credentials — generated <ISO-8601> by Database\Seeders\Demo\DemoSeeder`.
  The lines after it name what the file is bound to — `# Bound to connection [mysql],
  database [krayin]: …` and `# passwords authenticate against that database only.`
- The seeder also **prints every password to the console** in its report, together
  with the row counts and the file path. That terminal scrollback is a copy of the
  file; treat it the same way.

### The file is verified before it is written

After the transaction commits, `DemoSeeder::run()` asserts the operator's own users
untouched and then calls `assertGeneratedPasswordsAuthenticate()`: it reads the three
hashes back out of `users` and has `DemoCredentials::verify()` `Hash::check()` every
generated password against the row it was written to. A password that was generated but
never reached its row is exactly the state that must not be reported as a successful
seed, so a mismatch throws — naming the account, never the password — and **no file is
written**. A run that cannot produce a working login cannot leave a file claiming one.

### If a file is missing, or its passwords do not log in

Bead **crm-4ok** was this failure: one global filename meant a run against a throwaway
database overwrote the file an operator reads for the live one, and every demo login
failed while the seed reported success. Both causes are fixed in the working tree — the
database-bound name above, and the verification above — and pinned by `DemoSeederTest`
("binds the credentials file to the database it describes", "gives every database its own
filename, and keeps the current one for a safe name", "keeps the credentials file
authenticating against the rows after two consecutive runs", "fails loudly when a
generated password does not match the row it was written to").

What is left is an operator check rather than a stale-file minefield, and the two
remedies cost very different things:

1. **Read the file named for the database you are pointed at** — `credentials-<database>.txt`,
   or `credentials-<slug>-<digest>.txt` when the name needed disambiguating — and confirm
   the `# Bound to …` header names that connection and database. A file
   named for another database cannot authenticate against this one, and a file from the
   pre-binding scheme is bound to nothing. Reading it is safe; pasting its passwords when
   the header names another database is what fails. A file restored from a backup, or a
   password changed outside the seeder, is still stale — the binding and the verification
   cover runs of this seeder, not later hand edits.
2. If the passwords still do not log in, pick one, knowing what each costs:
   - **Reset the three demo users' passwords, and nothing else.** Write a new bcrypt
     `users.password` for the three `@sijilna.demo` rows (`php artisan tinker`, or
     `Hash::make()` on the row). This touches no CRM content — leads, contacts, quotes,
     activities, tags, web forms, campaigns and their pivots are all left exactly as they
     are, so the instance stays presentable. It does **not** rewrite the credentials file,
     which then describes passwords that are no longer set: delete that file, or record
     the new passwords beside it yourself. `scripts/create-admin.php` is not this tool — it
     forces `role_id = 1` and rewrites the account's name.
   - **Reseed with §1's command.** It rewrites the credentials file as part of the run, and
     **it destroys every CRM content row on that instance** (§2), including anything a
     colleague entered. That is a reset of the dataset, not a repair of a password: never
     present it as the harmless fix for a credentials problem.

## 5. What the dataset covers

Counts read out of `DemoFixtures` and the two seeders; the suite asserts the same
coverage as lower bounds plus the properties in §1 and §2.

| Module | Rows |
| --- | --- |
| Users | 3 |
| Organizations | 8 (Egyptian companies across Cairo, Giza, Tanta, Alexandria, Sharqia, Aswan) |
| Contacts | 19 — 14 fixtures plus 5 produced by web-form submissions |
| Products | 12 (SKUs priced in EGP) |
| Leads | 27 — 24 fixtures plus 3 from a web form; every stage populated: new 8 (5 fixtures + the 3 form submissions), follow-up 4, prospect 4, negotiation 4, won 4, lost 3 |
| Lead / contact / product tags | 11 / 3 / 2 |
| Quotes | 10 with 22 line items between them; 2 of them expired (`expires_in_days` negative) |
| Activities | 23 — call 9, meeting 7, lunch 3, note 4; 21 on leads, 2 on contacts; 23 calendar participants |
| Email | 5 threads, 9 messages (inbox, sent, draft), 2 tagged |
| Marketing | 2 templates, 2 events, 3 campaigns |
| Web forms | 2 forms with 8 fields between them, 5 submissions |
| Attribute values | more than 100 — the suite asserts `> 100` rather than an exact figure, because the repositories write them |

Dates are stored as day offsets from the day the seeder runs (organizations and
contacts backdated across a 75-day window, activities from −24 to +12 days), so the
dashboard date ranges, "this month" reports and the activity calendar have content
however long after this file was written the seeder runs.

### The dashboard window the dataset declares

The backdating is why the seeder also writes one setting: a one-month window over data
spanning 75 days renders the dashboard empty, so `DemoSeeder::seedDashboardRange()`
records `general.settings.dashboard.date_range = 3_months` in `core_config`, inside the
same transaction as the dataset.

- **It only writes when the installation holds no such row.** An operator's own saved
  range is never overwritten, so a re-seed on an instance that has been trading since
  leaves the reporting window where the operator put it. A row already holding
  `3_months` is reported as already recorded; a row holding anything else is reported
  as left as recorded.
- **The product-wide default is unchanged: one month.**
  `AbstractReporting::getDefaultStartDate()` still falls back to `now()->subMonth()`
  when no row exists, which is the right window for a client whose data is recent.
  This is the demo dataset's behaviour, not a change for real clients: a non-demo
  instance runs no part of this seeder, so nothing here writes to it.
- **Every preset still overrides it.** Choosing «آخر 30 يومًا» gives the 30-day window
  (and its empty panels) and «آخر 3 أشهر» restores the populated one, in both
  directions.
- **It is visible in the seed report.** The run prints the outcome —
  `3_months (recorded; the installation had not chosen one)`, `… (already recorded)` or
  `… (left as recorded, not overwritten)` — beside the row counts and the credentials
  path.


## 6. Full reset of the demo

> ### ⚠ `make db-reset` destroys the named database. There is no undo.
>
> It drops **every table** in that database, re-migrates it, re-seeds it and recreates
> the administrator. The MySQL data lives in the named volume
> `crm-platform-mysql-data`, which is the only copy — a prune deletes it whole, and
> restoring data without the matching `APP_KEY` leaves encrypted columns unreadable
> (`.agents/context/operations/database.md`).

1. **Clear a cached config**, or the target below refuses:
   `php artisan config:clear` (a `bootstrap/cache/config.php` makes artisan read the
   database name from the cache while the guard reads `.env`, so the two can name
   different databases).
2. **Wipe and rebuild the instance, ending fully installed.** `make db-reset` requires
   `CONFIRM=<the database name>` (for this checkout's default instance
   `CONFIRM=krayin`), plus `KRAYIN_ADMIN_EMAIL` and `KRAYIN_ADMIN_PASSWORD`. It refuses
   unless all of the following hold, and the refusal names what is wrong:

   ```bash
   KRAYIN_ADMIN_EMAIL='admin@example.test' KRAYIN_ADMIN_PASSWORD='<chosen>' \
     make db-reset CONFIRM=krayin
   ```

   - `CONFIRM` is exactly the resolved database name (the old generic `CONFIRM=reset`
     is refused), and the name is non-empty;
   - `KRAYIN_ADMIN_EMAIL` and `KRAYIN_ADMIN_PASSWORD` are both set — checked *before*
     anything is destroyed;
   - `APP_ENV` in `.env` is `local` (read from `.env` only: a caller's `APP_ENV` cannot
     make the guard refuse, and it cannot protect an instance either);
   - the resolved database is not the test database and not a MySQL system database;
   - the resolved host is loopback (`127.0.0.1`, `localhost`, `::1`) and the connection
     is `mysql` or `mariadb`;
   - the MySQL container is running;
   - `bootstrap/cache/config.php` does not exist.

   Full detail, and the reason each guard exists, is in
   [`scripts/README.md` §"`make db-reset` refuses more often than it runs"](../../scripts/README.md).
   Because the guards refuse a non-`local` `APP_ENV`, this target cannot be used on the
   homelab demo instance at all — see `deploy/README.md` for that instance.
3. **Seed the demo dataset** with the command in §1.
4. **Log in** with `manager@sijilna.demo` and the password from the database-bound file
   `credentials-<database>.txt` that §4 describes, and check the dashboard, the lead
   pipeline, the quote list and a contact record render.
5. **Delete the credentials file** when the demo is over.

To exercise the guards themselves, point them at a scratch database — never at the
live one:
`KRAYIN_ADMIN_EMAIL=... KRAYIN_ADMIN_PASSWORD=... DB_DATABASE=krayin_scratch CONFIRM=krayin_scratch make db-reset`
(worked example in `scripts/README.md`).

## Sources

- **Code:** `database/seeders/Demo/DemoSeeder.php`, `DemoWiper.php`, `DemoIdentity.php`,
  `DemoCredentials.php`, `DemoFixtures.php`, `DemoSalesSeeder.php`, `DemoMailSeeder.php`,
  `database/seeders/DatabaseSeeder.php`.
- **Tests:** `tests/Feature/Demo/DemoSeederTest.php` (idempotency, the
  non-demo-user-untouched assertion, the reserved-domain delete, per-run bcrypt
  passwords, the credentials path and its database binding, the pre-write password
  verification and its account-not-password failure message, reference integrity,
  module coverage, rollback with no role, installed reference data untouched).
- **Command surface and guards:** `Makefile` (`db-reset`), `scripts/README.md`
  (§`make db-reset` refuses more often than it runs), `deploy/.env.deploy.example`
  (`APP_ENV=production` on a deployed instance).
- **Knowledge base:** `.agents/context/operations/database.md`,
  `.agents/context/operations/provisioning.md`.
- **`crm-4ok` (the one global credentials file):** fixed in the working tree by
   `DemoCredentials::path()` / `provenance()` / `filename()` / `verify()` and `DemoSeeder::run()`'s
  order-of-operations, and pinned by the four `DemoSeederTest` cases named in §4.
