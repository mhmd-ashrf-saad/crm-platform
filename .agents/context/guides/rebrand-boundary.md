<!-- Context: guides/rebrand-boundary.md | Priority: high | Version: 1.6 | Updated: 2026-10-17 -->

# Rebrand Boundary

Where the fork's rebranding stops. The operator decided this on 2026-10-16; do not
widen it because an internal identifier looks like an oversight — it is not.

**The decision:** every Webkul PHP namespace, every `packages/Webkul/**` path and
every composer package name stays exactly as it is. None of them is in scope.

## Why internals stay

- They are not a client-visible surface. A rename buys nothing a user can see.
- The scale is not small: `git grep -i webkul` matches 611 tracked files and
  about 1,770 lines at the time of writing, ~560 files of them under `packages/`.
- Renaming collides on **every** future `2.2` merge, and upstream releases are
  where fixes arrive. See
  [../operations/environment-and-deployment.md](../operations/environment-and-deployment.md)
  for the branch model.
- Branding is presentation. It reaches users through override and configuration
  surfaces, not through namespaces.

## In scope and out of scope

The operator's decided scope, in substance:

### In scope

- User-facing surfaces: login, admin header/footer, page titles, dashboard text,
  generated PDFs, transactional email templates and signatures, installer
  banner, error pages, favicon.
- Repo identity: README, `composer.json` package name, GitHub description and
  topics, `.env.example` comments.

### Out of scope

- PHP namespaces, `packages/Webkul/**` paths.
- Composer package and dependency names.
- Upstream issue templates and upstream CI workflows.
- `config/krayin-vite.php`, unless an override path exists.
- The `vendor/` tree.

## The invariant

Branding is applied through an override or configuration surface, never by
editing a core Krayin file. That is this checkout's existing never-modify-core
rule applied to naming; the rule and its upgrade-safety reasoning live in
`AGENTS.md` (fork-specific project section) and are not restated here.

## Recorded exceptions — core files the rebrand did change

The invariant above is the rule; these are the exceptions the tree now carries.
Each is a core Krayin file changed on purpose, and merge debt on every `2.2` merge.

| File | Change | Why it could not be an override |
| ---- | ------ | ------------------------------- |
| `bootstrap/providers.php` | Two lines: a `use App\Providers\BrandingServiceProvider;` import and one entry at the end of the returned array | The provider must be registered, and a package that is not a composer package cannot auto-register. The brand *resources* live in `packages/Sijilna/Brand`, but the class sits in `app/Providers/`, which the existing `App\` PSR-4 entry already autoloads — so no `composer.json` autoload change and no lock churn were needed. |
| `packages/Webkul/{Admin,Installer}/src/Resources/lang/{es,fa,ja,ko,pt_BR,tr,vi,zh_CN}/app.php` — 16 files | **Values, not deletions**: the 8 Admin and 15 Installer vendor-branded keys per locale return at their original positions carrying that locale's own pre-rebrand translation, vendor token replaced by the brand. Measured 2026-10-17: `git diff --numstat bf545355` = 7/7 per Admin file and 14/14 per Installer file (15/15 in `ko`, `tr`), 170 changed lines | See *Why the locale values are edited in place* below. |
| `packages/Webkul/{Admin,Installer}/src/Resources/lang/{en,ar}/app.php` — 4 files | Value rewrites, not deletions: 8 keys per Admin locale and 14 per Installer locale, plus the installer caption's `krayin-info`; measured 2026-10-17 against `bf545355` as 8/8 and 15/15 in `en` and in `ar` | `FileLoader::loadNamespaceOverrides()` reads an override only from `lang/vendor/{namespace}/{locale}/app.php`, and `/lang/vendor` is gitignored (`.gitignore:26`), so no override file can be committed — the same constraint as the value edits above. |
| `README.md` | Rewritten — 186 insertions / 119 deletions; 219 lines now, 152 at `HEAD` | Not a loadable surface. |
| `composer.json` | `name` and `description` only | Project identity; nothing loads it as a surface, and it is what moved `composer.lock`'s `content-hash`. |
| `.env.example` | `APP_NAME`, `MAIL_FROM_ADDRESS`, `MAIL_DOMAIN` values (3 lines) | A documented template, never loaded at runtime. |
| `tests/Feature/HelpTest.php` | Deleted, replaced by `tests/Feature/RebrandRegressionTest.php` | The upstream test asserted the vendor help content that the rebrand removes. |
| `composer.lock` | One line, `content-hash` (`92387f91…` → `6133d5a2…`) | Must be regenerated with Composer's own algorithm if any hashed key in `composer.json` changes again; `composer update --lock` cannot resolve offline here. |
| `packages/Webkul/Automation/src/Helpers/Entity/Activity.php` | One line, 318: the iCal `PRODID`, `PRODID:-//Krayincrm//Krayincrm//EN` → `PRODID:-//Sijilna//Sijilna//EN` | The string is hardcoded in a helper with no config key, view or lang surface, and calendar clients render it. |
| `tests/Feature/Security/HtmlSanitizationTest.php` | The sanitisation fixture's URL and text swapped to the brand domain (`krayincrm.com`/`Krayin` → `sijilna.com`/`Sijilna`) | A test fixture has no view or config surface to override; the file sits at an upstream path a `2.2` merge also owns. |
| `AGENTS.md` | Fork identity in the header (3 lines) plus a 17-line "Rebrand boundary" subsection, on a file the fork had already expanded from upstream's 70 lines to 274 at `HEAD` (+204 lines, commit `b9d80f3a`); 288 lines in the working tree | Agent instructions are read directly; nothing loads them as an overridable surface. A merge collides on the whole fork project section, rebrand or not. |

A `2.2` merge sees more than this table: `git diff --name-only upstream/2.2`
also lists pre-existing fork work the rebrand did not touch — `.agents/context/**`,
`Makefile`, `scripts/**`, the Playwright e2e suite, `phpunit.xml`, `CHANGELOG.md`,
both CI workflows and `.gitignore` — reconciled separately.

Re-verified 2026-10-17: `git status --porcelain -- packages/Webkul` still lists
exactly these 21 files, and `-- public/` is empty.

### Why the locale values are edited in place

`Illuminate\Translation\FileLoader::loadNamespaceOverrides()` merges each
override file with `array_replace_recursive($output, $this->files->getRequire($file))`.
That iterates the *override* array's keys, so an override can replace a value
but can never delete a key.

**The shipped mechanism, settled 2026-10-17.** Each of the 23 vendor-branded
keys returns to its original position in each of the eight locales, carrying
that locale's own **pre-rebrand translation** — the text at `bf545355` — with
the vendor token inside the value replaced by the Sijilna brand (`Krayin CRM`,
`Krayincrm`, `Krayin`, `Webkul` → `Sijilna`; `krayincrm.com` → `sijilna.com`;
case-insensitively; never in a key name). Nothing is machine translated and no
locale value is copied from English.

Both upstream spec tests pass with `lang.spec.ts` unedited: key parity holds
because every key is back, and `no locale falls further behind English` holds
because each locale's wording differs from English — measured 2026-10-17 as
exactly the recorded `BACKLOG` (`ar` 1, `pt_BR` 46, `tr` 23, `vi` 41, else 0).

Two earlier mechanisms are withdrawn: **deletion** (failed the parity test on
every missing key) and **English-value rewrites** (restored parity, pushed all
eight locales past `BACKLOG`).

**Honest cost.** The rebrand changed the English wording of 22 keys (8 Admin,
14 Installer — `git diff --numstat bf545355 132393d5` = 8/8 and 14/14), so
176 locale-key pairs (22 × 8) keep the older phrasing re-branded and drift from
the new English until reviewed. No reviewer for those languages exists here.

Upstream restores the vendor *values* on every `2.2` merge, which is what
`tests/Feature/RebrandRegressionTest.php` guards: a merge turns into a visible
test failure instead of a silent regression.

**crm-941 is satisfied in substance — operator ruling, 2026-10-17.** The layer
exists (`packages/Sijilna/Brand` plus the provider) and per-client branding is
configuration, so these 21 core Webkul edits are unavoidable, not an oversight.

## Decided — `CHANGELOG.md` stays untouched

The operator decided the fork does not edit `CHANGELOG.md`, despite the
convention in `AGENTS.md`'s fork section requiring an entry for every change.
Upstream owns the file and rewrites it wholesale on each release, so an entry
buys a conflict on every future `2.2` merge. Deliberate, considered: do not
re-open it as an oversight or add the missing entry.

## Accepted residuals — do not re-file these as bugs

The operator reviewed and accepted these five; none of them is a reachable brand
surface. **The list is exactly five.** The installer's outbound vendor POST is
*not* on it: it was never accepted and is now fixed — see the next section.

1. **The installer's cloud-hosting advert and ASCII banner.**
   `ComposerEvents::cloudHostingBox()` (`ComposerEvents.php:35–41`) prints a
   Krayin Cloud Hosting box linking `https://krayincrm.com/crm-cloud-hosting/`,
   and `Installer/src/Templates/on-boarding.php:13,16` is the ASCII "Krayin"
   banner. Both are console output from a `composer create-project` hook, so no
   view, config or route surface reaches them. **Accepted as written** — the
   copy deck §2.7 rows for them are withdrawn, not pending.
2. **The `storage_path('installed'/'installing')` marker filenames.** Written
   and read in `DatabaseManager`, `InstallerController`, `CanInstall` and the
   `krayin-crm:install` command. Local state markers, not user-facing text;
   renaming them would break upgrade compatibility.
3. **The vendor logo route and controller.** The core class
   (`Webkul\Installer\Http\Controllers\ImageCacheController`) is present but
   **unreachable**: `BrandingServiceProvider` registers the same `cache/{filename}`
   URI and `image_cache` name, and `RouteCollection` indexes routes by method +
   URI, so the provider's handler wins and no admin page reaches the vendor host.
   The residual is the presence of the unused class, not the network fetch.
4. **The `krayin-crm:*` command signatures.** `krayin-crm:version` and
   `krayin-crm:install` keep their names because provisioning and operator
   runbooks call them by name.
5. **The info toast's `#0E90D9` severity tint.** Accepted 2026-10-17 by the
   operator. `Admin/src/Resources/views/components/flash-group/item.blade.php`
   hardcodes `#0E90D9` three times (131, 133, 135) as the info toast severity — a
   `border-left: 8px solid` plus its `icon:` and `stroke:`. It is a generic alert
   severity, not a brand mark: `/admin/login` returns `200` with
   `--brand-color: #0B3C5D`. No config key reaches it, and removing it needs a
   core edit or a copy of an unrelated ~60-line component — both
   disproportionate under the never-modify-core rule.

## Fixed, not accepted — the installer's outbound vendor call

`Webkul\Installer\Listeners\Installer@installed` POSTed the installing
administrator's name, email and site domain to
`https://updates.krayincrm.com/api/updates`. The operator chose to stop all
outbound vendor contact, so this is **fixed**, never one of the five above.

- **Mechanism.** `BrandingServiceProvider::boot()` calls
  `Event::forget('krayin.installed')` in `disableVendorInstallTelemetry()`. The
  event's one listener is registered by
  `Webkul/Installer/src/Providers/InstallerServiceProvider.php:38`, which boots
  *before* the fork's provider, so the listener is bound when the fork forgets
  it.
- **The core listener class still exists but is never invoked**, so nothing
  calls `installed()`; deleting it or the `Event::listen()` line would be a core
  edit, which is why the fork owns the one-line `forget`.
- **Not merge debt** — no second core change; an upstream `2.2` merge that
  restores the listener is caught by `tests/Feature/VendorTelemetryTest.php`.

## Deliberately unchanged — two additions

PHP namespaces, `packages/Webkul/**` paths, module and dependency composer
names, the `vendor/` tree and `config/krayin-vite.php` are covered above and not
repeated. Two more belong on that list:

- **Internal identifiers** — key names such as `krayin-info`, the
  `images/krayin-logo.svg` filename, route names such as `image_cache`. Only the
  translated *values* reach a user.
- **`krayin/krayin-package-generator`** — a real `require-dev` dependency, not a
  rebrand surface.

## Legal basis

Upstream Krayin CRM is MIT licensed, so forking and rebranding are permitted —
see `LICENSE` and `THIRD-PARTY-NOTICES.md`. MIT grants **no trademark rights**,
so the upstream name must never be used in any way that implies this product is
official, endorsed or sponsored by Webkul Software.

## Related

- Never-modify-core rule → `AGENTS.md` (fork-specific project section)
- Attribution and dependency licences → `THIRD-PARTY-NOTICES.md`
- Branch model and upstream merges → [../operations/environment-and-deployment.md](../operations/environment-and-deployment.md)
