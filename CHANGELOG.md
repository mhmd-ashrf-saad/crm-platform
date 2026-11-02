# CHANGELOG for 2.2

This changelog consists of the bug & security fixes and new features being included in the releases listed below.

## **v2.2.8 (Upcoming)**

* [fixed] Fixed the dashboard's chart axis labels still printing English months and weeks under the Arabic chart titles — the last mixed-language date surface left after `Core::formatDate()` moved to `translatedFormat()`. `formatDateForLabel()` in the lead reporting helper built its bucket labels with Carbon's `format()`, so a month bucket read `Aug 2026`, a day bucket `Aug 26` and a weekly bucket `Week 34, 2026`, both on the dashboard and in the date column of the dashboard PDF. The day and month buckets now go through `core()->formatDate()` like every other date in the product, and the week bucket keeps its number with only the word "Week" translated (`الأسبوع 34، 2026`), because a bare Arabic month would mislabel a bucket that spans seven days. These are display strings only — the key that joins a query row to its interval comes from `formatDateForGrouping()` and nothing parses the label — no bucket, interval, aggregation or query changed, and `en` stays byte-identical (`Aug 26`, `Week 34, 2026`, `Aug 2026`) for every period, checked against the old implementation across four dates. The new `dashboard.index.week-label` key is translated into all ten Admin locales, which the language-parity test requires rather than merely prefers. Verified on the live dashboard in `ar` at 360 and 1440 with day, week and month buckets, and in `en` for the same charts.

* [fixed] Fixed the Arabic validation set missing the `ipv4` and `ipv6` messages: they were the only two keys in `lang/en/validation.php` that `lang/ar/validation.php` did not mirror, so an `ipv4`/`ipv6` rule would have rendered one English sentence inside otherwise-Arabic output while `ip`, `email` and `required` stayed Arabic. Both are added in the neighbouring entries' voice and the two files' key sets are now identical, verified by running a real Laravel validator under locale `ar` (`يجب أن يكون :attribute عنوان IPv4 صحيحًا.`) and under `en`, where nothing changed.

* [fixed] Fixed the shared table dragging the whole page sideways again at 1240-1366px, which the earlier narrow-viewport pass could not see because it only exercised the two callers it had measured. `x-admin::table` moved its 800px floor and fixed layout behind `xl`, but the wrapper becomes `overflow-x-visible` at that same breakpoint, so a table whose column is narrower than 800px overflowed the document instead of scrolling: `/admin/leads/view/{id}?tab=products` measured 198px of page overflow at 1240, 158px at 1280 and 41px at 1366, and the same table also pushed the lead, person and product detail views wider than the viewport because their content column had no `min-w-0` and could not shrink below its widest child. The floor and `table-fixed` now start at `3xl` (1680px), where even a view with a 394px side panel still leaves the table ~1023px, and the three detail views' content column may shrink. All 30 route variants now measure 0px of page overflow at every audited width, and the table keeps its fixed column alignment at 1680 and above.

* [enhancement] Expanded the responsive audit from 19 to 30 route variants (133 to 210 cells) so the shared table's blast radius is measured rather than assumed: `/admin/leads/view/{id}?tab=products` and `?tab=quotes`, `/admin/products/view/{id}` inventory, `/admin/quotes/edit/{id}`, the attribute create and edit forms, the workflow create and edit forms, the web-form create and edit forms and `/admin/settings/warehouses/view/{id}` locations. The premise behind the sweep's earlier scope was slightly wrong and the correction is recorded in the harness: `<x-admin::table>` itself has 9 callers, while its `th`/`td` primitives are composed by 13 views, so the web-form and workflow tables were reachable by the nowrap change but never by the width floor. Ids are resolved from each module's own datagrid JSON at run time — never hardcoded — and the two routes whose table is rendered by a tab that only fetches on activation (warehouse locations, product inventory) are now driven by a tab click before measuring, because loading them and measuring straight away produced a clean zero on a page whose table had not rendered yet.

* [enhancement] Hardened the responsive audit so it can no longer report PASS on a page that is blank, crashed or missing the table it was listing. A cell that never settles — Vue never mounted, or the network never went idle — is now a FAIL instead of a quiet zero, because a Vue exception leaves the server-rendered shell in place where `scrollWidth` equals `innerWidth`. Every cell also records uncaught `pageerror`s and console errors raised while it loaded and surfaces them per cell in `matrix.json` and `matrix.md`, so "no console errors" is evidence from inside the harness rather than a claim made beside it. The shared table marks its wrapper with `data-e2e-shared-table` and each cell records whether that component rendered at all and with how many rows, so a route whose table needs data this dataset does not have is reported as a coverage gap instead of an implicit pass.

* [enhancement] Tracked both `package-lock.json` files and made the committed admin bundles reproducible. A blanket `package-lock.json` rule in `.gitignore` (and again in the Admin package's own `.gitignore`) meant neither lockfile was tracked, so `public/admin/build` could not be rebuilt from anything in the repository: a fresh checkout resolved whatever day's version the declared ranges offered. Rebuilds now use `npm ci` against the pinned graph — `laravel-echo` 2.6.3, `vue` 3.5.44, `pusher-js` 8.6.0, `vite` 5.4.21 — and the rebuilt bundle is committed in the same change as the pin, so a bisect cannot land on a commit whose markup and assets disagree.

* [fixed] Recorded the `laravel-echo` version drift that was hiding inside this branch's asset rebuilds, so nobody later blames a responsive change for a websocket bug. `package.json` declares `^2.6.1`; the first unlockfiled rebuild resolved and shipped **2.6.3**, and it is that version in the committed `echo` chunk. No code changed and no dependency was upgraded deliberately in this pass — the pin now records 2.6.3 as the version the bundles actually contain, which is why the fix is a tracked lockfile rather than a revert. The same rebuild also drops 119 bytes of dead CSS (`ltr:mr-6`, `rtl:ml-6`) that the Arabic form fix had removed from the markup without rebuilding the stylesheet.

* [fixed] Made dates render in the interface's own language everywhere, through the single path the product formats them with. `Core::formatDate()` called Carbon's `format()`, which prints English month names and meridiem whatever the active locale is, so an Arabic interface showed `26 Aug 2026 12:00AM`, `Oct 30` and `Wed Nov 25, 2026` on the lead, person, quote and mail screens and in the PDFs; it now uses `translatedFormat()` with the active locale, which leaves `en` — and any locale with no translation of its own — byte-identical, and changes nothing about the format strings the callers pass.

* [fixed] Fixed the mobile card layout of every datagrid printing `[object Object]` instead of names: the card view reused the table's `v-html="record[column.index]"`, which stringified every column that is not pre-rendered markup — a user's name arrives as `{image, name}`, mail tags as an array, a status as `1`. On a phone the four users were indistinguishable, so this was data loss rather than cosmetics. One formatter in the shared datagrid table component now serves all twelve card call sites: a string that carries markup passes through untouched so no HTML column changes, arrays are joined, objects yield their own label (escaped), a boolean column reads as the yes/no label its filter uses, and the settings/users and marketing/campaigns cards render their name and status columns the way their own table views do, so the status pill is back.

* [fixed] Fixed left-to-right values being mangled by the right-to-left interface: an address truncated to `...sjilna.demo`, keeping the useless domain and discarding the local part, and `+20 106 555 1203` printed as `1203 555 106 20+` at 360 and at 1440 because a run of digits and neutrals takes its direction from the surrounding paragraph. Truncating elements now carry `dir="ltr"`, moving the ellipsis to the end of the value and keeping the cell's own right alignment, and scalars with no Arabic content are wrapped in `<bdi dir="ltr">` in the datagrid formatter and in the lead and person views. Dates are localised rather than only isolated: `core()->formatDate()`, the mail grid's date closure and the attribute label formatter use `translatedFormat()` with the active locale, which leaves `en` byte-identical, and the activity timeline — which formats in JavaScript — uses `Intl.DateTimeFormat` with the document locale instead of a hardcoded `en-US`. `26 Aug 2026 12:00AM`, `Oct 30` and `Wed Nov 25, 2026` now read as Arabic dates.

* [fixed] Fixed the datagrid being crushed and unreachable at exactly 1024px. The content box is about 790px there and the table gives every column an equal share, so a 13-column grid had 45px per cell: `00:00:00` broke over two lines, headers fragmented, "New Business" split, and the two row actions were squeezed into one 45px column that `overflow-hidden` then clipped. Horizontal scrolling was rejected because the action column sits at the inline end — the leftmost column in RTL — so a scrollbar would hide exactly the view/edit control that must stay reachable. The table-to-card switch moved from `lg` (1024) to `xl` (1240), the first width where the audited table fits: below it the card layout renders, above it the table does. `public/admin/build` was rebuilt because `max-xl:` utilities were not in the compiled Tailwind bundle, which is why the class change alone was a no-op at runtime. The audit's clipped counts at 1024 fell from 6/15/7 to 1/1/1 for the leads, persons and users grids.

* [fixed] Fixed the week calendar on phones: vue-cal swaps the weekday label down to its one-letter form when a column is narrow, so the heading row read as fragments (`ا`, `حدث`) instead of day names, and an event cell had room for nothing but an ellipsis — every entry was a bare coloured bar. The full weekday name is kept and shrunk to fit, an event shows its start time below 768px (the range returns above it), both time forms are isolated with `<bdi dir="ltr">` so `10:30 - 12:00` is not reordered, and the hour grid's scrollbar — previously invisible until touched, which made the day look as if it stopped at 11:00 — is now always visible; scrolling reaches 23:00 at 360px.

* [fixed] Fixed the Arabic interface showing English validation sentences wrapped around Arabic attribute names: a rejected quote form read "The اسم المنتج field is required." The repository shipped `lang/en/` and no `lang/ar/`, so every server-side string fell back to English while attribute names came from the Arabic attribute set; `lang/ar/validation.php` now mirrors the English file's keys and nesting. The message actually seen on that form was client-side — vee-validate's default locale is `en` and the plugin only called `setLocale()` on the `load` event, so a field validated on mount produced an English message that never refreshed — and the locale is now set during install as well.

* [fixed] Fixed the email and contact-number rows overhanging their container by 24px in the Arabic interface: the work/home select carried `rtl:ml-6` on top of `w-full`, so its margin sat outside its own box (77px of box, 101px of content) and the contact section of `leads/create` reported a clipped overhang at 360px. The space it was meant to reserve is already the select's arrow padding, so the margin is dropped; the person and organization forms use the same rows and stop overhanging too.

* [fixed] Fixed 15 of the 133 responsive audit cells overflowing the page horizontally at 360/390/414/768/1024px in the Arabic RTL admin — the lead and quote create forms by 473/443/419/65/8px, the lead view by 198/168/144px and again at 1024px by 143px, and the dashboard by 25px at 360px. Three separate causes, each measured before it was touched. `x-admin::table` hard-coded `min-w-[800px]` plus `whitespace-nowrap` on every `th`/`td`, so the line-item tables demanded 800px at every viewport; the floor and the nowrap now start at `xl` (1240px), where the content box is finally wide enough, `xl:table-fixed` keeps the column alignment there, and the table is wrapped in an `overflow-x-auto` container that becomes `overflow-x-visible` at `xl` so a table whose content cannot wrap scrolls itself instead of the document. The lead view's pipeline strip was a single non-wrapping flex row of `nowrap` chips that could not shrink below 542px, which also explains the 1024px failure — the audit's hypothesis that the 215px sidebar reservation alone caused it was only half right, the reservation merely left the right-hand panel narrower than the strip's unshrinkable width; letting the strip wrap drops that panel's min-content to the widest single chip. The dashboard's date-range and export controls were a non-wrapping row of fixed-width children 361px wide in a 328px box and now wrap. The audit matrix now reports 133 pass / 0 fail, and every one of the four pages measures 0px of overflow at all seven widths.

* [fixed] Fixed iOS Safari zooming the whole page in every time a form field was focused: every admin control computed to 14px (`text-sm`), below iOS's 16px threshold, on 45 of the 57 phone cells the audit measures. Controls are now 16px below the `lg` breakpoint — the breakpoint the admin layout itself switches to its mobile chrome at — so tablet and desktop typography is untouched (verified 16px at 360/768 and 14px at 1024/1440). The rule needs two `:not([type=...])` pseudo-classes to out-specify the `.text-sm` utility without `!important`; an element-only selector loses to a class selector wherever it is written. `public/admin/build` was rebuilt with `npm install && npm run build` and verified afterwards: `/admin/login` 200 and RTL Arabic from the new hashed bundles, a real sign-in reaching the dashboard with all three charts drawn, and zero console errors across 18 admin routes at 360px.

* [feature] Added `Database\Seeders\Demo\DemoSeeder` — a reproducible, Arabic-first demo dataset that wipes and rebuilds every user-facing module (users, organizations, contacts, products, leads across all six installed stages, quotes with line items and totals that reconcile, activities, tags, campaigns, email threads, web forms and attribute values) for a client demo or a screenshot pass, run with `php artisan db:seed --class='Database\Seeders\Demo\DemoSeeder' --force`. Content is static hand-written fixture data rather than generated at run time, because the random-data generator is a development dependency and the production image is built with `composer install --no-dev`. The wipe deletes only users whose address is on the reserved `@sijilna.demo` domain and asserts that every other user row is byte-identical afterwards, leaving the operator's own login intact; installed reference data — pipelines, stages, sources, types, roles, attributes and the installer's email templates — is read and never recreated or deleted; dates are relative to the day it runs so the dashboard ranges and the "this month" reports are never empty. Demo passwords are generated per run and written to a scratch credentials file outside the repository, never committed.

* [feature] Added a client-facing Arabic user manual under `docs/client-guide/` — 17 chapters covering orientation, login, dashboard, leads and pipeline, the lead record, contacts, activities, quotes, products and inventory, mail, team and permissions, configuration, automation, web forms and import, troubleshooting, limits and provider, plus a glossary — with a verified-facts digest and a screenshot catalog for the writer and operator.

* [fixed] Fixed four false limits in the client manual and its internal fact base — the documents a client actually reads. They said the Google Contacts and Web Forms screens had no menu entry and no permission key, that the installation language could not be changed, and that an activity never sends email. Each was contradicted by the code: `GoogleContactServiceProvider` and `WebFormServiceProvider` both `mergeConfigFrom` their `Config/menu.php` into `menu.admin` under `settings.other_settings` and their `Config/acl.php` into `acl`, so the Settings page renders both as cards under **Other Settings** with real permission keys (the Google card keeps its English label, which is why the shipped "connect your Google account" message looks like it points nowhere); `general.general.locale_settings.locale` in `packages/Webkul/Admin/src/Config/core_config.php` makes the single installation-wide language changeable in Configuration → General, and only the currency stays install-time; and `Webkul\Automation\Helpers\Entity\Activity` queues mail for the `send_email_to_sales_owner` and `send_email_to_participants` workflow actions with an `invite.ics` attachment, fired on `activity.create.after` and `activity.update.after`. Chapter 16, chapter 14, chapter 17, chapter 06 and the fact base now state what the product does, and the limit that is real is kept: nothing fires at an activity's scheduled time, and only two commands are scheduled in the whole product.

* [feature] Added a tag-gated pull-based deploy pipeline for the homelab: `.github/workflows/deploy-tag.yml` publishes `deploy-<short sha>` only once every check-run on a `main` commit is completed and green, and `deploy/deploy.sh` — run by `sijilna-deploy.timer` every three minutes — dumps the database, deploys the newest tag that is reachable from `origin/main`, rebuilds only when the image inputs changed, gates on an HTTP 200 health check, and rolls back to the previous tag and image (restoring the pre-deploy dump when migrations were applied) with a single Telegram alert on failure. Deploys are documented end to end in `deploy/README.md`.

* [fixed] Fixed the Admin Playwright suite failing intermittently on the organization and person create cases: the tests typed into the form before the page had finished loading, so the mount discarded the value and the form was never submitted.

* [enhancement] Scoped the contact lookup pickers to their own popup in the Playwright suite, and made each organization case create the record it acts on, so one failure no longer reports as three.

* [enhancement] Moved the `beads` skill into the canonical `.github/skills` directory, linked it from `.agents/skills`, and made the skills-consistency workflow run for changes under `.agents/`.

* [feature] Added a self-contained production container stack under `deploy/` — PHP-FPM 8.3, nginx and MySQL 8.4 on host loopback port 3102, built from `deploy/docker-compose.yml` — plus an `APP_FORCE_HTTPS` flag that forces `https://` URLs where a Cloudflare Tunnel terminates TLS in front of the app.

* [fixed] Fixed PDF export failing with a server error in the production image whenever the interface was in Arabic or Hebrew, because mPDF could not create its temporary directory inside the image.

* [fixed] Fixed a green commit on `main` sometimes never being tagged `deploy-<sha>` — leaving the homelab silently on an older tag — because the tag workflow exited as soon as any other workflow's checks were still finishing and trusted a later event to re-evaluate it; it now re-checks the checks and workflow runs every 20 seconds for up to six minutes instead, still fails at once on a real failure, and only gives up after that deadline.

* [fixed] Fixed a green commit on `main` that still never reached the homelab because nothing tagged it after the fact: a scheduled reconciler in `.github/workflows/reconcile-deploy-tags.yml` sweeps recent `main` commits, backfills any `deploy-*` tag that is missing, and raises a tracked alarm when `main`'s tip stays green and untagged across runs rather than waiting on an event that never arrives.

* [fixed] Fixed a hung Playwright shard or a stalled browser download stalling a deploy with no upper bound: every shard job and the browser install step in `.github/workflows/admin_playwright_tests.yml` are now time-bounded, and the install is bounded by a single measured attempt that fails the job visibly.

* [fixed] Fixed the Playwright install bound itself taking CI red: a retry loop killed a slow `--with-deps` install at the 240 second cap, which left `dpkg` locked or half-configured, so each retry failed instantly and 5 of 15 shards failed. Measured install time on healthy shards was 42-80 seconds, so the retry is gone and the install is one 600 second attempt inside a 15 minute step, reporting a timeout (`124`/`137`) and a genuine failure separately and never retrying after a kill.

* [enhancement] Added unattended provisioning for a second Sijilna instance: `deploy/provision-instance.sh` clones a checkout, writes the `.env`, generates the database passwords and instance identity, and refuses to proceed against an `.env` that is not production-safe (`APP_ENV=production`, `APP_DEBUG=false`), while `deploy/docker-compose.yml` takes the project, container, image, volume and published-port names from seven `SIJILNA_*` keys so two instances cannot collide.

* [fixed] Fixed a failed mail send being invisible: the mail transport now logs the server's own error at `ERROR` before re-throwing, a production instance booted on a mailer that cannot fail (`log`, `array`, `null`) logs `CRITICAL`, and `make mail-test` sends a real message and exits non-zero when the relay refuses it.

* [fixed] Fixed the Admin e2e shards failing on a throttled apt mirror. The step had been apt-installing 109 MB and 166 packages of system libraries for firefox and webkit, for a suite whose Playwright config declares a single `chromium` project; installing `--with-deps chromium` alone cut that to 25.9 MB, but `azure.archive.ubuntu.com` served it at ~44 KB/s (one 5.6 MB package took 188 seconds), so the download still landed on the 600 second cap and killed 6 of 15 shards. The remaining packages were almost entirely fonts, and the `ubuntu-24.04` runner image already ships chromium's complete library closure through Chrome's own dependency chain — so the dependency install is now dropped altogether, leaving only the browser build (which `cdn.playwright.dev` serves in seconds and which is now cached across shards). The 600 second bound and its visible-failure reporting are unchanged.

* [feature] Added the sales kit under `docs/sales/` — an Arabic print-ready one-page offer sheet (`offer-sheet-ar.md`) carrying the three packages, the founding-client terms and an explicit "what this offer does not include" list, alongside the internal `pricing.md`, a 40-account target-list method and an Arabic discovery script — and recorded the commercial model in `pricing.md` §2 after comparing a hosted per-seat subscription (rejected: it makes us the operator of every client's server and puts us on the same per-seat ladder as Zoho, §7.1) and implementation-plus-support with no licence (rejected: revenue stops at go-live, and it anchors the sale in the 50,000–250,000 EGP ERP project market, §7.3) against the chosen perpetual licence + paid setup + mandatory annual maintenance at ≈18% of the licence, the market norm.

* [enhancement] Documented how Sijilna is supported in `README.md`: the annual maintenance contract and what it covers (updates and security patches for the 2.2 line, upgrade assistance, an answer when something breaks), the written 17-chapter Arabic manual as the self-service reference, the fact that a lapsed installation keeps running on a perpetual licence, and that no public tracker, forum or community exists — so nobody goes looking for the upstream project's channels — with the upstream remote and the merge-2.2-into-main procedure pointed at `AGENTS.md`.

* [feature] Added a compliance pack under `docs/legal/` — a data-residency statement, a privacy notice, a data processing agreement and terms of service — answering the five questions an Egyptian business buyer asks (where the data lives, who can see it, how long it is kept, what happens at termination, and on what terms the product is provided). Every statement cites the repository file it is built on; commitments the operator has not made are marked DECISION rather than written as defaults, and the 26 open ones are collected in `docs/ops/decisions-to-confirm.md`. No price appears in a legal document: `docs/sales/pricing.md` stays the single source and contracts carry the numbers in a signed order form.

* [feature] Added an operator runbook set under `docs/ops/` — `support-sla.md` (channel, business hours, severity 1–4 with separate response and resolution targets, in/out of scope, the report template a client fills, the per-client handover sheet) and `incident-response.md` (the first five minutes, who is notified in what order and on what cadence, seven known total-loss vectors led by the `storage/installed` kill-switch, a restore procedure that verifies by row counts rather than file size, the termination-deletion procedure, and an after-action review). The SLA is written against a one-operator business: no 24/7 cover, no uptime percentage, no fix-time guarantee, and no service credits.

* [fixed] Fixed the compliance and support documents being uncommittable: `.gitignore` ignored `/docs/*` with exceptions only for `docs/client-guide/`, so new `docs/ops/` and `docs/legal/` files were untracked by default. Added `!/docs/ops/` and `!/docs/legal/` in the same style.

* [security] Recorded the dependency advisory position: `composer audit --locked` against the committed lockfile reports 16 advisories across 9 packages — 1 critical (`laravel/framework` TokenGuard bypass, latent here because no `api` guard exists), 6 high, 7 medium and 2 low — plus findings for missing security headers, an unset `SESSION_SECURE_COOKIE`, an unvalidated file-type upload path, ACL gaps on mass delete and export, and `.env.example` shipping debug mode. `docs/ops/security-review.md` gives each finding a severity, a reachability argument and a fix command; no dependency was bumped, because a bump without a green suite is unverified. Tracked as `crm-1ra`, `crm-50q`, `crm-epc` and `crm-3ef`.

* [enhancement] Cut the Actions minutes this private fork pays for: CI, Linting Tests and Admin | Playwright Tests keep their guards but no longer run on a docs-, brand- or `.agents`-only push (the Playwright suite is now allow-listed to the paths that can change the admin UI, and its 15 shards no longer run on a Markdown commit), each gained concurrency with `cancel-in-progress` so a superseded push stops burning runners, Linting Tests no longer commits formatting into `pull_request` head branches, and `ci.yml` moved off the retired `actions/checkout@v2`. The decisions, the workflows deliberately left alone (the deploy gate and the tag reconciler) and the remaining cost are recorded in `docs/ops/ci-workflows.md`.

* [fixed] Fixed a stray `SIJILNA_*` key in the live `.env` being able to bring a second, empty stack up beside the live one: `deploy/deploy.sh` now refuses to run when any `SIJILNA_*` key — or one of the seven instance-identity keys in its own environment — is present, naming the keys and the file before it fetches, dumps, checks out or restarts anything, and every compose invocation is pinned with `-p sijilna` so an exported `COMPOSE_PROJECT_NAME` cannot move the project either.

* [fixed] Fixed an ops-only commit forcing a full image rebuild in the deploy pipeline: `deploy/docker-compose.yml` and `deploy/provision-instance.sh` are now excluded from the Docker build context (`.dockerignore`) and from the rebuild classification (`NON_IMAGE_GLOBS` in `deploy/deploy.sh`), so changing them deploys in seconds instead of re-running `composer install` for 366–500 s.

* [fixed] Fixed "my `.env` edit did nothing" on the deployed instance: `deploy/README.md` now documents that `env_file: .env` is read once at container creation and becomes process environment that outranks Laravel's own `.env` reading, so a `.env` edit needs the app container recreated (`docker compose -p sijilna -f deploy/docker-compose.yml up -d --force-recreate --no-deps app`), not restarted, and `config:clear` cannot help — with a three-step recipe proving the running container sees the new value; the two troubleshooting entries that recommended `restart app` were corrected.

* [fixed] Fixed large client documents being rejected before the application saw them: the application's upload ceilings were scattered literals, and the image-upload rule capped at 8 MB. They now live in one configuration file, `packages/Webkul/Core/src/Config/uploads.php`, read by the editor image upload (`UPLOAD_MAX_SIZE_KB`, 64 MB) and the mail attachment validator (`UPLOAD_MAIL_ATTACHMENT_MAX_KB`, 20 MB — kept lower because an attachment is read into memory and base64-encoded for transport). Verified end to end with a 12,338,004-byte 12-page scanned invoice: stored, listed in the interface, downloaded at 200 `application/pdf` with the same checksum, and unchanged after a restart.

* [fixed] Fixed the public web-form upload ceiling being an unexplained literal: it stays at 2 MB (`UPLOAD_WEB_FORM_MAX_SIZE_KB`) because that endpoint is anonymous, unthrottled and writes to a publicly served disk — `WebFormFileUploadTest` refuses a 4 MB submission for that reason — and it is now documented next to the 64 MB ceiling instead of looking like an oversight.

* [fixed] Fixed PDF exports announcing themselves as web pages: `/admin/dashboard/export-pdf` returned a valid PDF body with `Content-Type: text/html; charset=utf-8` in the Arabic/mPDF branch, because `PDFHandler` streams the mPDF output without a MIME type and `streamDownload` falls back to the response default. Both files involved are upstream Krayin code this fork may not edit, so a package-level middleware (`Webkul\Admin\Http\Middleware\ForcePdfContentType`, on the `web` group) now sets `application/pdf` for any response whose `Content-Disposition` already names a `.pdf`, leaving every other response untouched. Measured before and after: same 54,736-byte 3-page body, header now `application/pdf`; `tests/Feature/PdfExportTest.php` pins both `ar` and `en`.

* [fixed] Fixed five admin grids offering a mass-delete action to a role that may not delete: the Lead, Quote, Product, Organization and Activity datagrids registered the action unconditionally, so a user without the module's delete permission was shown a control whose only possible outcome was a 401. Server-side enforcement was never the missing half — `admin.{module}.mass_delete` already maps to that module's delete permission in the route→ACL map and the Bouncer middleware already refused the request — so the leak was the button, not the deletion. Each action is now wrapped in the same `bouncer()->hasPermission()` guard `PersonDataGrid` had been using, and `tests/Feature/Security/MassActionPermissionTest.php` pins both halves: the action is absent from the grid payload for a role lacking the delete permission and present for one holding it, and a forged request to the route still answers 401.

* [fixed] Fixed `file`-type attributes accepting any upload at all and storing it on the public disk: `AttributeForm` appended its `mimes` allowlist for the `image` type only, so a `file` attribute fell through to a bare `nullable` rule and `AttributeValueRepository` wrote whatever arrived into the disk the web server hands back verbatim — a stored `.html` is same-origin content and a stored `.php` is offered to the PHP handler. A `file` attribute now accepts documents only (csv, doc, docx, ods, odt, pdf, ppt, pptx, rtf, txt, xls, xlsx, zip), an allowlist rather than a list of forbidden names so a format nobody listed is refused by omission, and the same check is repeated where the file is actually written, because a lead form carries no rule at all for file attributes and an import validates nothing: an executable extension reaching the repository is deleted, leaving the attribute with no value rather than a same-origin URL to a payload.

* [fixed] Fixed a value typed into an admin form coming back empty: the admin and login layouts mounted Vue on the window `load` event, which waits for every subresource — the stylesheet, the images, the preloaded logo — while the server-rendered Blade fields were already interactive, so anything typed in that window was written into a node Vue had not taken over and the mount re-rendered the field from its own still-empty state. The mount now waits for `DOMContentLoaded`, which fires without waiting for a single subresource and is still after every deferred module script, with a `readyState` branch for a document that reaches the script already parsed (a bfcache restore), where waiting for an event that has fired would leave the page inert; measured in Chrome on a throttled connection, the un-hydrated window dropped from ~2.4 s to 0. Both layouts are overridden from `packages/Sijilna/Brand/` rather than a core file, and `tests/Feature/AdminAppMountTimingTest.php` pins the wiring.

* [fixed] Fixed the production stack shipping no security headers at all: `deploy/docker/nginx.conf` now sends `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, a `Permissions-Policy` denying camera, microphone and geolocation, and `Strict-Transport-Security: max-age=15552000`, each with `always` because nginx otherwise omits them on exactly the 4xx/5xx responses an attack probe produces. The set is restated inside the `/build/` and `/storage/` locations, because a location that declares its own `add_header` loses the inherited set entirely — measured: without the restatement those responses carried none of it. A CSP is deliberately not included: the admin UI is built from in-DOM templates and 145 Blade files carry inline `<script>` blocks and `on*` handlers, so a policy strict enough to be worth having needs `'unsafe-inline'` or `'unsafe-eval'` and would white-screen production, which is worse than the header that is missing today. `SESSION_SECURE_COOKIE` is now documented in both env templates — `false` for local plain-HTTP development, `true` for a deployed instance — and an existing instance needs the flag added to its `.env` and the app container recreated, because `env_file` is read once at container creation.

* [feature] Added `docs/ops/secrets-handling.md` — every secret the product holds and where it is also copied unintentionally, per-instance provisioning with the verification an operator runs before handover, per-secret rotation with its blast radius in one table, `APP_KEY` as the one rotation that can destroy data, access control and where a secret must never appear, a leak response that contains before it rotates, an operator checklist, and an explicit list of what the document does not claim.

## **v2.2.7 (16th of Oct 2026)**

* [fixed] Fixed a web form submission creating a duplicate contact when the address differed only in case.

* [fixed] Fixed an empty web form submission answering with a server error.

* [feature] Added quotes to Quick Add.

* [fixed] Fixed creating a quote without line items failing with `Undefined array key "items"`. 

* [fixed] Fixed the leads settings control moving when switching between the kanban and table views.

* [feature] Added notifications.

* [feature] Update data filter at dashboard.

* [feature] Mega Search Will open into modal.

* [feature] Added a per-section record count to the mega search. 

* [fixed] Fixed the lookup field's search field.

* [fixed] Fixed saving a contact with the phone left blank answering with a server error.

* [fixed] Fixed picking a lead on a quote leaving the quote unsaveable and unlinked from that lead.

* [fixed] Fixed picking a lead on a quote replacing the existing items instead of adding to them.

* [fixed] Fixed a lead's page erroring when its contact had no phone number.

* [fixed] Fixed removing a quote from a lead failing with no such lead.

* [fixed] Fixed a lead and its quote not holding the same products.

* [fixed] Fixed opening a mail outside drafts erroring under any locale but English.

* [fixed] Added the missing Spanish and Brazilian Portuguese translations.

* [fixed] Fixed the language file test passing while a translation was missing.

* [security] Improved in Security.

## **v2.2.6 (10th of Sept 2026)**

* [security] Improved in Security.

* [feature] Added MariaDB support.

* [feature] Added customizable lead card information. Datagrid columns and Kanban lead card fields can now be chosen per user through new column and card settings components.

* [feature] Added PDF export for dashboard reports, available from the dashboard alongside the existing views.

* [feature] Added Japanese (`ja`) translation for the Admin, Installer, GoogleContact and WebForm packages.

* [feature] Added an associated group column to the users grid in Settings > Users.

* [fixed] Fixed duplicated activity handling across the lead, person, product and warehouse activity controllers by consolidating the shared logic, and added the missing Japanese activity translations.

* [fixed] Fixed the data transfer import queue processing inconsistently, and corrected the form control group rendering used by the import screen.

* [fixed] Fixed the Google Contact settings screen erroring when no account was connected, and added the corresponding translations.

* [fixed] Fixed the mail ACL mapping so email actions are checked against the correct permission.

* [fixed] Fixed people created from a lead being saved with a null `user_id`, which hid them from the person listing for users restricted to group or individual data scope. They are now assigned to the lead owner, falling back to the acting user.

* [fixed] Fixed the stage API resource omitting `lead_pipeline_id`, so stages could not be matched to their pipeline.

* [fixed] Fixed email attachment downloads being blocked by the URL sanitizer middleware.

* [fixed] Added the missing Chinese translations for the users grid's associated group column.

* [fixed] Fixed flaky admin end-to-end tests around organization owner lookup, lead creation and rich-text comment fields.

* [fixed] Fixed creating or updating a lead failing with a server error (HTTP 500) when a product row was submitted without a `product_id`. The request is now rejected with a validation error.

* [fixed] Fixed the expected close date of a lead being cleared when its stage was changed (Kanban drag and drop, the stage bar, won/lost) or another field was edited inline from the lead view.

* [security] Fixed user and role listings not being scoped by the acting user's data scope, which allowed users to see records outside their own group. Roles now track their creator via a new `created_by` column.

* [security] Hardened the admin ACL middleware to fail closed. An administrative route with no ACL mapping is now denied rather than allowed, inheriting the permission of its nearest mapped ancestor, with a narrow allow-list for authentication, self-service account management and generic UI helpers.

* [security] Removed SVG from the allowed upload types for the admin logo and favicon configuration fields.

* [security] Fixed a security issue configuration file uploads.

* [security] Fixed a security issue activity notes and the TinyMCE editor component.

* [security] Fixed a security issue in the web form embed view.

* [security] Fixed a security issue in attribute downloads.

* [security] Fixed a security issue in mail links.

* [security] Fixed broken tag handling in the tag settings controller.

* [security] Fixed SVG sanitization bypasses in media and configuration file uploads.

* [security] Secured installer APIs.

## **v2.2.5 (4th of Aug 2026)**

* #2631[fixed] Fixed the persons CSV import creating duplicate records and dropping select attribute values on re-import. Existing people are now updated by matched email regardless of a changed phone or organization (so the reported count is accurate), and select/multiselect option labels (or ids) are resolved to their option ids instead of being stored as `0`.

* #2630[fixed] Fixed date attributes in the persons CSV import silently saving as `0000-00-00`. Spreadsheet serial numbers and regional formats such as `DD/MM/YYYY` are now normalised to a valid date, and a value that cannot be parsed is reported as a row error instead of being stored as a zero date.

* [feature] Added Chinese (Simplified) `zh_CN` translation for the Admin, Installer, DataTransfer, WebForm and Core packages.

* [feature] Added a configurable default dashboard date range — 1 month, 3 months, 9 months, 1 year, 2 years or a custom number of days — under Configuration > General > Settings > Dashboard Configurations.

* [fixed] Fixed menu item names set in Configuration not applying to section pages, breadcrumbs and the mobile sidebar. Previously only the desktop sidebar reflected a rename.

* [fixed] Fixed renaming the "Mail" and "Contacts" menu items having no effect anywhere, as their configuration fields did not match the actual menu keys.

* [fixed] Fixed the dashboard date range label omitting the year on ranges spanning more than one calendar year, which rendered as "30 Jul - 30 Jul".

* [fixed] Fixed Arabic DataTransfer translations never loading, as the file was named `ar/ar.php` instead of `ar/app.php`.

* [fixed] Fixed the missing Korean translation for the "None" input validation option on the create and edit attribute forms.

* [enhancement] Moved the Core and DataTransfer package translations into the Admin package. Only packages that ship their own Blade views now carry a `Resources/lang` directory.

* [enhancement] Reduced database queries on every admin page by loading the configured menu names in a single query instead of one per menu item.

* [enhancement] Documented the localization convention in the `crm-package-development` agent skill and in AGENTS.md.
* [feature] Added a collapse/expand toggle to the admin sidebar, matching the Bagisto admin. The choice is remembered across page loads, and page content now reflows to the sidebar width instead of being overlaid by it. The sidebar no longer expands on hover; it is controlled by the toggle only.
* #2614[security] Fixed unauthenticated installer access and executable email attachment upload vulnerabilities.

* #2612[feature] Added import and export support for custom attributes for Leads and Persons.

* #2609[feature] Added Google Contacts export for Persons with Google account connection, duplicate detection, queued export progress, and result summary.

* #2608[fixed] Added missing "none" key to the Korean locale for attribute validation.

* #2606[feature] Added a collapse/expand toggle to the admin sidebar, matching the Bagisto admin. The choice is remembered across page loads, and page content now reflows to the sidebar width instead of being overlaid by it. The sidebar no longer expands on hover; it is controlled by the toggle only.

* #2606[feature] Added an option to show or hide the "Powered by" bar under Configuration > General > Settings > Powered by Section Configurations.

* #2603[feature] Added Korean translations for the Installer, DataTransfer, WebForm, and Core packages.

* #2602[feature] Added Korean translation support for the Admin package.

* #2600[fixed] Fixed invalid activity calendar .ics date-times by emitting UTC RFC 5545 values.

* #2592[security] Fixed webhook validation to reject internal endpoint URLs.

* #2580[enhancement] Added a "None" option to input validation for text attributes.

## **v2.2.4 (20th of July 2026)** *Release*

* #2590[fixed] Fixed page does not refresh after creating a record via Quick Add.

* #2589[fixed] Fixed Quick Add not working for users with group and individual permissions.

* #2582[fixed] Fixed pipeline field visible on public webform.

* #2581[enhancement] Fixed responsive UI issues when page is zoomed.

* #2579[feature] Allow group selection for individual view permission users.

* #2575[enhancement] Added previous month's sales update in Kanban view.

* #2573[enhancement] Added dashboard support for multiple pipelines.

* #2572[enhancement] Added filter by tag option in Contacts > Persons.

* #2571[fixed] Fixed issue with lead creation.

* #2570[fixed] Fixed auto-fill lead email issue.

* #2567[fixed] Fixed IDOR agent record access control vulnerability.

* #2563[fixed] Fixed Kanban infinite scroll duplicates issue.

* #2583[fixed] Fixed SQL injection in rotten lead filter.

* #2585[security] Fixed unrestricted file upload vulnerability (CVE-2026-38526).

* #2559[fixed] Fixed agent record access control issue.

* #2556[fixed] Fixed installation config save issue.

* #2550[fixed] Fixed Kanban infinite scroll duplicates.

* #2549[enhancement] Added support tab feature.

* #2548[enhancement] Allow search by phone and email when creating a lead.

* #2546[feature] Quick Attribute now available at lead form.

* #2545[feature] Added agent skills functionality.

* #2544[enhancement] Added validate skills.

* #2543[enhancement] Added Agents Skills folder.

* #2542[fixed] Fixed stored XSS vulnerability in notes field.

* #2541[fixed] Fixed quote description truncation issue.

* #2539[fixed] Fixed lost revenue arrow UI issue.

* #2538[fixed] Fixed missing translations.

* #2501[fixed] Fixed sales owner not saved in organization.

* #2500[fixed] Fixed activities date filter range issue.

* #2479[fixed] Fixed textarea field not rendered in WebForm.

* #2471[fixed] Fixed missing translations for lead won/lost modal.

* #2420[fixed] Added missing mega search translations for settings and configurations.

* #2533[fixed] Fixed GUI installation issue.

* #2419[security] Fixed stored XSS vulnerability in notes field.

* #2454[fixed] Fixed quote description truncation.

* #2407[fixed] Fixed missing translations.

* #2157[fixed] Fixed auto-fill lead email when creating a lead.

* #2258[fixed] Fixed issue with same-as-billing-address field.

## **v2.2.3 (1st of May 2026)** *Release*

* [fixed] Pipline critical issue resolved.

## **v2.2.2 (1st of May 2026)** *Release*

* [fixed] Update Change Log and version.

## **v2.2.1 (1st of May 2026)** *Release*

* [fixed] Quote fields now auto-fill correctly when a quote is linked to a lead.

* [fixed] Fixed price formatting issue.

* [fixed] Fixed Lead Kanban list ordering.

* [fixed] Fixed header block position at the top.

* [fixed] Updated Activity UI.

* [fixed] Admins can now view and share quote details to a person from the quote list.

* [fixed] Fixed submission issue on the web form.

* [fixed] Fixed activity display issue in the Calendar view.

* [fixed] Logo update issue resolved.

* [enhancement] Drag-and-drop support added to Activity. Admins can now change date and time directly from the Calendar view.

* [feature] Quick App feature added for faster access to key CRM actions.

* [feature] Admins can now add or update a person directly from the lead view page.

* [security] Resolved an authentication bypass vulnerability caused by improper access control in the installer.

## **v2.2.0 (17th of March 2026)** *Release*

* **[Laravel 12 Upgrade]** Upgraded framework to Laravel 12

* #2480[enhancement] Codebase updates and refinements.

* #2478[enhancement] Improved class instantiation handling.

* #2472[enhancement] Upgrade to Laravel 12.

* #2470[enhancement] Updated auto_commits.yml configuration.

* #2469[enhancement] General enhancements and optimizations.

* #2468[enhancement] Documentation updates (MD files).

* #2450[fixed] Added ACL support for warehouses.

* #2444[fixed] Improved global search functionality for organizations.
