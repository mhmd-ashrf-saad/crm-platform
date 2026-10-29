# Security review and dependency advisory sweep

> **Basis:** commit `ad180161` on `main`, working tree dirty with other lanes'
> changes (this review read the tracked state of the files it cites).
> **Date of the sweep:** 2026-10-29, 18:29–18:37 UTC.
> **What was reviewed by reading code, not by running the app:** routes,
> middleware registrations, `config/`, `deploy/` configs, the installer gate,
> upload paths, the ACL surface, and the composed dependency tree in
> `composer.lock` (`content-hash 6133d5a28268e5d7a29b19d1204c0c90`).
> **What was not done:** nothing was deployed, no request was made against a live
> instance, no penetration test was performed, no dependency was upgraded. This is
> a source review plus an advisory scan — not a proof that the system is secure.
> **Companions:** [support-sla.md](support-sla.md),
> [incident-response.md](incident-response.md),
> [../legal/dpa.md](../legal/dpa.md) §4.

## 1. Environment as reviewed

| Item | Value |
| --- | --- |
| PHP on the development host | **8.5.4** (CLI), built 2026-10-19, Ubuntu |
| PHP the product declares | `php: ^8.3` (`composer.json`) |
| PHP in the deployment image | **8.3**, PHP-FPM (`deploy/docker/php.Dockerfile`) |
| Composer | 2.10.1 |
| Framework | `laravel/framework` **v12.66.0** |
| Web server for a client instance | nginx:alpine serving `public/`, PHP-FPM upstream (`deploy/docker/nginx.conf`) |
| Database | MySQL 8.4 in a named volume, no published port (`deploy/docker-compose.yml`) |

The three PHP 8.3 runtime paths (image, declared floor, dev host 8.5) are not the
same interpreter. Nothing in this review depends on that difference, but a
"works on my machine" fix on 8.5 is not proof for the 8.3 image.

**Concurrent uncommitted work, disclosed:** while this review was read, the
working tree also carried unrelated in-flight changes to the upload paths —
`TinyMCEController.php`, `WebForm.php` and a new
`packages/Webkul/Core/src/Config/uploads.php` raising and centralising the size
ceilings. Those changes touch **size**, not the extension whitelist gap in F12,
which is in `AttributeForm.php` and was untouched. Every finding below was
verified against the tracked state at `ad180161`; a reader re-checking a line
number should expect drift.

## 2. The advisory scan — how it was run and what it returned

```bash
composer audit --locked --format=plain     # exit 1
composer audit --locked --format=json      # exit 1
```

**Result: `Found 16 security vulnerability advisories affecting 9 packages.`
No abandoned packages. Exit code 1 means advisories are present.**
The scan reached the network and used the committed lockfile (`--locked`), so this
is the tree that would actually be installed, not a hypothetical resolution.

| Severity | Count |
| --- | --- |
| **Critical** | **1** |
| **High** | **6** |
| Medium | 7 |
| Low | 2 |
| **Total** | **16** |

| Package | Installed | Advisories | Severities |
| --- | --- | --- | --- |
| `laravel/framework` | v12.66.0 | 3 | critical, medium, low |
| `symfony/http-foundation` | v7.4.16 | 1 | high |
| `symfony/mime` | v7.4.16 | 1 | medium |
| `symfony/yaml` | v7.4.15 | 1 | high |
| `league/commonmark` | 2.10.0 | 2 | high, medium |
| `league/flysystem` | 3.35.2 | 1 | low |
| `phpoffice/phpspreadsheet` | 1.30.6 | 3 | 2 high, 1 medium |
| `maatwebsite/excel` | 3.1.68 | 1 | high |
| `enshrined/svg-sanitize` | 0.22.0 | 3 | 3 medium |

## 3. Findings

Severity below is **this review's** rating for *this application*, not the
advisory's own label — reachability changes everything, and both numbers are
given where they differ.

### F1 — `laravel/framework` v12.66.0: TokenGuard authentication bypass — advisory critical, **app exposure: latent**

`CVE-2026-114509`, affected `<12.68.0`. Non-string API credentials bypass
`TokenGuard`.

**Why it is not exploitable today:** `config/auth.php` defines exactly one guard —
`'user'`, a *session* driver. There is no `api` guard. `routes/api.php` declares
its single route behind `auth:api`, so that route cannot authenticate at all and
fails when called. There is no token guard for the bypass to attack.

**Why it must still be fixed:** `laravel/sanctum` **4.3.1** is installed and
auto-discovered. The moment a token-based API is added — the obvious next feature
— this becomes remote, unauthenticated, administrative-level compromise. It is one
`config/auth.php` line away from critical.

### F2 — `symfony/http-foundation` v7.4.16: ReDoS via the `Cache-Control` request header — advisory high, **app exposure: high, unauthenticated**

`CVE-2026-108082`, affected `<7.4.19`. `HeaderUtils::split()` backtracks
quadratically on a crafted header value. Every request passes through
`http-foundation`; the header is attacker-supplied and needs no session. One
request, crafted, is a CPU denial of service on a 4-child PHP-FPM pool.

**This is the most practically dangerous item in the list**, because it needs no
account and no special path.

### F3 — `symfony/yaml` v7.4.15 / `symfony/mime` v7.4.16 — advisory high / medium

`CVE-2026-109413` (unbounded recursion in the YAML inline lexer) and
`CVE-2026-45070` (email header injection via non-token characters in MIME
parameter names). **Reachability in this application is not established by
reading:** the YAML parser is reached by the framework rather than by a
user-facing import path, and the MIME injection needs attacker-controlled
parameter names in a message the application builds. Rated **medium** here: real
defects, no path found.

### F4 — `phpoffice/phpspreadsheet` 1.30.6 + `maatwebsite/excel` 3.1.68 — **high, authenticated**

Five advisories across the two packages: XML scanner bypass giving XXE
(`CVE-2026-108358`), ODS `number-rows-repeated` CPU/memory exhaustion
(`CVE-2026-108359`), SSRF via an unvalidated HTTP redirect in external image
loading (`CVE-2026-108360`), and Excel writing exports outside the configured
filesystem disk when given a caller-controlled path (`CVE-2026-84374`).

**Reachable in this product:** import and export are product features — persons,
products and leads are importable, and leads and persons are exportable
(`docs/client-guide/_internal/facts.md` §3, `packages/Webkul/DataTransfer/`). A
crafted spreadsheet uploaded by an **authenticated user with import permission**
can exhaust the process, attempt an outbound request, or read a local file through
XXE. On a 4-worker FPM pool with a 256 MB limit
(`deploy/docker/php-fpm.conf`), exhaustion is a full outage, not a slow request.

### F5 — `enshrined/svg-sanitize` 0.22.0 — **high, authenticated; stored XSS**

Three advisories, all `<=0.22.0` — the installed version is the last affected one.
One is a **stored-XSS bypass** (DTD entity / HTML5 named-character-reference
collision), one a nesting-DoS check skipped by mixed-case `xlink:HrEf`, one a DTD
attribute-declaration crash.

**Reachable:** SVG uploads are allowed and sanitised with exactly this library —
TinyMCE uploads accept `bmp,gif,jpeg,jpg,png,svg,webp` up to 8 MB
(`packages/Webkul/Admin/src/Http/Controllers/TinyMCEController.php:22,30`), and
attribute values of type file/image are sanitised by the same library
(`packages/Webkul/Attribute/src/Repositories/AttributeValueRepository.php:95`).
A sanitizer bypass here is a **stored XSS in the admin origin**, harvested by
whoever uploaded the SVG and loading for every administrator who views the record.

### F6 — `league/commonmark` 2.10.0 / `league/flysystem` 3.35.2 — medium / low

Quadratic-time DoS in the GFM table block-start scan and a `DisallowedRawHtml`
bypass; a control-character path check bypassed by malformed UTF-8 in Flysystem.
Reachability not established by reading (markdown rendering in this product is
driven by admin-authored templates, not by front-end user input). Recorded, fixed
in the same batch.

### F7 — No security headers at all — **medium**, config

A repository-wide search for `X-Frame-Options`, `Content-Security-Policy`,
`X-Content-Type-Options`, `Strict-Transport-Security`, `Referrer-Policy` and
`Permissions-Policy` across `deploy/`, `packages/`, `app/` and the Blade views
returns **nothing**. `deploy/docker/nginx.conf` sets no `add_header` except cache
headers on static assets. Consequences: the admin UI can be framed (clickjacking
on mass-delete and configuration forms), MIME sniffing is not disabled, and there
is no HSTS for a client instance that is HTTPS.

Fix: `add_header` block in `deploy/docker/nginx.conf` plus an edge rule for
tunnel-fronted instances, then verify with `curl -sI`. The CSP needs care — the
admin UI ships inline scripts and a Vite build.

### F8 — The session cookie is not marked `Secure` — **low–medium**, config

`config/session.php:171` reads `SESSION_SECURE_COOKIE` from the environment and
`http_only` is `true` with `same_site` `lax` (both good). But the key is **absent
from both `.env.example` and `deploy/.env.deploy.example`**, so it is unset and
the cookie is sent over plain HTTP as well. Behind the Cloudflare Tunnel the
browser leg is HTTPS, so the demo is not exposed — but a client instance reached
over plain HTTP on a LAN, or one where `APP_FORCE_HTTPS` is forgotten, transmits a
session cookie in clear.

Fix: `SESSION_SECURE_COOKIE=true` in the deploy env template, and a container
restart to pick it up.

### F9 — `.env.example` ships `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug` — **medium as a trap**

The file a reader copies first enables the debug page: stack traces with file
paths and snippets, environment values, and query bindings on any error. It also
activates the framework's debug-page XSS advisory (`CVE-2026-102279`, low,
`<12.69.0`) — a finding that is merely latent becomes live.

The **provisioning path is already correct**: `deploy/.env.deploy.example` ships
`APP_ENV=production`, `APP_DEBUG=false`, `APP_FORCE_HTTPS=true`, and
`deploy/provision-instance.sh` writes those literals
(`deploy/provision-instance.sh:346`). So no client instance created by the
provisioning script has debug on.

Fix: a loud line at the top of `README.md`/the client runbook — "never deploy
`.env.example` as-is" — and, if `.env.example` were ever made the install
template, flip the three values. **Not changed by this review** (README is another
lane's path).

### F10 — Authentication and session handling — what is actually there

Reviewed because it is the first thing a buyer's reviewer asks about.

- Login is session-based on the `user` guard. **Login failures are rate-limited**
  to 5 per credential set and 20 per address per minute, counting failures only
  (`packages/Webkul/Admin/src/Http/Controllers/User/SessionController.php`,
  `ensureIsNotRateLimited`). Password reset is `throttle:5,1`
  (`packages/Webkul/Admin/src/Routes/Admin/auth-routes.php:40,49`).
- **Defect: a rate-limited login renders as HTTP 500.** `abort(429)` is thrown and
  the package exception handler rewrites every status outside
  `[401,403,404,503]` to 500, so the user sees "internal server error" instead of
  "too many attempts" (`docs/client-guide/_internal/facts.md` §7.1). It is a
  correctness and support-cost defect, not a security hole — the limit still
  holds. **recorded, not fixed** (core file).
- **No multi-factor authentication anywhere.** A repository search for
  `two_factor`, `2fa`, `google2fa`, `otp` in `packages/Webkul/`, `config/` and
  `app/` finds only `ForgotPasswordController`. A client administrator account is
  protected by a password alone.
- **No invitation flow.** The administrator types the new user's password, and the
  "you were added" mail carries the username only; its failure is swallowed
  (`docs/client-guide/_internal/facts.md` §2). Passwords therefore travel
  out-of-band and are chosen by someone other than their owner — say this to a
  security-conscious buyer rather than letting them discover it.
- Primary administrator (id 1) cannot be demoted or disabled from the UI; a
  non-administrator cannot grant a permission or a data scope wider than their own
  (`docs/client-guide/_internal/facts.md` §2). Those guards are good and should be
  quoted.

### F11 — Authorisation coverage — **medium, real gaps**

- **Mass delete and export are not ACL-guarded** on leads, quotes, products,
  organizations and activities. Only persons check the permission
  (`docs/client-guide/_internal/facts.md` §7.7, with file and line references for
  each datagrid). Any user who can open the grid can mass-delete from it. That is
  a data-destruction path available to the lowest-privileged role.
- **One ACL key gates the whole configuration tree** (`configuration`), so there is
  no way to give someone the language setting and nothing else (§2).
- **Two working screens have no menu entry and no ACL key** — Google Contacts and
  Web Forms — reachable by direct URL (§7, chapter 16). "Not in the menu" is not
  "not accessible".
- Tagging a lead requires a *settings* permission, so an ordinary user sees the
  control and is refused (§7.9).

These are core-package behaviours. Fixing them is a package-level override or an
upstream change, so each is recorded here and **not** attempted by this review.

### F12 — File uploads — mixed, and the exception is the interesting part

- Image attributes are validated to `mimes:bmp,jpeg,jpg,png,webp`
  (`packages/Webkul/Admin/src/Http/Requests/AttributeForm.php:100`), and TinyMCE
  uploads to a fixed extension list with an 8 MB cap (`TinyMCEController.php`).
- **Attribute type `file` has no MIME or extension rule.** The validation block
  only appends rules for `image`; a `file`-type attribute stores whatever is
  uploaded (`AttributeValueRepository.php:88-99` → `$file->store(...)`). The result
  is arbitrary content hosted on the application origin under `/storage/`, i.e.
  stored XSS via an uploaded `.html` or `.svg`, and a file-hosting surface for a
  compromised account.
- **Mitigating fact, verified in the nginx config:** `location ^~ /storage/`
  is a prefix location with `^~`, which outranks the `\.php$` regex location, so an
  uploaded `.php` file is served as a static file and **is not executed**. The
  front controller also does `try_files $uri =404`, which is what stops
  PATH_INFO-style upload-to-RCE. Those two decisions are the reason the upload gap
  is not critical.
- `client_max_body_size 64m` and `upload_max_filesize` are kept in step
  deliberately (`deploy/docker/nginx.conf`).

### F13 — The installer is correctly sealed, and one file re-opens it — **high impact, low likelihood**

The installer routes are `web` + `installer_locale`, and `CanInstall` makes them
unreachable once installation is complete; the previous AJAX-header bypass
(`X-Requested-With`) was removed and the reason is documented in the middleware
itself (`packages/Webkul/Installer/src/Http/Middleware/CanInstall.php:19-31`). That
is a real fix and worth citing to a buyer.

The residual risk is operational, not code: **`storage/installed` is the
kill-switch.** Delete it (or wipe `storage/`, which `git clean -xfd` does) and the
next container start re-arms the installer, whose internal step is
`migrate:fresh` against the live database, with no warning
(`.agents/context/operations/deployment-homelab.md`). Recorded here because it is
the highest-impact single action available on a live instance;
[incident-response.md](incident-response.md) §4 #1 is where it is detected.

### F14 — API surface — small today, and one exposure worth naming

- `routes/api.php` contains exactly one route, `/api/user`, behind `auth:api` —
  and no `api` guard exists, so it cannot authenticate. There is **no functional
  API** in this product.
- `laravel/sanctum` 4.3.1 is installed and auto-discovered, so
  `/sanctum/csrf-cookie` is registered and answers publicly. It sets a CSRF cookie
  and exposes nothing else, but a public route that the product never intends to
  publish should be disabled until an API is actually shipped.
- The genuinely public endpoints that do exist are **web form submissions**
  (`packages/Webkul/WebForm/src/Routes/routes.php`) and the **inbound-mail webhook**
  (`POST /{admin}/mail/inbound-parse`), the latter gated on
  `MAIL_RECEIVER_INBOUND_TOKEN` and returning 503 without it
  (`docs/client-guide/_internal/facts.md` §4). Web forms ship **no spam
  protection** (§4) — that is an availability and data-pollution issue, not an
  authorisation one.

### F15 — Infrastructure facts that are security decisions, and are right

Recorded because a reviewer will ask, and because they should not be undone:

- MySQL publishes **no port at all** in the client stack (`db` is reachable only as
  `db:3306` on the compose network); the development container binds loopback
  explicitly (`-p 127.0.0.1:3306:3306`) — never `0.0.0.0`
  (`.agents/context/operations/environment-and-deployment.md`).
- nginx publishes **one** port, bound to `127.0.0.1:3102`, never `0.0.0.0`
  (`.agents/context/operations/deployment-homelab.md`).
- Dotfiles are denied (`location ~ /\.(?!well-known)`), and the document root is
  `public/`, so the checkout's `.env` is one directory above anything nginx can
  reach.
- `.env` is **not tracked**: `git ls-files | grep -E '(^|/)\.env'` returns only
  `.env.example` and `deploy/.env.deploy.example`. No `APP_KEY` literal exists in
  any tracked file.
- The runtime `.env` in the container deploy is mode **600**, owned by uid 33 —
  deliberately not widened to 644
  (`.agents/context/operations/deployment-homelab.md`).
- `APP_KEY` rotation would make encrypted columns unreadable; it is documented as
  a destructive operation (`.agents/context/operations/provisioning.md`).

### F16 — Backup handling — **medium**, and it is a credential problem

The pre-deploy dumps are unencrypted SQL on the host. The nightly set stages the
checkout **including `.env`** next to the dump, and copies to a laptop at 03:30, so
a copy of the backup repository is **both the data and the key**
(`.agents/context/operations/deployment-homelab.md`, "Open risks" #1). Anyone who
obtains that repository copy obtains readable data and the ability to decrypt
encrypted columns. [../legal/dpa.md](../legal/dpa.md) §5.2 states this to the
client; this review records it as the top item in the fix list that is not a
composer bump.

### F17 — The live demo has a demo administrator credential on a public login page — **highest practical risk on the only public instance**

`.agents/context/operations/deployment-homelab.md` ("Open risks" #3) states it
plainly: the administrator password for `https://crm.mohamedlabs.com` is a demo
credential. The login page is public, the monitor polls it every 60 s, and there is
no MFA (F10) and no IP allow-list. Rotate it before the instance is described to
anyone as a real CRM. Operational, not code — and it needs the operator, not a
pull request.

## 4. The fix list

**No dependency was changed by this review.** `composer.lock` is owned by another
lane, and a bump without a green suite is unverified. These are the exact commands
to run, in this order, followed by the verification in §5.

The engineering findings are tracked as **crm-1ra** (the dependency sweep),
**crm-50q** (headers and the session cookie), **crm-epc** (the `file`-attribute
upload whitelist) and **crm-3ef** (ACL coverage on mass delete and export). The
items that need a commercial or legal decision rather than a code change are
D24 and D25 in [decisions-to-confirm.md](decisions-to-confirm.md).

| # | Finding | Command | Target |
| --- | --- | --- | --- |
| 1 | F2 ReDoS, F3 MIME/YAML | `composer update symfony/http-foundation symfony/mime symfony/yaml --with-dependencies` | ≥ 7.4.19 / ≥ 7.4.22 / ≥ 7.4.18 |
| 2 | F1, F5(low XSS) framework | `composer update laravel/framework --with-dependencies` | ≥ 12.69.0 (satisfies the critical `<12.68.0`, the medium `<12.68.0` and the low `<12.69.0`) |
| 3 | F6 markdown, flysystem | `composer update league/commonmark league/flysystem` | > 2.10.1 commonmark; > 3.35.2 flysystem |
| 4 | F4 spreadsheets + excel | `composer update phpoffice/phpspreadsheet maatwebsite/excel --with-dependencies` | **constraint may have to move**: `composer.json` pins `phpoffice/phpspreadsheet: ^1.30.6` and 1.30.6 is affected, so if no 1.30.x release is fixed, the requirement must move to the next fixed line and that is a **breaking-change decision**, not a routine bump. Verify what the resolver picks before writing the new constraint |
| 5 | F5 svg-sanitize | `composer update enshrined/svg-sanitize` | the version above 0.22.0 released for CVE-2026-107379/107380/107381; `composer.json` pins `^0.22.0`, so the constraint may need widening |
| 6 | F7 headers | edit `deploy/docker/nginx.conf`: `add_header X-Content-Type-Options nosniff;` `add_header X-Frame-Options SAMEORIGIN;` `add_header Referrer-Policy same-origin;` `add_header Permissions-Policy "geolocation=(), microphone=(), camera=()";` `add_header Strict-Transport-Security "max-age=31536000" always;` and a CSP in report-only first (the admin UI uses inline scripts) | `deploy/**` — **not this lane's path** |
| 7 | F8 cookie | `SESSION_SECURE_COOKIE=true` in `deploy/.env.deploy.example` and the existing instance's `.env`; restart the container | `deploy/**` and the instance |
| 8 | F9 example env | a warning that `.env.example` is not a deploy template | `README.md` — another lane |
| 9 | F12 uploads | add an extension whitelist to the `file` attribute validation | `packages/**` — core-ish, needs its own bead |
| 10 | F11 ACL gaps | guard mass delete/export on leads, quotes, products, organizations, activities | `packages/**` — needs its own bead |
| 11 | F14 API | do not add an `api` guard until F1 is patched; disable `/sanctum/csrf-cookie` until an API ships | `config/` |
| 12 | F16 backups | encrypt the dump set, or separate `APP_KEY` from the snapshot; record the retention window | operator action |
| 13 | F17 demo admin | rotate the password on the demo instance | operator action |

Findings F10, F13, F15 are recorded with reasons and need no code change. F11 and
F12 need beads of their own (they are package-level work, not documentation).

## 5. How to verify the fixes

```bash
composer audit --locked --format=plain          # expect: "No security vulnerability advisories found"
composer update --dry-run                       # confirm nothing else moves unexpectedly
make test                                        # the suite, on krayin_test — owned by another lane
curl -sI https://<instance>/admin/login | grep -iE 'x-frame|x-content-type|referrer|strict-transport'
```

A fix is complete when the audit is clean **and** the suite is green **and** the
headers are present on a response. Any one of the three alone is not evidence.

## 6. What this review does not claim

- No penetration test was performed; §3 is source reading plus an advisory scan.
- Nothing was executed against a running instance, so no finding here has a
  runtime proof.
- The advisory scan covers Composer packages only. The **JavaScript build
  dependencies** were not audited (`npm audit` was not run — `node_modules` is
  gitignored and the build is not part of this checkout's running instance), and
  **no container image was scanned** for CVEs.
- Absence of a finding is not evidence of absence.

## Sources

- `composer audit --locked` output, 2026-10-29 (both formats, exit 1).
- `composer.lock` `content-hash 6133d5a28268e5d7a29b19d1204c0c90`.
- `config/auth.php`, `config/session.php`, `routes/api.php`, `bootstrap/app.php`.
- `deploy/docker/nginx.conf`, `deploy/docker/php-fpm.conf`,
  `deploy/docker-compose.yml`, `deploy/.env.deploy.example`,
  `deploy/provision-instance.sh`.
- `packages/Webkul/Installer/src/Http/Middleware/CanInstall.php`,
  `packages/Webkul/Admin/src/Http/Controllers/User/SessionController.php`,
  `packages/Webkul/Admin/src/Http/Controllers/TinyMCEController.php`,
  `packages/Webkul/Admin/src/Http/Requests/AttributeForm.php`,
  `packages/Webkul/Attribute/src/Repositories/AttributeValueRepository.php`,
  `packages/Webkul/Admin/src/Routes/Admin/*.php`.
- `docs/client-guide/_internal/facts.md` §2, §3, §4, §7 (ACL gaps, uploads,
  installer, silent failures).
- `.agents/context/operations/*.md` — deployment, database, provisioning.
