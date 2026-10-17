<!-- Context: guides/rebrand-boundary.md | Priority: high | Version: 1.3 | Updated: 2026-10-17 -->

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

The invariant above is the rule; these are the exceptions to it that the tree
now carries. Each is a core Krayin file changed on purpose, and each is merge
debt that an upstream `2.2` merge will try to undo.

| File | Change | Why it could not be an override |
| ---- | ------ | ------------------------------- |
| `bootstrap/providers.php` | Two lines: a `use App\Providers\BrandingServiceProvider;` import and one entry at the end of the returned array | The provider must be registered, and a package that is not a composer package cannot auto-register. The brand *resources* live in `packages/Sijilna/Brand`, but the class sits in `app/Providers/`, which the existing `App\` PSR-4 entry already autoloads — so no `composer.json` autoload change and no lock churn were needed. |
| `packages/Webkul/{Admin,Installer}/src/Resources/lang/{es,fa,ja,ko,pt_BR,tr,vi,zh_CN}/app.php` — 16 files | Measured 2026-10-17: Admin deletes 8–9 vendor keys per locale, Installer 15, so 23–24 per locale | See *Why the locale deletions have no override route* below. |
| `packages/Webkul/{Admin,Installer}/src/Resources/lang/{en,ar}/app.php` — 4 files | Value rewrites, not deletions: 7 keys per Admin locale, 14 per Installer locale (measured 8/8 and 14/14 line substitutions against `upstream/2.2`) | `FileLoader::loadNamespaceOverrides()` reads an override only from `lang/vendor/{namespace}/{locale}/app.php`, and `/lang/vendor` is gitignored (`.gitignore:26`), so no override file can be committed — the same constraint as the deletions above. |
| `README.md` | Rewritten — 186 insertions / 119 deletions; 219 lines now, 152 at `HEAD` | Not a loadable surface. |
| `composer.json` | `name` and `description` only | Project identity; nothing loads it as a surface, and it is what moved `composer.lock`'s `content-hash`. |
| `.env.example` | `APP_NAME`, `MAIL_FROM_ADDRESS`, `MAIL_DOMAIN` values (3 lines) | A documented template, never loaded at runtime. |
| `tests/Feature/HelpTest.php` | Deleted, replaced by `tests/Feature/RebrandRegressionTest.php` | The upstream test asserted the vendor help content that the rebrand removes. |
| `composer.lock` | One line, `content-hash` (`92387f91…` → `6133d5a2…`) | Must be regenerated with Composer's own algorithm if any hashed key in `composer.json` changes again; `composer update --lock` cannot resolve offline here. |
| `packages/Webkul/Automation/src/Helpers/Entity/Activity.php` | One line, 318: the iCal `PRODID`, `PRODID:-//Krayincrm//Krayincrm//EN` → `PRODID:-//Sijilna//Sijilna//EN` | The string is hardcoded in a helper with no config key, view or lang surface, and calendar clients render it. |
| `tests/Feature/Security/HtmlSanitizationTest.php` | The sanitisation fixture's URL and text swapped to the brand domain (`krayincrm.com`/`Krayin` → `sijilna.com`/`Sijilna`) | A test fixture has no view or config surface to override; the file sits at an upstream path a `2.2` merge also owns. |
| `AGENTS.md` | Fork identity in the header (3 lines) plus a 17-line "Rebrand boundary" subsection, on a file the fork had already expanded from upstream's 70 lines to 274 at `HEAD` (+204 lines, commit `b9d80f3a`); 288 lines in the working tree | Agent instructions are read directly; nothing loads them as an overridable surface. A merge collides on the whole fork project section, rebrand or not. |

A `2.2` merge sees more than this table. `git diff --name-only upstream/2.2`
also lists pre-existing fork work the rebrand did not touch — the
`.agents/context/**` tree, `Makefile`, `scripts/**`, the Playwright e2e suite,
`phpunit.xml`, `CHANGELOG.md`, both CI workflows and `.gitignore` — and the merge
reconciles that separately.

Re-verified 2026-10-17 after the brand-config lane: `git status --porcelain --
packages/Webkul` still lists exactly these 21 files, and `-- public/` is empty.

### Why the locale deletions have no override route

`Illuminate\Translation\FileLoader::loadNamespaceOverrides()` merges each
override file with:

```php
$output = array_replace_recursive($output, $this->files->getRequire($file));
```

`array_replace_recursive` iterates the *override* array's keys, so an override
file can replace a key's value but can never delete a key. Those eight locales
have to fall back to the English file for the vendor strings, and deleting the
key from the core locale file is the only way to make that happen. Upstream
restores the keys on every `2.2` merge, which is exactly what
`tests/Feature/RebrandRegressionTest.php` guards — a merge turns into a visible
test failure instead of a silent regression.

## Decided — `CHANGELOG.md` stays untouched

The operator decided the fork does not edit `CHANGELOG.md`, despite the
convention in `AGENTS.md`'s fork section requiring an entry for every change.
Upstream owns the file and rewrites it wholesale on each release, so an entry
buys a conflict on every future `2.2` merge. Deliberate, considered: do not
re-open it as an oversight or add the missing entry.

## Accepted residuals — do not re-file these as bugs

The operator reviewed and accepted these four; each is console output, a local
state marker or an unreachable class rather than a reachable surface. **The list
is still exactly four.** The installer's outbound vendor POST is *not* on it: it
was never accepted and is now fixed — see the next section.

1. **The installer's cloud-hosting advert and ASCII banner.**
   `ComposerEvents::cloudHostingBox()` (`ComposerEvents.php:35–41`) prints a
   Krayin Cloud Hosting box linking `https://krayincrm.com/crm-cloud-hosting/`,
   and `packages/Webkul/Installer/src/Templates/on-boarding.php:13,16` is the
   ASCII "Krayin" banner. Both are written to the console by a `composer
   create-project` post-create hook, so there is no view, config or route
   surface to override. **Accepted as written** — the copy deck §2.7 rewrite
   rows for them are withdrawn, not pending.
2. **The `storage_path('installed')` and `storage_path('installing')` marker
   filenames.** Written and read in `DatabaseManager`, `InstallerController`,
   `CanInstall` and the `krayin-crm:install` command. They are local state
   markers, not user-facing text, and renaming them would break upgrade
   compatibility.
3. **The vendor logo route and controller.** The core class
   (`Webkul\Installer\Http\Controllers\ImageCacheController`) is present but
   **unreachable**: `BrandingServiceProvider` registers the same `cache/{filename}`
   URI and `image_cache` name, and `RouteCollection` indexes routes by method +
   URI, so the provider's handler wins and no admin page reaches the vendor host.
   The residual is the presence of the unused class, not the network fetch.
4. **The `krayin-crm:*` command signatures.** `krayin-crm:version` and
   `krayin-crm:install` keep their names because provisioning and operator
   runbooks call them by name.

### Fifth candidate — flagged, not accepted

A core blade still renders vendor blue on a reachable surface:
`packages/Webkul/Admin/src/Resources/views/components/flash-group/item.blade.php`
hardcodes `#0E90D9` three times (lines 131, 133, 135) as the **info toast
severity** — a `border-left: 8px solid` plus its `icon:` and `stroke:` colours.
This is **not** among the four above and is **not promoted** into them: the
operator approved those four specifically. It is a generic alert severity, not a
brand mark, and the brand slot itself is correct — verified 2026-10-17,
`/admin/login` returns `200` with `--brand-color: #0B3C5D` and exactly three
`#0E90D9` literals, all of them this toast. Config cannot reach it: the severity
colours are a literal array in a core blade with no config key, so the fix is a
core edit or a copy of the component into `packages/Sijilna/Brand`, both
disproportionate under the never-modify-core rule.

**One-line decision for the operator:** accept the info-toast blue as a fifth
accepted residual, or authorise copying the flash-group component into the brand
package.

## Fixed, not accepted — the installer's outbound vendor call

`Webkul\Installer\Listeners\Installer@installed` POSTed the installing
administrator's name, email and site domain to
`https://updates.krayincrm.com/api/updates`. The operator chose to stop all
outbound vendor contact, so this is **fixed**, never one of the four above.

- **Mechanism.** `BrandingServiceProvider::boot()` (in `app/Providers/`) calls
  `Event::forget('krayin.installed')` in `disableVendorInstallTelemetry()`. The
  event has exactly one listener, registered at
  `packages/Webkul/Installer/src/Providers/InstallerServiceProvider.php:38`. That
  provider boots *before* the fork's, so the listener is bound by the time the
  fork forgets it.
- **The core listener class still exists but is never invoked.**
  `Webkul\Installer\Listeners\Installer` is untouched and unreferenced, so
  nothing calls `installed()`; deleting it or the `Event::listen()` line would be
  a core edit, which is why the one-line `forget` in fork-owned code is it.
- **Not merge debt.** The provider's `bootstrap/providers.php` registration is
  the exception already listed in the table above; this call adds no second core
  change. An upstream `2.2` merge that restores the listener is caught by
  `tests/Feature/VendorTelemetryTest.php`.

## Deliberately unchanged — two additions

PHP namespaces, `packages/Webkul/**` paths, the modules' own composer package
names, composer dependency names, the `vendor/` tree and `config/krayin-vite.php`
are covered above and not repeated. Two items belong on that list too:

- **Internal identifiers and route names** — translation key names such as
  `krayin-info`, the `images/krayin-logo.svg` asset filename and route names
  such as `image_cache`. Only the translated *values* reach a user.
- **`krayin/krayin-package-generator`** is a real Composer dependency (in
  `require-dev`), not a rebrand surface, so it stays.

## Legal basis

Upstream Krayin CRM is MIT licensed, so forking and rebranding are permitted —
see `LICENSE` and `THIRD-PARTY-NOTICES.md`. MIT grants **no trademark rights**,
so the upstream name must never be used in any way that implies this product is
official, endorsed or sponsored by Webkul Software.

## Related

- Never-modify-core rule → `AGENTS.md` (fork-specific project section)
- Attribution and dependency licences → `THIRD-PARTY-NOTICES.md`
- Branch model and upstream merges → [../operations/environment-and-deployment.md](../operations/environment-and-deployment.md)
