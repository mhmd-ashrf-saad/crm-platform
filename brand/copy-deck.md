# Sijilna Rebrand — Bilingual Copy Deck

Mechanical replacement copy for every **user-facing** string that carries a
Krayin/Webkul vendor mark. One English table, one Arabic table. Each row names
the file, the exact line, the key or attribute, the current text and the new
text. An implementation lane can apply this deck key by key without deciding
anything except the items listed under [Needs a human decision](#9-needs-a-human-decision).

- Identity (locked, not re-opened): **Sijilna** / **سجلنا**, `sijilna.com`.
- Attribution to Webkul lives in `LICENSE` and `THIRD-PARTY-NOTICES.md` only.
  Nothing on a user-facing surface may name Krayin, Webkul, `krayincrm.com`,
  `docs.krayincrm.com`, `devdocs.krayincrm.com`, `forums.krayincrm.com` or
  `updates.krayincrm.com`.
- Scope is `guides/rebrand-boundary.md`: user-facing surfaces only. PHP
  namespaces, `packages/Webkul/**` paths and composer dependency names stay.
- **The tagline is approved:** “Every deal, on the record.” / **كل صفقة لها
  سجل.** Its placement is the **login screen and the mail chrome only** —
  nothing in the admin's working screens, and not the footer credit (decisions
  1 and 11 in the record below).
- Palette rules apply to the two strings that carry markup: body text `#12263A`
  or `#0B3C5D` on `#FAF7F1` / `#FFFFFF`, secondary `#5A6472`, **ochre `#B07D2B`
  is never text**. The one inline colour in this deck is register blue.

## 0. Operator-approved decisions — 2026-10-17

**This section is the decisions record, and it supersedes any conflicting
statement anywhere else in this deck.** §2–§9 were written before the operator
settled these twelve items; where a decision below contradicts a row, a note or
a §9 entry, the decision wins and the original proposal is kept only as
rationale for how it was reached. A lane applying this deck reads this section
first.

All twelve are **approved**:

1. **Tagline approved.** “Every deal, on the record.” / **كل صفقة لها سجل.**
   This is a decision, not a proposal (`operations/brand.md`).
2. **Help & Resources — cards deleted, not reworded.** The support,
   documentation, extensions, API-docs, forum and video cards are **deleted**
   from the admin Help page **and** from the installer completion step. Not
   reworded, not re-pointed, and **no invented `sijilna.com/<path>` URL ships**
   anywhere. This supersedes the de-vendor-and-reword plan in §2.2, §2.3, §2.6,
   §3.2, §3.3 and §3.5, and the open questions they raised (§9.2, §9.3, §9.16).
   The approved root domain `sijilna.com` is the only sanctioned non-vendor
   destination.
3. **Vendor name: Sijilna.** The root composer package is `sijilna/crm` and the
   installer caption reads “a project by Sijilna” (§9.4, §9.9).
4. **Locales.** Each of the 23 vendor-branded keys **returns at its original
   position in all eight locales** (`es, fa, ja, ko, pt_BR, tr, vi, zh_CN`) with
   its own pre-rebrand translation, vendor token replaced by Sijilna (§1, §7).
5. **The live instance is rebranded for real.** `.env` values are changed
   **and** a `general.settings.footer.label` `core_config` row is seeded, so the
   credit is visible and editable under **Settings**.
6. **Core residuals accepted — all five.** The installer's cloud-hosting advert
   and ASCII banner, the `storage/installed` literals, the presence of the
   now-unreachable core `ImageCacheController` class, the `krayin-crm:*` command
   signatures, and the core info toast's `#0E90D9` severity tint (accepted
   2026-10-17, §9.17) are **accepted**: not rewritten, not deleted, and not to be
   re-filed as bugs (§2.7, §4, §5, §9.10, §9.13). The *route* is no longer a
   residual — it is replaced (decision 7); only the unreachable class remains.
7. **The outbound vendor calls are stopped — not by a Blade override.** Two
   provider registrations do it: `disableVendorInstallTelemetry()` forgets the
   `krayin.installed` listener, so the installer never POSTs to
   `updates.krayincrm.com`, and `replaceVendorLogoRoute()` re-registers the
   `cache/{filename}` URI last under the same `image_cache` name, so
   `RouteCollection` indexes the fork's handler by method + URI and the core
   `ImageCacheController` is never reached. The admin layout's preload still
   fires, but as a same-origin request answered locally by the Sijilna mark, so
   no admin page contacts `updates.krayincrm.com` (§5, §9.13).
8. **Arabic is wired as written, then reviewed on the rendered screens.** The
   operator reviews the rendered admin; there is no pre-wiring Arabic review.
9. **README: full rewrite.** Already delivered by another lane (§2.9).
10. **`tests/Feature/HelpTest.php` is replaced**, not updated, by a regression
    test that fails if a vendor mark reappears on a user-facing surface. It is
    **not** changed to assert a new Help label, because the Help cards are
    deleted. This supersedes §8.2.
11. **Tagline placement: the login screen and the mail chrome only.** Nothing in
    the admin's working screens. §9.1's suggestion that the footer credit is the
    tagline's home is wrong.
12. **Landing: one commit**, with bead closures reported for approval.

## 1. Locale policy — decided

**`en` and `ar` are the maintained locales. The other eight keep their own
pre-rebrand strings, re-branded.**

| Locale | Action | Why |
| --- | --- | --- |
| `en` | Rewritten (English table) | Source of truth |
| `ar` | Rewritten (Arabic table) | Primary market |
| `es, fa, ja, ko, pt_BR, tr, vi, zh_CN` | **23 keys restored per locale**, each carrying its own pre-rebrand translation with the vendor token replaced by Sijilna | Passes both spec tests; no reviewer for these languages exists, so no machine translation is risked |

Reasoning — the mechanism was settled by measurement, not preference:

1. Deleting the keys in `132393d5` failed upstream's key-parity test; rewriting
   each value to the English Sijilna string passed parity but pushed all eight
   locales past the second test's recorded `BACKLOG`.
2. Restoring the pre-rebrand translation with the vendor token replaced passes
   **both** tests with `lang.spec.ts` unedited and returns each locale to its
   recorded `BACKLOG` (measured 2026-10-17: `Admin/ar` 1, `Admin/pt_BR` 46,
   `Admin/tr` 23, `Admin/vi` 41, every other combo 0).
3. Nothing is retranslated and no value is copied from `en`; the honest cost is
   **176 locale-key pairs** — the 22 keys whose English wording changed (8
   Admin, 14 Installer) × 8 locales — keeping older re-branded phrasing.
4. Two hand-fixes, because a doubled brand is barred: each locale's
   `components.layouts.powered-by.description` drops the second `:webkul`
   mention, and `installer.index.webkul` is **empty in all ten locales** (as
   `en`/`ar` already were), kept for key parity: the caption is config-driven and
   the override replaces its whole paragraph, deleting both vendor anchors rather
   than re-pointing them, so neither renders (§2.6, §9.4); `ko` and `tr` also drop the vendor clause in `installer.index.krayin-info`.

The 23 keys, restored at their original positions — Admin,
`packages/Webkul/Admin/src/Resources/lang/<loc>/app.php`:

```text
components.layouts.powered-by.description
help.index.description
help.index.still-need-help-description
help.index.services.cloud-hosting.title
help.index.services.cloud-hosting.description
help.index.services.extensions.description
help.index.resources.api-docs.description
help.index.community.forums.description
```

Installer, `packages/Webkul/Installer/src/Resources/lang/<loc>/app.php`:

```text
installer.index.create-administrator.krayin
installer.index.environment-configuration.krayin
installer.index.installation-processing.krayin
installer.index.installation-completed.krayin-forums
installer.index.installation-completed.explore-krayin-extensions
installer.index.installation-completed.title-info
installer.index.ready-for-installation.install-info
installer.index.start.title
installer.index.start.welcome-title
installer.index.krayin
installer.index.krayin-info
installer.index.krayin-logo
installer.index.installation-description
installer.index.title
installer.index.webkul
```

**Console output stays English.** The installer's CLI banner and the composer
on-boarding template are ASCII box art whose padding assumes LTR text; they are
not localised and the Arabic table does not cover them.

### Known, accepted divergences — dead keys and unedited config files

Three places where the shipped tree diverges from the wording in §2 and §3, all
deliberate. None is a vendor leak: the guard scans **values**, and nothing
vendor-marked here ever renders.

- **Dead keys retained, no vendor text.** `en` and `ar` still carry values for
  keys whose cards the Help page no longer renders —
  `help.index.services.extensions.description`,
  `help.index.resources.api-docs.description`,
  `help.index.community.forums.description` — and the installer still carries
  `installer.index.installation-completed.krayin-forums` /
  `.explore-krayin-extensions`; `installer.index.webkul` is an **empty string**
  rather than a removed key. The key *names* keep `krayin`, which is explicitly
  allowed (internal identifiers, `rebrand-boundary.md`). Do **not** delete these
  keys: they are unreachable and the tree is not to be churned further. This is
  a divergence from the "deletion (decision 2)" rows in §2.2/§2.5/§3.2/§3.5, not
  a missed deletion.
- **`packages/Webkul/Admin/src/Config/core_config.php` is deliberately unedited
  too.** Its line 73 still holds the vendor footer HTML, and `git status` shows
  the file clean. The rendered footer comes from the provider's
  `settings.footer.label` override (`overrideFooterCredit()` →
  `Brand::FOOTER_CREDIT`), which `SystemConfig::getDefaultConfig()` consults with
  `Config::get($field, …)` **before** the field's declared default
  (`packages/Webkul/Core/src/SystemConfig.php:160–170`). Verified:
  `core()->getConfigData('general.settings.footer.label')` returns the Sijilna
  mark-up. So §2.4 records the provider key as the rendered value and the file
  as unedited, and §7 counts that one string but no file for it.
- **`packages/Webkul/Admin/src/Config/support.php` is deliberately unedited.**
  It still holds all 15 vendor URLs; the provider neutralises it at **runtime**
  with `config(['support' => …])` in `overrideHelpSupportConfig()`, which is the
  whole link surface the Help page reads. §2.3's disposition table therefore
  describes what *renders*, not a core-file edit.

## 2. English table

`admin` = `packages/Webkul/Admin`, `installer` = `packages/Webkul/Installer`.

### 2.1 Admin — login / forgot / reset footer

| File | Line | Key / location | Current | New |
| --- | --- | --- | --- | --- |
| `admin/src/Resources/lang/en/app.php` | 340 | `components.layouts.powered-by.description` | `Powered by :krayin, an open-source project by :webkul.` | `Powered by Sijilna.` — **applied** (no placeholder; see the note below) |
| `admin/.../views/sessions/login.blade.php` | 107–110 | `@lang(... 'powered-by.description', [...])` | `'krayin' => '<a class="text-brandColor hover:underline " href="https://krayincrm.com/">Krayin</a>'` and `'webkul' => '<a class="text-brandColor hover:underline " href="https://webkul.com/">Webkul</a>'` | `'krayin' => '<a class="text-brandColor hover:underline " href="https://sijilna.com/">Sijilna</a>'` — **delete the `webkul` array entry** |
| `admin/.../views/sessions/forgot-password.blade.php` | 80–83 | same | same | same |
| `admin/.../views/sessions/reset-password.blade.php` | 125–128 | same | same | same |

Placeholder discipline — **superseded by the applied tree**. This paragraph used
to say `:krayin` would stay in the string and stay bound to the `krayin` key.
It did not. The shipped brand override renders the footer with
`@lang('admin::app.components.layouts.powered-by.description')` and **no
arguments**
(`packages/Sijilna/Brand/resources/views/admin/sessions/login.blade.php:112`),
so the string carries no placeholder and the rendered footer reads **Powered by
Sijilna.** / **مدعوم من Sijilna.** — the values the `en` and `ar` locale files
now hold. The core blades' `krayin` and `webkul` array entries are never
reached, because the brand package ships replacement blades for all three
session views. A lane applying this deck must not reintroduce `:krayin`.

### 2.2 Admin — Help & Resources page

> **Superseded in part by decision 2.** The support, documentation, extensions,
> API-docs, forum and video cards are **deleted**, so their keys are **key
> deletions, not rewrites**. That covers the rows below for
> `services.extensions.description`, `resources.api-docs.description` and
> `community.forums.description`, **and** every key in the “deliberately not
> reworded” list that only labels one of those cards —
> `services.support.title` / `.description`, `resources.user-docs.*`,
> `resources.dev-docs.*`, `community.tutorials.*`, `community.forums.link` —
> together with `help.index.view-all` / `help.index.contact-us` if their
> sections go with the cards. The `cloud-hosting` card is not named by decision
> 2: it survives retitled `Self-hosted Deployment` and keeps the rewrite below.
> The page lead (`help.index.description`) and `still-need-help-description`
> are still rewritten, but the proposed values below were written for the card
> page and must not promise cards that no longer exist.

| File | Line | Key | Current | New |
| --- | --- | --- | --- | --- |
| `admin/src/Resources/lang/en/app.php` | 2314 | `help.index.description` | `Everything you need to get the most out of Krayin Admin — hosting, support and professional services, plus extensions and developer documentation.` | `Everything you need to run Sijilna well — support, maintenance and documentation, plus a place to ask questions.` |
| " | 2318 | `help.index.still-need-help-description` | `Talk to the Krayin team about hosting, custom development or anything else.` | `Contact Sijilna support about your installation, customisation or anything else.` |
| " | 2323 | `help.index.services.cloud-hosting.title` | `Cloud Hosting` | `Self-hosted Deployment` |
| " | 2324 | `help.index.services.cloud-hosting.description` | `Cost-effective, managed cloud hosting — try and launch Krayin on the cloud in minutes, fully optimised and scalable.` | `Sijilna runs on your own server. This covers installing it, updating it and backing it up.` |
| " | 2332 | `help.index.services.extensions.description` | `Browse official and community add-ons to extend Krayin with new connectors, channels and features.` | **Deletion (decision 2)** — the extensions card does not render, so this is not a replacement |
| " | 2348 | `help.index.resources.api-docs.description` | `Full REST API reference with endpoints, authentication and examples to integrate Krayin with your stack.` | **Deletion (decision 2)** — the API-docs card does not render, so this is not a replacement |
| " | 2355 | `help.index.community.forums.description` | `Connect with thousands of other Krayin users and developers to share tips and solve problems.` | **Deletion (decision 2)** — the forum card does not render, so this is not a replacement |

Changed meaning, not just branding: `cloud-hosting.title` and
`cloud-hosting.description` promised **managed cloud hosting**, which a
self-hosted first-party product does not sell. `community.forums.description`
claimed thousands of users; Sijilna has none yet.

Deliberately **not** reworded (no vendor mark — hard rule 2):
`help.index.services.support.title` / `.description` (2326–2329),
`help.index.resources.user-docs.*` (2338–2341),
`help.index.resources.dev-docs.*` (2342–2345),
`help.index.community.tutorials.*` (2358–2363),
`help.index.services.title`, `help.index.resources.title`,
`help.index.contact-us`, `help.index.view-all`, `help.index.community.forums.link`.

### 2.3 Admin — Help & Resources links (`Config/support.php`) — cards deleted

`url_label` is rendered on the card (`admin/.../views/help/card.blade.php:27`),
so both columns are user-visible.

**This section is a deletion list, not a URL map (decision 2).** The support,
documentation, extensions, API-docs, forum and video cards are removed; nothing
on this page is re-pointed, and no `sijilna.com/<path>` is invented. The vendor
values leave the tree with the entries that carry them.

| Line | Key | Disposition |
| --- | --- | --- |
| 9–10 | `services[0].url`, `.url_label` | **not named by decision 2** — the `cloud-hosting` card survives retitled `Self-hosted Deployment`; its vendor URL must go and no path may be invented, so both values fall back to the approved root domain: `https://sijilna.com/` and `sijilna.com` |
| 16–17 | `services[1].url`, `.url_label` | **delete the entry** (support card — decision 2) |
| 23–24 | `services[2].url`, `.url_label` | **delete the entry** (extensions card — decision 2) |
| 33–34 | `resources[0].url`, `.url_label` | **delete the entry** (user documentation card — decision 2) |
| 40–41 | `resources[1].url`, `.url_label` | **delete the entry** (developer documentation card — decision 2) |
| 47–48 | `resources[2].url`, `.url_label` | **delete the entry** (API-docs card — decision 2) |
| 57 | `community[0].url` | **delete the entry** (forum card — decision 2) |
| 64 | `community[1].url` | **delete the entry** (video card — decision 2) |
| 69 | `contact_url` | **not named by decision 2** — the vendor URL must go and no path may be invented: `https://sijilna.com/` (approved root domain) until a real contact page exists |

A single `sijilna.com` root-domain fallback on the two surviving destinations is
the whole of the non-vendor link surface that remains on this page; §9.2 and
§9.3 are closed by decision 2, so **no invented `sijilna.com` path appears
anywhere in this deck**.

### 2.4 Admin — footer credit (`Config/core_config.php` is deliberately unedited)

| Line | Key | Current | Disposition |
| --- | --- | --- | --- |
| 73 | `general.settings.footer` → `fields[1].default` | `Powered by <span style="color: rgb(14, 144, 217);"><a href="http://www.krayincrm.com" target="_blank">Krayin</a></span>, an open-source project by <span style="color: rgb(14, 144, 217);"><a href="https://webkul.com" target="_blank">Webkul</a></span>.` | **Not an edit.** The file stays clean (`git status`); the value below renders from the provider, and this line is the field's dead runtime default. |

The approved replacement value — applied by `Brand::FOOTER_CREDIT`, not by this
line — is
`Powered by <span style="color: rgb(11, 60, 93);"><a href="https://sijilna.com" target="_blank">Sijilna</a></span>.`
`rgb(11, 60, 93)` is register blue `#0B3C5D` (10.80:1 on `#FAF7F1`, 11.55:1 on
`#FFFFFF` — AA and AAA). It replaces the vendor blue `rgb(14, 144, 217)` not in
the palette. The field type is a TinyMCE `editor` and the value passes through
`core()->sanitizeHtml()`, so the inline `style` is the supported surface.

**Rendered value — the provider key wins over this default.** The footer reads
`core()->getConfigData('general.settings.footer.label')`
(`admin/.../views/components/layouts/index.blade.php:154`). With no `core_config`
row, `SystemConfig::getConfigData()`
(`packages/Webkul/Core/src/SystemConfig.php:176`) falls back to
`getDefaultConfig()`, which strips the leading `general.` and returns
`Config::get('settings.footer.label', <the default above>)` (`:160–170`,
`:182–184`). `BrandingServiceProvider::overrideFooterCredit()` sets that key to
`Brand::FOOTER_CREDIT`, so the provider's value renders and **line 73 is never
consulted**. A seeded `general.settings.footer.label` row outranks the provider
key too (decision 5), for visibility under **Settings** and for per-client
white-labelling. See §9.6.

### 2.5 Installer — wizard (`installer/src/Resources/lang/en/app.php`)

| Line | Key | Current | New |
| --- | --- | --- | --- |
| 133 | `installer.index.create-administrator.krayin` | `Krayin` | `Admin` — see §9.5 |
| 148 | `installer.index.environment-configuration.krayin` | `Krayin` | `Sijilna` |
| 232 | `installer.index.installation-processing.krayin` | `Installation Krayin` | `Installing Sijilna` |
| 239 | `installer.index.installation-completed.krayin-forums` | `Krayin Forum` | **Deletion (decision 2)** — the forum card does not render |
| 241 | `installer.index.installation-completed.explore-krayin-extensions` | `Explore Krayin Extension` | **Deletion (decision 2)** — the extensions card does not render |
| 243 | `installer.index.installation-completed.title-info` | `Krayin is Successfully installed on your system.` | `Sijilna has been installed successfully.` |
| 249 | `installer.index.ready-for-installation.install-info` | `Krayin For Installation` | `Sijilna is ready to install` |
| 260 | `installer.index.start.title` | `Your Krayin install` | `Your Sijilna install` |
| 261 | `installer.index.start.welcome-title` | `Welcome to Krayin` | `Welcome to Sijilna` |
| 288 | `installer.index.krayin` | `Krayin` | `Sijilna` |
| 289 | `installer.index.krayin-info` | `a Community Project by` | `a self-hosted CRM` — see §9.4 |
| 290 | `installer.index.krayin-logo` | `Krayin Logo` | `Sijilna logo` |
| 292 | `installer.index.installation-description` | `Krayin installation typically involves several steps. Here's a general outline of the installation process for Krayin` | `Installing Sijilna takes a few steps. Here is what this wizard does.` |
| 296 | `installer.index.title` | `Krayin Installer` | `Sijilna Installer` |
| 297 | `installer.index.webkul` | `Webkul` | **Deletion (§9.4)** — the vendor anchor at line 267–273 goes with it |

**Not** reworded: `installer.index.installation-processing.krayin-info`
(line 233, `Creating the database tables, this can take a few moments`) carries
no vendor mark. Flagged in §9.8 because its heading is rewritten.

> **Decision 2 note:** `installer.index.installation-completed.krayin-forums`
> (239) and `.explore-krayin-extensions` (241) label the two completion-step
> cards deleted in §2.6, so they are **key deletions, not the rewordings tabled
> above**. The Arabic rows in §3.5 are the same.

### 2.6 Installer — view (`installer/src/Resources/views/installer/index.blade.php`)

| Line | Location | Current | New |
| --- | --- | --- | --- |
| 132 | `src` of the wizard logo | `vite()->asset('images/krayin-logo.svg', 'installer')` | asset swap → Sijilna mark; source asset is `installer/src/Resources/assets/images/krayin-logo.svg` (see §5) |
| 259 | first vendor anchor `href` (`krayin` key → `https://krayincrm.com/`) | `https://krayincrm.com/` | **deleted, not re-pointed** — both vendor anchors go with the whole caption paragraph the brand override replaces (core 253–272) with config-driven copy (`installer/installer/index.blade.php:263`); line 259 never renders — see §9.4 |
| 267–273 | second vendor anchor (`webkul` key → `https://webkul.com/`) | renders `… a Community Project by Webkul` | **superseded** — the brand override replaces the whole caption paragraph (override line 263), which renders `config('sijilna.installer.caption_en')` = `a project by Sijilna`; no inline lang element survives — see §9.4 |
| 735 | default value of the Application Name field | `::value="envData.app_name ?? 'Krayin'"` | `::value="envData.app_name ?? 'Sijilna'"` |
| 1027 | completion-step forum card `href` | `https://forums.krayincrm.com/` | **delete the anchor block** — a forum card, deleted by decision 2; no invented URL |
| 1034 | completion-step extensions card `href` | `https://krayincrm.com/extensions/` | **delete the anchor block** — an extensions card, deleted by decision 2; no invented URL |
| 1103 | hardcoded JS validation error | `"Krayin currently supports MySQL and MariaDB only."` | `"Sijilna currently supports MySQL and MariaDB only."` |

Line 1103 is the only user-facing **hardcoded English** string found in the
rebrand scope; it has no lang key, so it cannot follow the locale policy. It is
flagged in §9.11.

### 2.7 Console installer banner (English only) — accepted residuals, not pending

| File | Line | Current | Disposition (decision 6) |
| --- | --- | --- | --- |
| `installer/src/Events/ComposerEvents.php` | 35 | `['★ KRAYIN CLOUD HOSTING', …]` | **Accepted residual** — not rewritten |
| " | 37 | `['Skip the server setup — run Krayin on fast, secure,', …]` | **Accepted residual** — not rewritten |
| " | 38 | `['cost-effective managed hosting that scales on demand.', …]` | **Accepted residual** — not rewritten |
| " | 39 | `['Launch in minutes, without the infrastructure overhead.', …]` | **Accepted residual** — not rewritten (the line stays) |
| " | 41 | `['→ https://krayincrm.com/crm-cloud-hosting/', …]` | **Accepted residual** — not rewritten; no URL is invented |
| `installer/src/Templates/on-boarding.php` | 13 | `Welcome to the <info>Krayin</info> project! Krayin Community is an <comment>open-source CRM solution</comment>` | **Accepted residual** — not rewritten |
| " | 16 | `Made with 💖  by the Krayin Team. Happy helping :)` | **Accepted residual** — not rewritten |
| `installer/src/Console/Commands/Installer.php` | 142 | `Step: Seeding basic data for Krayin kickstart...` | `Step: Seeding basic data for Sijilna kickstart...` |
| " | 214 | `env('APP_NAME', 'Krayin CRM')` | `env('APP_NAME', 'Sijilna')` |
| " | 391 | `The installation has been finished and you can now use Krayin.` | `The installation has been finished and you can now use Sijilna.` |

> **Superseded by decision 6.** The cloud-hosting advert and the ASCII banner are
> **accepted core residuals**: the panel is not rewritten, not deleted, and not
> re-filed. The `ComposerEvents.php` rows and the on-boarding ASCII template
> above are withdrawn as proposals and kept only as rationale;
> `rebrand-boundary.md` lists them as residual 1 of exactly five. §9.10 is closed
> by decision 6. The plain console lines in `Installer.php` (142, 214, 391) are
> not banner art and are not covered by decision 6.

### 2.8 Other in-scope files

| File | Line | Key / location | Current | New |
| --- | --- | --- | --- | --- |
| `packages/Webkul/Automation/src/Helpers/Entity/Activity.php` | 318 | iCal `PRODID` of activity invitations | `PRODID:-//Krayincrm//Krayincrm//EN` | `PRODID:-//Sijilna//Sijilna//EN` |
| `.env.example` | 1 | `APP_NAME` | `APP_NAME='Krayin CRM'` | `APP_NAME='Sijilna'` |
| `.env.example` | 43 | `MAIL_FROM_ADDRESS` | `MAIL_FROM_ADDRESS=laravel@krayincrm.com` | `MAIL_FROM_ADDRESS=support@sijilna.com` |

`APP_NAME` is what the transactional mail chrome prints: the header alt text at
`admin/.../views/emails/layout.blade.php:20` is `alt="{{ config('app.name') }}"`
and the signature at line 31 is
`@lang('admin::app.emails.common.cheers', ['app_name' => config('app.name')])`
→ `Cheers,</br>Team Sijilna`. **No lang edit is needed in `emails/`**: the two
strings carry no vendor mark. The mail header logo `images/logo.svg` (line 19) is
an asset swap, listed in §5.

The live `.env` is gitignored and is not in this deck's write set: the wiring
lane must set `APP_NAME='Sijilna'` and a Sijilna `MAIL_FROM_ADDRESS` there too,
or the mail chrome and the installer default keep the old name on this checkout.

### 2.9 Repo identity — listed, not per-key

`rebrand-boundary.md` puts README, `composer.json` package name, GitHub
description/topics and `.env.example` comments in scope. Only the two
`.env.example` values above are a mechanical string swap. The rest is a rewrite,
not a copy edit:

| File | Content | Recommendation |
| --- | --- | --- |
| `README.md` | 8 `krayincrm.com` links, vendor badges, cloud-hosting and extension marketing | separate documentation lane; do not patch line by line |
| `composer.json:2` | `"name": "krayin/laravel-crm"` | renamed to **`sijilna/crm`** (decision 3). The lock's `content-hash` is stale from that rename and still to be refreshed — §9.9 |
| `Makefile:71` | `Krayin CRM dev commands` | **untouched** — developer tooling, not a client surface (§4) |

## 3. Arabic table

Arabic reuses the terminology already in
`packages/Webkul/Admin/src/Resources/lang/ar/app.php`: عميل محتمل (lead), جهات
الاتصال (contacts), العرض (quote), الأنشطة (activity), المستودعات (warehouse),
الأدوار (roles), لوحة القيادة (dashboard), الإعدادات (settings). No new parallel
terms are introduced.

### 3.1 Login / forgot / reset footer

| File | Line | Key / location | Current | New |
| --- | --- | --- | --- | --- |
| `admin/src/Resources/lang/ar/app.php` | 340 | `components.layouts.powered-by.description` | `مدعوم من :krayin، وهو مشروع مفتوح المصدر من :webkul.` | `مدعوم من Sijilna.` — **applied**; the `من :krayin` shape below was not shipped, because the brand view renders the key with no arguments |
| `admin/.../views/sessions/login.blade.php` | 107–110 | `'krayin'` / `'webkul'` | `…krayincrm.com…>Krayin</a>` + `…webkul.com…>Webkul</a>` | `'krayin' => '<a class="text-brandColor hover:underline " href="https://sijilna.com/">سجلنا</a>'`, delete `'webkul'` |
| `admin/.../views/sessions/forgot-password.blade.php` | 80–83 | same | same | same |
| `admin/.../views/sessions/reset-password.blade.php` | 125–128 | same | same | same |

**Why `من :krayin` was the plan and `مدعوم من Sijilna.` is what shipped:** «مدعوم من» is a passive
"supported by", which in Arabic reads as a service relationship and was already
the awkward part of the old string. A footer byline in Arabic products is «من
`X`». It is two words, native, and needs no relative clause — but it needs a
`:krayin` placeholder, and the applied brand view renders the key with no
arguments, so the shipped value is the placeholder-free `مدعوم من Sijilna.`.
The `من :krayin` fallback is kept here only as rationale; §9.7 remains open on
the wording of the *seeded* `core_config` row.
Renders as **مدعوم من Sijilna.** under the login form — the applied value. The
`من سجلنا` shape this line used to show was the plan, and it did not ship.

### 3.2 Help & Resources page

> **Superseded in part by decision 2**, exactly as in §2.2: the Arabic card keys
> are **deletions, not rewrites** (`services.support.*`,
> `services.extensions.description`, `resources.user-docs.*`,
> `resources.dev-docs.*`, `resources.api-docs.description`, `community.*`). The
> `cloud-hosting` card keeps the rewrite below; the page lead and
> `still-need-help-description` must not promise the deleted cards.

| File | Line | Key | Current | New |
| --- | --- | --- | --- | --- |
| `admin/src/Resources/lang/ar/app.php` | 2307 | `help.index.description` | `كل ما تحتاجه لتحقيق أقصى استفادة من لوحة تحكم Krayin — الاستضافة والدعم والخدمات الاحترافية، بالإضافة إلى الإضافات ووثائق المطوّرين.` | `كل ما تحتاجه لتشغيل سجلنا بسلاسة — الدعم والصيانة والوثائق، ومكان تسأل فيه أسئلتك.` |
| " | 2311 | `help.index.still-need-help-description` | `تحدّث مع فريق Krayin حول الاستضافة أو التطوير المخصص أو أي شيء آخر.` | `تواصل مع دعم سجلنا بشأن التثبيت أو التخصيص أو أي شيء آخر.` |
| " | 2316 | `help.index.services.cloud-hosting.title` | `الاستضافة السحابية` | `التشغيل على خادمك` |
| " | 2317 | `help.index.services.cloud-hosting.description` | `استضافة سحابية مُدارة وفعّالة من حيث التكلفة — جرّب وأطلق Krayin على السحابة في دقائق، مُحسّنة بالكامل وقابلة للتوسّع.` | `يعمل سجلنا على خادمك الخاص. يغطي هذا القسم تثبيته وتحديثه والنسخ الاحتياطي.` |
| " | 2325 | `help.index.services.extensions.description` | `تصفّح الإضافات الرسمية وإضافات المجتمع لتوسيع Krayin بموصّلات وقنوات وميزات جديدة.` | **حذف (القرار 2)** — بطاقة الإضافات لا تُعرض، فليس هذا استبدالًا |
| " | 2341 | `help.index.resources.api-docs.description` | `مرجع كامل لواجهة REST API مع نقاط النهاية والمصادقة وأمثلة لدمج Krayin مع نظامك.` | **حذف (القرار 2)** — بطاقة وثائق API لا تُعرض، فليس هذا استبدالًا |
| " | 2348 | `help.index.community.forums.description` | `تواصل مع آلاف المستخدمين والمطوّرين الآخرين في Krayin لتبادل النصائح وحل المشكلات.` | **حذف (القرار 2)** — بطاقة المنتدى لا تُعرض، فليس هذا استبدالًا |

Arabic-specific judgements:

- `cloud-hosting` becomes «التشغيل على خادمك» ("running on your own server"),
  not a translation of "cloud hosting" — Sijilna is self-hosted, so the offer
  itself changes. The description says the section covers install, update and
  backup, which is what a self-hosted operator actually needs.
- `still-need-help-description`: «تواصل مع دعم سجلنا» ("contact Sijilna
  support") replaces «تحدّث مع فريق Krayin» — «دعم» is what an Arabic admin
  looks for; «فريق» alone would imply an in-product chat channel we do not have.
- `forums.description`: the thousands-of-users claim is dropped rather than
  translated, since it would be false. «مستخدمي سجلنا» keeps the plural form but
  makes no claim about size.
- `api-docs.description`: «لربط سجلنا بأنظمتك» replaces the literal
  «لدمج … مع نظامك»; «ربط الأنظمة» is the phrase Egyptian technical readers use
  for integration, `دمج` reads as merging records.
- `installation-description`, `title-info` and the welcome strings keep the same
  register as the surrounding installer file (Arabic installer already uses
  «التثبيت», «متطلبات الخادم», «جاهز للتثبيت»).

Deliberately **not** reworded, matching the English side:
`help.index.services.support.title` / `.description` (2319–2322),
`help.index.resources.user-docs.*` (2331–2334),
`help.index.resources.dev-docs.*` (2335–2338),
`help.index.community.tutorials.*` (2351–2356),
`help.index.title` (2306), `help.index.contact-us`, `help.index.view-all`,
`help.index.community.forums.link`.

### 3.3 Help & Resources links (`Config/support.php`)

Same disposition as §2.3 — the file is locale-independent, so the Arabic UI
shows the same URLs and labels: the support, documentation, extensions,
API-docs, forum and video cards and their labels are deleted (decision 2), and
the two destinations the decision does not name fall back to the approved root
domain `https://sijilna.com/`. No separate Arabic values.

### 3.4 Footer credit

Rendered through TinyMCE for **whichever locale the admin is in**, and the value
is a single HTML string, so it cannot hold two languages. It comes from the
provider's `settings.footer.label` (`Brand::FOOTER_CREDIT`), **not** from
`Config/core_config.php:73`, which stays unedited (§2.4). Latin `Sijilna` text in
an Arabic admin footer is acceptable here (the brand name is Latin in the
wordmark); the alternative is
`<span style="color: rgb(11, 60, 93);"><a href="https://sijilna.com" target="_blank">سجلنا</a></span>`.

Recommended value, single string, both locales:

```html
<span style="color: rgb(11, 60, 93);"><a href="https://sijilna.com" target="_blank">سجلنا</a></span> — <span style="color: rgb(11, 60, 93);">Sijilna</span>
```

This is flagged in §9.7 as the one string that genuinely has to pick a language.

### 3.5 Installer — wizard (`installer/src/Resources/lang/ar/app.php`)

| Line | Key | Current | New |
| --- | --- | --- | --- |
| 133 | `installer.index.create-administrator.krayin` | `Krayin` | `مسؤول` — see §9.5 |
| 148 | `installer.index.environment-configuration.krayin` | `Krayin` | `سجلنا` |
| 232 | `installer.index.installation-processing.krayin` | `تثبيت Krayin` | `جارٍ تثبيت سجلنا` |
| 239 | `installer.index.installation-completed.krayin-forums` | `منتديات Krayin` | **حذف (القرار 2)** — بطاقة المنتدى لا تُعرض |
| 241 | `installer.index.installation-completed.explore-krayin-extensions` | `استكشاف امتدادات Krayin` | **حذف (القرار 2)** — بطاقة الإضافات لا تُعرض |
| 243 | `installer.index.installation-completed.title-info` | `تم تثبيت Krayin بنجاح على نظامك.` | `تم تثبيت سجلنا بنجاح.` |
| 249 | `installer.index.ready-for-installation.install-info` | `Krayin للتثبيت` | `سجلنا جاهز للتثبيت` |
| 260 | `installer.index.start.title` | `تثبيت Krayin الخاص بك` | `تثبيت سجلنا` |
| 261 | `installer.index.start.welcome-title` | `مرحبًا بك في Krayin` | `مرحبًا بك في سجلنا` |
| 288 | `installer.index.krayin` | `Krayin` | `سجلنا` |
| 289 | `installer.index.krayin-info` | `مشروع مجتمعي من قبل` | `نظام إدارة عملاء تستضيفه بنفسك` — see §9.4 |
| 290 | `installer.index.krayin-logo` | `شعار Krayin` | `شعار سجلنا` |
| 292 | `installer.index.installation-description` | `عادة ما تتضمن عملية تثبيت Krayin عدة خطوات. إليك نظرة عامة عامة على عملية التثبيت krayin` | `يتم تثبيت سجلنا على عدة خطوات. إليك ما يفعله هذا المعالج.` |
| 296 | `installer.index.title` | `مثبت Krayin` | `مثبّت سجلنا` |
| 297 | `installer.index.webkul` | `Webkul` | **حذف (§9.4)** — يُحذف مع رابط البائع في السطر 267–273 |

Notes:

- `title-info` is the **first sentence a new customer reads**: «تم تثبيت سجلنا
  بنجاح.» drops «على نظامك» ("on your system"), which the congratulation does not
  need, and the bare perfect-tense verb is the natural Egyptian Arabic form.
- `installation-description` in the source has a stray untranslated `krayin` and
  a doubled «عامة عامة»; both are gone in the replacement.
- `install-info` was `Krayin للتثبيت`, a fragment that only made sense with the
  following button label; «سجلنا جاهز للتثبيت» is a complete heading.
- Standardised on «إضافات» for add-ons (the Admin locale already uses it) rather
  than the installer's «امتدادات» — flagged in §9.15.

## 4. Strings that mention the vendor but that I judge stay untouched

| File / location | Reason |
| --- | --- |
| `LICENSE`, `THIRD-PARTY-NOTICES.md` | MIT attribution is a **legal requirement**. This is where the vendor mark belongs; the footer is not. |
| Every PHP namespace, `use Webkul\…`, `packages/Webkul/**` path (~560 files) | Out of scope by `rebrand-boundary.md`; renaming collides on every `2.2` merge. |
| `composer.json` dependency names, `composer.lock` | Same as above; package names only. |
| `config/krayin-vite.php` (file name, `Krayin Vite Configuration` comment) | Explicitly out of scope; rename has no user-visible effect. |
| `packages/Webkul/Admin/src/Resources/views/components/tinymce/index.blade.php:7` | Code comment (`TODO (@devansh-webkul)`), never rendered. |
| `packages/Webkul/Admin/.../header/notifications.blade.php:123,191` | Code comments explaining CSS; never rendered. |
| `packages/Webkul/Admin/src/Bouncer.php`, `DataGrids/**`, `Http/Requests/**`, `Routes/**`, permission keys (`Webkul\Admin\Http\Controllers…`) | Internal identifiers. Renaming a permission key or route name breaks stored roles and browser bookmarks for no visible gain. |
| `packages/Webkul/Core/src/Console/Commands/Version.php:14` | Command signature `krayin-crm:version` (line 14) and its `$description` (line 21) — a console identifier a client's cron or runbook may already call. Console surfaces are English-only and out of the copy scope. |
| `storage/installed`, `storage/installing` marker bodies (`Installer.php:379`, `InstallerController.php:167`, `DatabaseManager.php:82`, `scripts/create-admin.php:137`) | Marker file **contents** are never rendered; the installer tests for the file's existence only. |
| `.github/ISSUE_TEMPLATE/**`, `.github/FUNDING.yml`, `.github/workflows/**`, `CODE_OF_CONDUCT.md`, `UPGRADE.md` | Upstream-only surfaces, explicitly out of scope. |
| `Makefile:71` (`Krayin CRM dev commands`) | Developer tooling output, not a client-facing surface. |
| `tests/**` (e.g. `tests/Feature/HelpTest.php:25` asserts `krayincrm.com/cloud-hosting`, `HtmlSanitizationTest.php:38` uses the vendor HTML as a fixture) | Tests are not copy, and under decision 10 they are not “updated to match §2.3” either: `HelpTest` is **replaced** by a vendor-mark regression test, and the `HtmlSanitizationTest` fixture is swapped per §8.3. |

## 5. Vendor references that are not copy — separate decision

| File / line | What it is | Note |
| --- | --- | --- |
| `installer/src/Listeners/Installer.php:15` | `API_ENDPOINT = 'https://updates.krayincrm.com/api/updates'` — the installer phones home to the vendor | Not a string a user reads. **Settled (decision 7):** `disableVendorInstallTelemetry()` forgets the `krayin.installed` listener, so the POST never fires; the endpoint itself stays. §9.13 is closed. |
| `installer/src/Http/Controllers/ImageCacheController.php:23` | `const KRAYIN_LOGO = 'https://updates.krayincrm.com/krayin.png'`, cached under the key `krayin-logo` and served on route `image_cache` (`InstallerServiceProvider.php:46`) | Fetches the **vendor logo over the network at runtime**. The route is **replaced, not residual** (decision 7): `replaceVendorLogoRoute()` registers the same `cache/{filename}` URI and the same `image_cache` name last, so `RouteCollection` indexes the fork's handler and this class is never reached. **Accepted core residual (decision 6):** the unreachable class stays and is not to be re-filed as a bug. |
| `admin/.../views/components/layouts/index.blade.php:74` | `<link rel="preload" as="image" href="{{ url('cache/logo/bagisto.png') }}">` | A **Bagisto** mark (the other upstream project), on a page `<head>`. Outside the `krayin` / `webkul` grep, found while sweeping. Out of this deck's letter; in scope for hard rule 1 if Bagisto counts as a vendor mark (§9.12). |
| `admin/src/Resources/assets/images/logo.svg`, `dark-logo.svg`, `mobile-light-logo.svg`, `mobile-dark-logo.svg`, `favicon.ico`; `installer/src/Resources/assets/images/krayin-logo.svg`; `public/admin/build`, `public/installer/build` | The vendor logo artwork itself | Asset swap, not copy: replace with `brand/sijilna-wordmark.svg` (and the Arabic lockup for `ar`), regenerate the built assets. Referenced from `header/index.blade.php:17,24,94,159`, `layouts/anonymous.blade.php:51`, `emails/layout.blade.php:19`, installer view line 132. |

## 6. Longest proposed value per surface (UI fit)

Characters, UTF-8, tags and placeholders included.

| Surface | Longest new value | Chars | Old chars | Fit |
| --- | --- | --- | --- | --- |
| Help page lead paragraph (`help.index.description`) | `Everything you need to run Sijilna well — support, maintenance and documentation, plus a place to ask questions.` | 112 | 146 | wraps under the 3xl hero column, same class as before |
| Help card description (`api-docs.description`) | `Full REST API reference with endpoints, authentication and examples to integrate Sijilna with your stack.` | 105 | 104 | **+1 char** — the only value in the deck that is not shorter |
| Help card description (`cloud-hosting.description`) | `Sijilna runs on your own server. This covers installing it, updating it and backing it up.` | 90 | 116 | 3-column grid card, shorter |
| Help card URL label (`url_label`) | `sijilna.com` | 12 | 25 | shorter; only the surviving self-hosted card keeps a link (decision 2) |
| Footer credit (`powered-by.description`) | `Powered by :krayin.` | 19 | 54 | much shorter |
| Footer credit, rendered value (§2.4; provider `settings.footer.label` = `Brand::FOOTER_CREDIT`, `core_config.php:73` unedited) | see §2.4 | 114 | 250 | halved; fixed bottom bar |
| Footer credit, bilingual variant (§3.4; same provider key, or the seeded row) | see §3.4 | 155 | 250 | still shorter than the old value |
| Installer paragraph (`installation-description`) | `Installing Sijilna takes a few steps. Here is what this wizard does.` | 68 | 117 | shorter; left column max-w 362px |
| Installer success line (`title-info`) | `Sijilna has been installed successfully.` | 40 | 48 | |
| Console box line (`ComposerEvents`) | *accepted residual — not rewritten (§2.7, decision 6)* | — | — | no new value to fit |
| iCal `PRODID` | `PRODID:-//Sijilna//Sijilna//EN` | 30 | 34 | |
| **Arabic — Help lead** (`help.index.description`) | `كل ما تحتاجه لتشغيل سجلنا بسلاسة — الدعم والصيانة والوثائق، ومكان تسأل فيه أسئلتك.` | 82 | 132 | shorter |
| **Arabic — Help card** (`cloud-hosting.description`) | `يعمل سجلنا على خادمك الخاص. يغطي هذا القسم تثبيته وتحديثه والنسخ الاحتياطي.` | 75 | 118 | shorter |
| **Arabic — Installer** (`installation-description`) | `يتم تثبيت سجلنا على عدة خطوات. إليك ما يفعله هذا المعالج.` | 57 | 88 | shorter |
| **Arabic — longest of all** (`krayin-info`) | `نظام إدارة عملاء تستضيفه بنفسك` | 30 | 30 | single caption line under the wizard logo |
| **Arabic — footer** | `مدعوم من Sijilna.` | 17 | 52 | shorter |

Measured with `len(str)` on the exact strings above (UTF-8 code points). One
value grows by a single character and one is equal in length; everything else is
shorter than what it replaces, so no layout retest is demanded by length.

Palette rule to carry into the wiring: Arabic body text must be **≥16 px at
weight 500**; every string above is body text on `#FFFFFF` or `#FAF7F1`, so ink
`#12263A` or register blue `#0B3C5D`. Ochre appears nowhere in this deck —
nothing here is a mark or a rule.

## 7. Verification

Every row was derived from the source, not from a summary. Commands and their
output at deck time:

```text
$ git grep -n -i -e krayin -e webkul -- ':!vendor/' ':!node_modules/' | wc -l
2425
$ git grep -l -i -e krayin -e webkul -- ':!vendor/' ':!node_modules/' | wc -l
641
$ git grep -l -i -e krayin -e webkul -- '*/lang/*' | sort -u | wc -l
20     # 10 Admin locales + 10 Installer locales
```

Of those 641 files, the deck's 89 proposed edits sat in **15 files**. The
2026-10-17 operator decisions changed what happens to several groups, and the
purge that followed also applied the `en`/`ar` value rewrites, so the same
targets now carry **54 rewrites, 27 deletions and 7 withdrawals** across
**14 files**. Counting unit: **one row of §2/§3 = one string**, the same unit
the deck-time table used. `packages/Webkul/Admin/src/Config/core_config.php`
leaves the file count because it is deliberately unedited — its one string is
applied by the provider (§2.4).

| Group | Rewrites | Deletions | Withdrawn¹ | Files (copy targets) |
| --- | --- | --- | --- | --- |
| Admin lang `en` + `ar` | 10 | 6 | 0 | 2 |
| Admin session views | 6 | 0² | 0 | 3 |
| Admin footer credit (provider config, §2.4) | 1 | 0 | 0 | 0 |
| Admin `Config/support.php` | 3 | 12 | 0 | 1 |
| Installer lang `en` + `ar` | 24 | 6 | 0 | 2 |
| Installer view | 4 | 3 | 0 | 1 |
| Console banner / on-boarding / artisan | 3 | 0 | 7 | 3 |
| iCal `PRODID` | 1 | 0 | 0 | 1 |
| `.env.example` | 2 | 0 | 0 | 1 |
| **Total** | **54** | **27** | **7** | **14** |

**Files (copy targets)** counts the copy targets the plan touches, not upstream
files edited in the tree — the authoritative list of upstream files actually
edited is the merge-debt table in `.agents/context/guides/rebrand-boundary.md`
— and two of the 14, `core_config.php` (§2.4) and `Config/support.php` (§2.3),
are clean in the tree, their copy applied at runtime by `overrideFooterCredit()`
and `overrideHelpSupportConfig()`.

¹ **Withdrawn** = an accepted core residual under decision 6: the five
`ComposerEvents.php` cloud-hosting panel rows (§2.7) and the two
`on-boarding.php` ASCII-banner rows. Not rewritten, not deleted.

² Each session-view row rewrites the `:krayin` entry **and** deletes the
`webkul` array entry in the same row, so the six rows delete six further
user-facing entries that are counted inside the row, not as separate strings.

**How each group is counted, so it can be re-derived.** Every row of §2.1,
§2.2, §2.3, §2.4, §2.5, §2.6, §2.7 and §2.8 (and §3.1–§3.5) is walked once and
tagged *rewrite* (the value changes), *deletion* (decision 2, or §9.4 for the
installer `webkul` key, removes the string) or *withdrawn* (decision 6 accepts
the vendor string as a residual):

- **Admin lang `en` + `ar` (10 / 6).** Per locale, five rewrites
  (`powered-by.description`, `help.index.description`,
  `still-need-help-description`, `cloud-hosting.title`,
  `cloud-hosting.description`) and three deletions —
  `extensions.description`, `api-docs.description`,
  `community.forums.description` — because decision 2 deletes the cards whose
  text those keys are (§2.2, §3.2).
- **Admin footer credit (1 / 0).** §2.4's single string is one rewrite; the
  file is unedited, so the group contributes no file — the provider applies the
  value.
- **Admin `Config/support.php` (3 / 12).** Only `services[0].url`,
  `services[0].url_label` and `contact_url` survive, on the approved root
  domain; the seven card entries the decision deletes (14 `url`/`url_label`
  values) are deletions (§2.3).
- **Installer lang `en` + `ar` (24 / 6).** Per locale, three deletions
  (`installation-completed.krayin-forums`, `.explore-krayin-extensions` —
  decision 2 — and `webkul` — §9.4) and twelve rewrites.
- **Installer view (4 / 3).** Rows `132`, `259`, `735`, `1103` are rewrites;
  `267–273`, `1027`, `1034` are deletions. **This group has 7 rows, not the 8
  the deck-time table carried**; §2.6 lists seven, so this recount uses seven
  and the grand total is 88 rather than 89. The one-row difference sits in this
  group only; no other group's count moved.
- **Console (3 / 0 / 7 withdrawn).** Only the three plain `Installer.php`
  console lines (142, 214, 391) are rewrites.

Plus the locale work, now **measured against the tree**. `git diff --numstat`
over the same 20 lang files, two baselines:

```text
132393d5   the purge, as first approved       42 insertions / 232 deletions
bf545355   pre-rebrand tree, as shipped      216 insertions / 216 deletions
```

The purge's **42 / 232** is **184 key deletions in 16 files** (23 keys × 8
locales, written as 190 deleted lines — the 184 keys plus **6 blank separators**
in `es`, `fa`, `ja`, `pt_BR`, `tr`, `vi`) plus the **42 `en`/`ar` substitutions**.

**Final mechanism, settled 2026-10-17: the keys return carrying their own
pre-rebrand translations, vendor token replaced by Sijilna.** Re-measured:
`git diff --numstat bf545355` = **7/7** per Admin locale file, **14/14** per
Installer file (**15/15** in `ko`, `tr`) — **170 changed lines in 16 files** —
plus **46** in `en`/`ar` (the caption `krayin-info` adds one), so 170 + 46 = 216;
against `HEAD` the same 20 files are **192 insertions / 2 deletions**.

Both upstream spec tests pass with `lang.spec.ts` **unedited**: parity holds
because every key is back, and `no locale falls further behind English` holds at
exactly its recorded `BACKLOG` (35 combos; `Admin/ar` 1, `Admin/pt_BR` 46,
`Admin/tr` 23, `Admin/vi` 41, all others 0; no value carries a vendor token).
The group table above still counts *rows* of §2/§3, so it stays **54 / 27 / 7**.

Key-existence verification — every key named in §2 and §3 was confirmed in place
before it was written down:

```text
$ grep -n "powered-by' => \[" packages/Webkul/Admin/src/Resources/lang/en/app.php
339:            'powered-by' => [
$ grep -n "'description' => 'Powered by :krayin" packages/Webkul/Admin/src/Resources/lang/en/app.php
340:                'description' => 'Powered by :krayin, an open-source project by :webkul.',
$ grep -n "'title' => 'Help & Resources'" packages/Webkul/Admin/src/Resources/lang/en/app.php
2313:            'title' => 'Help & Resources',
$ grep -n "cloud-hosting' => \[" packages/Webkul/Admin/src/Resources/lang/en/app.php
2322:                'cloud-hosting' => [
$ grep -n "api-docs' => \[" packages/Webkul/Admin/src/Resources/lang/en/app.php
2346:                'api-docs' => [
$ grep -n "forums' => \[" packages/Webkul/Admin/src/Resources/lang/en/app.php
2353:                'forums' => [
$ grep -n "'description' => 'مدعوم من :krayin" packages/Webkul/Admin/src/Resources/lang/ar/app.php
340:                'description' => 'مدعوم من :krayin، وهو مشروع مفتوح المصدر من :webkul.',
$ grep -n "installation-processing' => \[" packages/Webkul/Installer/src/Resources/lang/en/app.php
231:            'installation-processing' => [
$ grep -n "start' => \[" packages/Webkul/Installer/src/Resources/lang/en/app.php
256:            'start' => [
$ grep -n "'krayin' => 'Krayin'" packages/Webkul/Installer/src/Resources/lang/en/app.php
133:                'krayin' => 'Krayin',
148:                'krayin' => 'Krayin',
288:            'krayin' => 'Krayin',
```

Each line number in §2.5 / §3.5 was then read back with `sed -n` on the actual
file; the parent keys (`installer.index.…`) resolve as
`installer::app.installer.index.…` because
`InstallerServiceProvider.php:40` calls
`loadTranslationsFrom(__DIR__.'/../Resources/lang', 'installer')`, and the Admin
keys resolve as `admin::app.…`.

Nobody edits a key that does not exist: if a row's key is not found, the wiring
lane should stop rather than guess.

Citation self-check — every `(file, line, current text)` triple in §2, §3, §4 and
§5 was replayed against the working tree by a script that reads the named line
and asserts the quoted current text is on it. That script now reports
`checked 121 citations, 0 wrong`. It found five wrong line numbers on the first
pass — the `ComposerEvents.php` array indices, the `help/card.blade.php` label
line, two `emails/layout.blade.php` lines and the `Version.php` signature line —
all corrected in this document and re-verified.

My only write — `brand/` was already untracked with the asset lane's files when
this task started, so a directory-level `git status` cannot separate them:

```text
$ git status --porcelain
 M .agents/context/guides/navigation.md
 M .agents/context/navigation.md
 M .agents/context/operations/navigation.md
 M .agents/context/quick-start.md
 M AGENTS.md
 M README.md
?? .agents/context/guides/rebrand-boundary.md
?? .agents/context/operations/brand.md
?? THIRD-PARTY-NOTICES.md
?? brand/

$ git status --porcelain --untracked-files=all brand/
?? brand/copy-deck.md      <-- this task
?? brand/palette.md        <-- asset lane
?? brand/png/…             <-- asset lane (15 files)
?? brand/sijilna-*.svg     <-- asset lane (5 files)
?? brand/sijilna-test-sheet.{html,png}   <-- asset lane
```

Every ` M ` and `??` outside `brand/` is another lane's uncommitted work
(attribution, the boundary record, brand assets); none was touched. The 20
locale files, this deck and `rebrand-boundary.md` are the only files this
session wrote; no navigation file, no source file and no git state-changing
command (`add`, `commit`, `stash`, `checkout`, `restore`, `clean`) was run.

## 8. Runtime wiring notes for the lane that applies this deck

1. **The rendered value is the provider's config key, not the code default.**
   The admin footer reads `core()->getConfigData('general.settings.footer.label')`
   (`admin/.../components/layouts/index.blade.php:154`); with no row,
   `getDefaultConfig()` strips the leading `general.` and returns
   `Config::get('settings.footer.label', <line-73 default>)`
   (`packages/Webkul/Core/src/SystemConfig.php:160–170`, `:182–184`).
   `overrideFooterCredit()` sets that key to `Brand::FOOTER_CREDIT`, so the
   provider value renders and line 73 is never consulted; `core_config.php` stays
   **unedited** and `git status` shows it clean. This database holds exactly one
   `core_config` row, `installation.completed`. A seeded row is still wanted
   (decision 5) — for visibility under **Settings** and for per-client
   white-labelling — but as a feature, not a fix (§9.6).
2. **`HelpTest` is replaced, not updated.** `tests/Feature/HelpTest.php:25`
   asserts `krayincrm.com/cloud-hosting`. Decision 2 deletes the Help cards, so
   under decision 10 the file is **replaced** by a regression test that fails if
   a vendor mark reappears on a user-facing surface; it must **not** be updated
   to assert a new Help label. The “after §2.3 it must assert the new label”
   instruction this item used to carry is withdrawn.
3. **`HtmlSanitizationTest.php:38`** now uses the brand string as its
   sanitisation fixture (`krayincrm.com`/`Krayin` → `sijilna.com`/`Sijilna`); it
   proves sanitisation, not branding, so do not read it as a surface. The swap
   is merge debt — see `rebrand-boundary.md`.
4. **Built assets.** `public/admin/build/assets/` ships `logo-Bjh7YAuF.svg`,
   `dark-logo-Dnw3cpYj.svg`, `mobile-light-logo-*` and `mobile-dark-logo-*`, and
   `public/installer/build/assets/` ships `krayin-logo-D5SO44Ys.svg`. A view edit
   alone will not remove them from the served bundle; `npm run build` must follow
   the asset swap. (`public/webform/build/assets/` carries no vendor logo.)
5. **Cache.** After changing lang files, clear the compiled view/translation
   cache (`php artisan optimize:clear`) or the old strings keep rendering.
6. **`config('app.name')`** reaches the mail chrome and the installer default;
   `APP_NAME` in the live `.env` is the real switch.

## 9. Needs a human decision

1. **Tagline — RESOLVED (decisions 1 and 11).** “Every deal, on the record.” /
   كل صفقة لها سجل. is **approved**, not proposed. Its placement is the login
   screen and the mail chrome only; nothing in the admin's working screens, and
   the footer credit is **not** its home — that suggestion was wrong.
2. **Invented Sijilna URLs — RESOLVED (decision 2).** No invented
   `sijilna.com/<path>` ships. The Help cards and the installer completion-step
   cards are deleted (§2.3, §2.6), and the two destinations decision 2 does not
   name — the surviving self-hosted card and `contact_url` — fall back to the
   approved root domain `https://sijilna.com/`. The dead-link risk this item
   described is gone because the links are gone.
3. **The community block — RESOLVED (decision 2).** The forum and video cards
   are deleted, not linked: `community[0]`, `community[1]` and the
   `help.index.community.*` lang keys leave the tree with them (§2.2, §2.3).
4. **The installer caption's vendor slot — RESOLVED (decisions 3 and 4).** The caption is
   config-driven, not a lang key: `BrandingServiceProvider.php:322–323` sets
   `sijilna.installer.caption_en` = `a project by Sijilna` / `caption_ar` = `نظام إدارة عملاء
   تستضيفه بنفسك`, printed by the brand override `installer/installer/index.blade.php:263`.
   That override replaces the **whole** caption paragraph (core 253–272), so the core
   `krayin-info` span (265) never renders: its `a self-hosted CRM` (§2.5/§3.5 row 289) is
   dead copy kept for key parity, as is the empty `webkul` key (297).
5. **`create-administrator.krayin` is doing double duty — OPEN.** `index.blade.php:913`
   uses it as the **example name** placeholder in the administrator-name field,
   while `environment-configuration.krayin` (`:738`) is the application-name
   placeholder. I propose `Admin` / `مسؤول` for the name field, but a real
   example name (or removing the `placeholder` attribute for that field) is the
   better product decision.
6. **Footer credit renders from the provider's `settings.footer.label` —
   RESOLVED (decision 5).** `SystemConfig::getConfigData()`
   (`packages/Webkul/Core/src/SystemConfig.php:176`) looks up a `core_config`
   row and, when none exists, calls `getDefaultConfig()`, which strips the
   leading `general.` and returns
   `Config::get('settings.footer.label', <line-73 default>)` (`:160–170`,
   `:182–184`). `overrideFooterCredit()` sets `settings.footer.label` to
   `Brand::FOOTER_CREDIT`, so the provider value renders; `core_config.php:73`
   stays **unedited** and is never consulted. A seeded
   `general.settings.footer.label` row outranks the provider key too, and is
   wanted: it makes the value visible under **Settings** and is the correct
   per-client white-labelling mechanism. That seeding is a later lane's feature,
   not a prerequisite.
7. **The footer credit is the one string that cannot be bilingual — OPEN.**
   Decision 5 seeds the `core_config` row but does not choose its language.
   `core_config` stores one HTML value for all locales. I recommend the
   mixed-form value in §3.4. Also choose the Arabic wording:
   `من :krayin` (my recommendation) vs `مدعوم من :krayin` vs
   `سجلنا — جميع الحقوق محفوظة`. No literal "powered by" form was chosen because
   none reads well in Arabic.
8. **`installation-processing.krayin-info` — OPEN.** (`Creating the database tables…`)
   carries no vendor mark and stays as it is, but its heading above it is
   rewritten by this deck. If the operator wants the pair to move together,
   that is a change outside rebrand scope.
9. **`composer.json` package rename — RESOLVED (decision 3).** The root package
   is `sijilna/crm`. The lock's `content-hash` is stale **from that rename** and
   is still to be refreshed: `description` does not affect the hash, and
   `COMPOSER_DISABLE_NETWORK=1 composer update --lock` cannot run on this checkout
   because the Packagist package metadata is not cached locally, so it exits 2
   with 186 unresolved packages. Reverting the rename is not an option; run the
   sanctioned command where the metadata is warm.
10. **The console cloud-hosting panel — RESOLVED (decision 6).** It is an
    **accepted core residual**: not rewritten, not deleted, not re-filed. The
    §2.7 rewrite and the deletion recommendation are both withdrawn.
11. **`index.blade.php:1103` — OPEN.** A hardcoded English JS error with no lang key.
    I replace the vendor name only. Making it translatable is a code change and
    is not required by the rebrand.
12. **`bagisto.png` preload — RESOLVED.** The brand layout override
    (`packages/Sijilna/Brand/resources/views/admin/components/layouts/index.blade.php:74`)
    now points the preload at the fork's own `cache/logo/sijilna.png`, so no
    Bagisto token ships in the admin `<head>`. The route is replaced
    (decision 7), so `replaceVendorLogoRoute()` owns `cache/{filename}` and
    answers the request locally (`200`, `image/svg+xml`, the Sijilna mark): no
    Bagisto or Krayin artwork is fetched and no network request leaves the
    machine. The core blade keeps its `bagisto.png` string but is shadowed by
    the override. The open question is closed — a vendor-named URL does count,
    and it is gone from the rendered markup.
13. **The installer phoning home — RESOLVED (decisions 6 and 7).** The outbound
    vendor calls are stopped by provider registrations, not a Blade override:
    `disableVendorInstallTelemetry()` forgets the `krayin.installed` listener,
    and `replaceVendorLogoRoute()` replaces the `cache/{filename}` route so
    `ImageCacheController` is never reached. The unreachable controller class
    (`ImageCacheController.php:23`) is an **accepted core residual** under
    decision 6; the replaced route is not.
14. **`cloud-hosting` card retitled to "Self-hosted Deployment" — RESOLVED
    (decision 2).** The managed-hosting offer leaves the tree with the deleted
    cards; the surviving self-hosted card keeps the retitled copy and falls back
    to the approved root domain, so nothing here sells a hosted tier.
15. **Arabic add-on vocabulary — OPEN.** I standardised on «إضافات» (matching the Admin
    locale) while the installer locale used «امتدادات». Only the installer row
    (241) still turns on this; row 2325 is a deletion under decision 2.
16. **Completion-step card labels — RESOLVED (decision 2).**
    `installation-completed.krayin-forums` and `.explore-krayin-extensions`
    label the deleted completion-step cards, so the keys are deleted with the
    anchors (`index.blade.php:1027`, `:1034`); no vendor forum URL and no
    invented `sijilna.com` path is involved.
17. **The info toast's blue — RESOLVED (decision 6): accepted as a fifth
    residual.** Accepted 2026-10-17 by the operator.
    `packages/Webkul/Admin/src/Resources/views/components/flash-group/item.blade.php`
    hardcodes `#0E90D9` three times (lines 131, 133, 135) as the **info** toast
    severity. It is a severity colour, not a brand mark, and the brand slot is
    correct — verified 2026-10-17: `/admin/login` renders `--brand-color: #0B3C5D`
    and exactly three `#0E90D9` literals, all from that blade. Config cannot reach
    it, because the colours are a literal array in a core blade with no config
    key, so the fix would be a core edit or a copy of the component. It is now one
    of decision 6's five accepted residuals, and `rebrand-boundary.md` keeps that
    list at five. Copying the flash-group component into `packages/Sijilna/Brand`
    is withdrawn.
