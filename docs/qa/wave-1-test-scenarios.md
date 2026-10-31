# Wave 1 — manual test scenarios

One line: what Wave 1 changed, proven by hand on the live site and a local checkout, in
about 45 minutes, with every step telling you what to click, what you must see, and what a
failure looks like.

> **Status:** written from the merged code on `main` at `6068ccd4`. **No scenario has been
> executed yet** — the results table at the end is blank on purpose and is the deliverable.
> Report results back by scenario ID (`W1-S1`, `W1-S2`, …), not by description.
>
> **Companions:** [../ops/incident-response.md](../ops/incident-response.md) (what a security
> failure starts), [../ops/security-review.md](../ops/security-review.md) (the findings these
> fixes answer), [../ops/secrets-handling.md](../ops/secrets-handling.md) (W1-S20, W1-S21).

## What shipped, and which scenario proves it

| Bead | Commit | What changed | Scenarios |
| --- | --- | --- | --- |
| `crm-3ef` | `a43f9545` | Five grids register the mass-delete action only when the role holds that module's delete permission (Leads, Quotes, Products, Organizations, Activities). Server-side refusal already existed; the change is that a forbidden button is no longer **shown** | W1-S1 … W1-S7 |
| `crm-epc` | `0c108107` | A `file` attribute accepts only csv, doc, docx, ods, odt, pdf, ppt, pptx, rtf, txt, xls, xlsx, zip; an executable-ish extension is refused at the form and, on paths that have no form rule, the uploaded file is deleted and the attribute stored with no value | W1-S8 … W1-S13 |
| `crm-b73` | `0faa2b6d` | The admin and login layouts mount Vue on `DOMContentLoaded` instead of window `load`, with a `readyState` branch for an already-parsed document | W1-S14 … W1-S16 |
| `crm-50q` | `12263386` | Security headers on the nginx layer and `SESSION_SECURE_COOKIE=true` in the deploy template | W1-S17 … W1-S19 |
| `crm-pzp` | `57794ac0` | `docs/ops/secrets-handling.md` — every secret, rotation blast radius, and D27–D34 left open in `docs/ops/decisions-to-confirm.md` | W1-S20 … W1-S21 |

## Before you start

### 0. Where each scenario runs

- **Live:** `https://crm.mohamedlabs.com` — one instance, behind a Cloudflare Tunnel.
  W1-S17 and W1-S18 are **live only**: the headers come from nginx, and the local dev
  server (`php artisan serve`) emits none of them.
- **Local:** `http://127.0.0.1:8000`, started with `make serve`. Safe to break — it holds no
  client data. Everything except W1-S17 and W1-S18 can be run here, and W1-S19 needs it.
- **Never do this as a test:** rotate `APP_KEY`. It destroys every encrypted column, and there
  is no re-encryption command in this repository (`../ops/secrets-handling.md` §3c). Reading
  that runbook is the test (W1-S20, W1-S21); executing its rotation steps is not.

### 1. Two roles

Admin → **Settings → Roles → Create Role**. Both roles need a Name and a Description
(both required).

| Role | Permissions setting | Tick exactly |
| --- | --- | --- |
| **QA No Delete** | Custom | Leads: **View** (+ Create if you want to add a lead as this user). Quotes: View. Products: View. Contacts → Organizations: View. Activities: View. **Tick no Delete anywhere** |
| **QA Delete** | Custom | The same five modules with **View and Delete**; add Create/Edit as you like |

Save each. If you prefer a shortcut for the second role, the existing administrator role also
has every permission and will do — but a custom role is the cleaner regression guard, because
it proves the button appears for a *non-admin* role too.

### 2. Two users

Admin → **Settings → Users → Create User** (one at a time):

- Name: `QA No Delete` — Email: `qa.nodelete@<your-domain>` — Password: your choice.
- **Role: QA No Delete.**
- **View Permission: Global.** This matters: on the default `individual` the user sees only
  records assigned to them, so the grids come up empty and there is nothing to select.
- Status: Active.

Repeat with `qa.delete@<your-domain>` and the **QA Delete** role.

Log in as each user in a **separate browser profile or a separate incognito window**. Two
admin sessions in one profile share one cookie name and will evict each other.

### 3. The attributes

Admin → **Settings → Attributes → Create Attribute**. Both go on **Entity Type: Lead**, both
left **not required**.

| Code | Name | Type |
| --- | --- | --- |
| `qa_document` | QA Document | **File** |
| `qa_image` | QA Image | **Image** |

An attribute created in the admin UI is user-defined, so it appears on the lead create/edit
form straight away — no extra step. Open **Leads → Create Lead** and confirm both fields are
there before going further.

### 4. Records to work with

Create one of each, so every grid has a row to select:

- a lead titled `QA disposable lead` (this one gets deleted in W1-S7),
- a lead titled `QA keep me` (never delete this one),
- a quote, a product, an organization, and one activity.

### 5. Files to prepare

Make a scratch directory outside `/tmp` (its size is bounded and small):

```bash
mkdir -p /var/tmp/pi-scratch/qa-wave1/files && cd /var/tmp/pi-scratch/qa-wave1/files
```

Then produce the files the upload scenarios need — eight files, none larger than a few bytes:

```bash
printf 'a,b\n1,2\n'                 > sample.csv
printf '%%PDF-1.4\n%% QA placeholder\n' > sample.pdf
printf '<h1>QA payload</h1>\n'      > payload.html
printf '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>\n' > payload.svg
printf '<?php echo "QA"; ?>\n'      > payload.php
printf 'MZ QA placeholder\n'        > payload.exe
cp payload.html disguised.pdf
zip -q sample.zip sample.csv
```

`disguised.pdf` is the file W1-S9 needs: HTML content under a `.pdf` name. A `.docx` is not
generated here — use any real Word document you already have.

### 6. The two tools

**Browser console.** Open DevTools (`F12`) → Console, on any admin page while logged in as the
user a scenario names. Chrome may block a first paste: type `allow pasting` and press Enter
when it asks. Cookies are per origin, so the console works from any page of that site.

**curl.** Every command in this document is copy-pasteable as written.

## A. Mass delete is not offered to a role without the delete permission

The server has refused these requests all along (the route→ACL map in
`packages/Webkul/Admin/src/Config/acl.php` plus the fail-closed Bouncer middleware). What Wave 1
changed is that the button is no longer rendered. So each of W1-S1 … W1-S5 has **two** checks:
the button is gone, and the request is still refused when you send it anyway.

Note on the mass-action bar: after you tick a row checkbox, a floating bar appears at the bottom
of the window. The delete control in that bar is a red button, and it is rendered
unconditionally — if the action was never registered its label is simply empty, so you may see a
small unlabelled red button. **Judge on the label and on the request, not on the presence of a red
rectangle.** If you see an unlabelled red button, note it as cosmetic in the results table.

### W1-S1 — Leads: no mass delete for a role without `leads.delete`

Covers: `packages/Webkul/Admin/src/DataGrids/Lead/LeadDataGrid.php` — the row-level delete and
the mass-delete action are both registered only under `leads.delete`.

1. Log in as **QA No Delete**. Go to `/admin/leads`.
2. Tick the checkbox of any row. Look at the floating bar.
3. Look at a row's action menu (the three dots at the end of the row).
4. Then send the request by hand, from the console:

```js
(async () => {
  const t = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  const r = await fetch('/admin/leads/mass-destroy', {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': t },
    body: JSON.stringify({ indices: [1] }),
  });
  console.log('mass-destroy ->', r.status);
})();
```

Expected: no red button labelled **Mass Delete** in the floating bar; no **Delete** entry in
the row menu; the console prints `mass-destroy -> 401`.

Failure looks like: a red **Mass Delete** button that is labelled; a row menu with **Delete** in
it; the console printing `200` or `302` instead of `401`; or a record actually disappearing.

- [ ] Pass
- [ ] Fail

### W1-S2 — Quotes: same check

Covers: `packages/Webkul/Admin/src/DataGrids/Quote/QuoteDataGrid.php`.

As **QA No Delete** on `/admin/quotes`, tick a row and look at the bar, then run:

```js
(async () => {
  const t = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  const r = await fetch('/admin/quotes/mass-destroy', {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': t },
    body: JSON.stringify({ indices: [1] }),
  });
  console.log('quotes/mass-destroy ->', r.status);
})();
```

Expected: no labelled **Mass Delete** in the bar — the bar may still offer Update, Print and Mail,
which are different permissions and must keep working — and `quotes/mass-destroy -> 401`.

Failure looks like: a labelled Mass Delete button, a `200`/`302` status, or a quote missing
afterwards.

- [ ] Pass
- [ ] Fail

### W1-S3 — Products: same check

Covers: `packages/Webkul/Admin/src/DataGrids/Product/ProductDataGrid.php`.

As **QA No Delete** on `/admin/products`, tick a row, then run:

```js
(async () => {
  const t = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  const r = await fetch('/admin/products/mass-destroy', {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': t },
    body: JSON.stringify({ indices: [1] }),
  });
  console.log('products/mass-destroy ->', r.status);
})();
```

Expected: no labelled **Mass Delete**, no **Delete** in the row menu, status `401`.

Failure looks like: the button present and labelled, or a `200`/`302`, or a product gone.

- [ ] Pass
- [ ] Fail

### W1-S4 — Organizations: same check, different HTTP verb

Covers: `packages/Webkul/Admin/src/DataGrids/Contact/OrganizationDataGrid.php`.

**This route is a `PUT`, not a `POST`** (`packages/Webkul/Admin/src/Routes/Admin/contacts-routes.php`,
organizations group). A `POST` to it will look refused for the wrong reason, so use the verb.

As **QA No Delete** on `/admin/contacts/organizations`, tick a row, then run:

```js
(async () => {
  const t = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  const r = await fetch('/admin/contacts/organizations/mass-destroy', {
    method: 'PUT',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': t },
    body: JSON.stringify({ indices: [1] }),
  });
  console.log('organizations/mass-destroy ->', r.status);
})();
```

Expected: no labelled **Mass Delete** in the bar, no **Delete** in the row menu, `401`.

Failure looks like: the button present and labelled, a `200`/`302`, or an organization gone.

- [ ] Pass
- [ ] Fail

### W1-S5 — Activities: same check

Covers: `packages/Webkul/Admin/src/DataGrids/Activity/ActivityDataGrid.php`.

As **QA No Delete** on `/admin/activities`, tick a row, then run:

```js
(async () => {
  const t = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  const r = await fetch('/admin/activities/mass-destroy', {
    method: 'POST',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': t },
    body: JSON.stringify({ indices: [1] }),
  });
  console.log('activities/mass-destroy ->', r.status);
})();
```

Expected: no labelled **Mass Delete**, no row-level **Delete**, status `401`.

Failure looks like: the button present and labelled, a `200`/`302`, or an activity gone.

- [ ] Pass
- [ ] Fail

### W1-S6 — The refusal is the server's, not the browser's

Covers: that the hidden button is not the only control. This is the summary scenario: the five
requests above are the whole test, and this one records the verdict in one place.

Re-run the five console snippets from W1-S1 … W1-S5 while still logged in as **QA No Delete**
and write the five status codes here: leads ___ · quotes ___ · products ___ · organizations ___ ·
activities ___.

Expected: five times `401`. A `419` means the CSRF token was stale (reload the page and retry) —
that is not a pass, because it does not prove the authorisation check ran.

Failure looks like: any status in the 2xx/3xx range, on any of the five.

- [ ] Pass
- [ ] Fail

### W1-S7 — The delete action must still exist for a role that has the permission

Covers: the regression the fix could have caused. The button must not vanish for everyone.

1. Log in as **QA Delete** (separate profile).
2. `/admin/leads` → tick `QA disposable lead` → the bar shows a red **Mass Delete** button.
3. Click it, confirm the dialog. The row disappears and a success flash appears.
4. On `/admin/quotes`, `/admin/products`, `/admin/contacts/organizations` and
   `/admin/activities`, tick a row and confirm the **Mass Delete** button is **labelled** —
   do not actually delete those.
5. Still as **QA Delete**, open `/admin/leads`, open a remaining row's action menu and confirm
   **Delete** is present.

Expected: the button works, the flash says the record was deleted, and the row-level action is
present for this role as well.

Failure looks like: the button missing or unlabelled for a role that holds the permission (the
fix went too far); or a click that returns `401`/`403` for a role that should be allowed.

- [ ] Pass
- [ ] Fail

## B. File attribute uploads

The allowlist is `csv, doc, docx, ods, odt, pdf, ppt, pptx, rtf, txt, xls, xlsx, zip`
(`packages/Webkul/Admin/src/Http/Requests/AttributeForm.php`, `FILE_EXTENSIONS`). Anything the
browser renders or executes and anything the PHP handler would accept is absent **by
construction**, so a format nobody thought to ban cannot slip through. The rule reads the
extension Laravel derives from the file's **content**, not from the name you gave it.

A second gate sits where the file is written
(`packages/Webkul/Attribute/src/Repositories/AttributeValueRepository.php`,
`keepInertUpload`): an uploaded file whose stored extension is in `EXECUTABLE_EXTENSIONS` is
**deleted** and the attribute is stored with **no value**. That gate exists because some paths
(lead forms, imports) carry no form rule at all. `svg` is deliberately not in that list — it is
sanitised in place instead.

Use the `QA Document` attribute created in §3. Nothing here needs the no-delete user; use the
administrator.

### W1-S8 — The document types are accepted, stored and openable

Covers: the allowlist works for the formats it is meant to allow.

1. Leads → **Create Lead** → title `QA document uploads` → in **QA Document** attach
   `sample.pdf` → Save.
2. Open the lead. The attribute shows a download icon; click it.
3. Edit the lead, attach a real `.docx`, save, open it again.
4. Edit the lead, attach `sample.zip`, save.

Expected: all three save with no validation error. The download icon opens an **authenticated**
route, `/admin/settings/attributes/download?path=leads/<id>/<file>`, not a raw path — that route
only serves a path that an existing file or image attribute actually references. The PDF opens in
the browser's viewer, the DOCX opens in Word or a viewer, the ZIP downloads. Replacing the file
replaces the value and the old file is removed from disk.

Two URLs are worth knowing, because they are different surfaces. The UI link is the route above.
The file itself lives on the public disk at `storage/app/public/leads/<id>/<file>`, which the web
server also serves straight back at `https://<host>/storage/leads/<id>/<file>` — that direct URL
is the one that matters when the question is "could stored content be served as a page".

Failure looks like: a validation error on a pdf/docx/zip; an attribute that saves but shows no
download icon; a download that returns 404 or 403 for a file you just uploaded; a file that
downloads empty or corrupted.

- [ ] Pass
- [ ] Fail

### W1-S9 — A payload renamed to `.pdf` (content-derived check)

Covers: the claim that the extension is derived from content, not from the client's file name.
The honest answer is "observe what happens", not "it must be rejected".

1. Leads → Create Lead → title `QA disguised payload` → attach `disguised.pdf`
   (HTML content, `.pdf` name) → Save.
2. Record what happens, in one of these three forms:
   - **(a)** a validation error under **QA Document** and the lead is not saved,
   - **(b)** the lead saves but the attribute is empty and there is no download icon,
   - **(c)** the lead saves and the attribute shows a download icon — open it, **and** fetch the
     same file directly at `https://<host>/storage/leads/<id>/<file>` (take `<id>` from the
     record and the name from the download), then see what the browser does with it.

Expected: **(a)** or **(b)**. (a) is the form rule reading content; (b) is the repository gate
deleting it. Both mean no active content was kept.

Failure looks like: case (c) with the file rendering as an HTML page (the payload served as
same-origin content). If you get (c), do not delete the record — copy the exact stored path and
both URLs into the results table and treat it as a security finding, not a cosmetic one.

- [ ] Pass
- [ ] Fail

### W1-S10 — Executable-ish extensions are refused at the form

Covers: `html`, `htm`, `svg`, `js`, `php`, `phtml`, `exe`, `sh` are refused with a validation
error, not silently dropped. SVG is refused on purpose even though the code is capable of
sanitising it — a document has no need for it.

For each file, in turn: Leads → Create Lead (`QA rejected <name>`) → attach the file in
**QA Document** → Save.

| File | Expected |
| --- | --- |
| `payload.html` | refused — validation error under **QA Document** |
| `payload.svg` | refused |
| `payload.php` | refused |
| `payload.exe` | refused |

Then repeat once with `payload.html` renamed to `payload.htm`, and once with `payload.php`
renamed to `payload.phtml`, using `cp` to make the copies first.

Expected: in every case the form stays on the page with the error under the file field and the
lead is **not** saved. Nothing new appears under `storage/app/public/leads/` — the directory that
`/storage/leads/` serves.

Failure looks like: the lead saving with the payload attached; a link to the payload; or an error
that names a different field.

- [ ] Pass
- [ ] Fail

### W1-S11 — The import path, which has no form rule for a file attribute

Covers: the second gate. An import carries a **string** where a file would go, so the form rule
never runs. The value must not end up stored as a downloadable same-origin URL.

You need the six foreign keys a lead import requires. Run this in the instance's MySQL shell
(locally: `make db-shell`; on the live host: the shell documented in `deploy/README.md`):

```sql
SELECT (SELECT MIN(id) FROM users)                AS user_id,
       (SELECT MIN(id) FROM persons)              AS person_id,
       (SELECT MIN(id) FROM lead_sources)         AS source_id,
       (SELECT MIN(id) FROM lead_types)           AS type_id,
       (SELECT MIN(id) FROM lead_pipelines)       AS pipeline_id,
       (SELECT MIN(id) FROM lead_pipeline_stages) AS stage_id;
```

Put the six numbers into this CSV (one header line, one data line) and save it as
`qa-import.csv`:

```csv
title,user_id,person_id,lead_source_id,lead_type_id,lead_pipeline_id,lead_pipeline_stage_id,qa_document
QA import payload,<user_id>,<person_id>,<source_id>,<type_id>,<pipeline_id>,<stage_id>,https://example.invalid/payload.html
```

Then: Admin → **Settings → Data Transfer → Imports → Create Import** — Type `Leads`, Action
`Create`, upload `qa-import.csv`, comma separator — Save, then **Start** on the import row.

Expected: the import completes and the lead `QA import payload` exists. Its **QA Document**
attribute is **empty**: no download icon, no value. Whether the file attribute is dropped at
mapping time or stored as no value, the pass condition is that nothing is stored and no
same-origin URL results.

Failure looks like: the attribute holding `https://example.invalid/payload.html` or any
`/storage/...` value that a browser would open as content; or a file appearing under
`storage/app/public/leads/` that came from the import.

- [ ] Pass
- [ ] Fail

### W1-S12 — The web-form path

Covers: the same second gate on the unauthenticated route. A web form can carry a file
attribute, and its own rule is a **different, narrower** allowlist
(`packages/Webkul/WebForm/src/Http/Requests/WebForm.php`:
`bmp,gif,jpeg,jpg,png,svg,webp,pdf,doc,docx,xls,xlsx,csv,txt`), so two behaviours have to be
observed separately.

1. Admin → **Settings → Web Forms** → create a form. Add the **QA Document** attribute (and a
   contact email field, which the submission requires). Save and note the `form_id`. The form
   caps uploads at `UPLOAD_WEB_FORM_MAX_SIZE_KB` (2048 KB unless the instance changed it), so keep
   the test files small.
2. Open the public form page — the same page the admin **Preview** button opens:
   `https://<host>/web-forms/forms/<form_id>/form.html`
3. Submit the form with `payload.html` attached. Record the result.
4. Submit a second time with `sample.pdf` attached, to prove the field works at all.
5. If you can craft it, submit `payload.php` and `payload.svg` the same way and record each.

Expected: `payload.html` is **not** stored — either the form returns a validation error (it is
absent from the web-form allowlist) or the attribute is empty. The `.pdf` submission creates a
lead with a working document link. `payload.php` is refused the same way. **`payload.svg` may be
accepted here**: it is in the web-form allowlist and the repository sanitises SVG in place rather
than deleting it — record what you observe, and fetch the stored file
(`https://<host>/storage/leads/<id>/<file>`, or the download icon on the lead the form created).
No `<script>` tag in what comes back is the pass condition for that case.

Failure looks like: any submission storing an `.html` or `.php` file; or a sanitised-by-claim SVG
that still carries script when fetched from its `/storage/...` URL.

- [ ] Pass
- [ ] Fail

### W1-S13 — The image attribute is unchanged

Covers: the image rule was not touched. It must still accept jpg/png/webp and still reject an
HTML payload under an image name.

1. Leads → Create Lead → title `QA image attribute` → attach a real `.jpg` in **QA Image** →
   Save (also worth doing for `.png` and `.webp`).
2. Create another lead and attach `payload.html` renamed to `payload.png`
   (`cp payload.html payload.png`) in **QA Image** → Save.

Expected: the real images save and display as images. The renamed HTML is **refused** with a
validation error under **QA Image**, or saved with no value — but never stored as an openable
file.

Failure looks like: the HTML file accepted and stored under `/storage/...`; or a legitimate
`.webp` refused.

- [ ] Pass
- [ ] Fail

## C. Vue mounts on `DOMContentLoaded`, not on `load`

The symptom this fixes: on a slow connection Vue mounted after the last image finished, re-bound
the form inputs, and wiped whatever had been typed in the meantime. Both layouts now mount on
`DOMContentLoaded`, with a branch for a document whose `readyState` is no longer `loading`
(the back/forward cache case).

### W1-S14 — Typing on the login page before the page settles

Covers: `packages/Sijilna/Brand/resources/views/admin/components/layouts/anonymous.blade.php`.

1. Open DevTools → **Network** → Throttling → **Slow 3G**. Keep the console visible.
2. Log out, then hard-reload `/admin/login` (`Ctrl+Shift+R`), with throttling still on.
3. The instant the email and password fields are visible — do not wait for the spinner, the
   fonts or the images — type an email and a password and submit immediately.
4. Watch the two fields as the page finishes loading.

Expected: the text you typed stays in the fields while the page settles, and the submit carries
it (you either land in the dashboard, or the form comes back with a credentials error — both
prove the values arrived). No red error in the console.

Failure looks like: the fields emptying themselves after you type; a submit answered with
"the email field is required" although you typed an email; a console error during mount.

- [ ] Pass
- [ ] Fail

### W1-S15 — Typing on a heavy admin page before it settles

Covers: `packages/Sijilna/Brand/resources/views/admin/components/layouts/index.blade.php`.

1. Keep throttling on Slow 3G. Log in and open `/admin/leads` (with the `QA Document`
   attribute present, this page carries more markup than the login page).
2. While it is still loading, click into a filter field and type `QA`.
3. Wait for the page to settle, then look at the field.
4. Repeat once inside the lead **Create** form: type a title during load and let it settle.

Expected: the typed text survives in both places and the filter actually applies. No console
error.

Failure looks like: the field empty after settling, the typed text appearing and then vanishing,
or a console error.

- [ ] Pass
- [ ] Fail

### W1-S16 — Back button (the `readyState` branch)

Covers: a page restored from the back/forward cache arrives already parsed, so the
`DOMContentLoaded` listener would never fire — hence the `readyState` branch. The failure this
guards against is a page that renders but never mounts: every control inert, no error.

1. On `/admin/leads`, click a row to open a lead.
2. Press the browser **Back** button.
3. Without reloading, interact: switch a tab, open a dropdown, sort a grid column, type in the
   search box.

Expected: the restored page is interactive immediately — filters, dropdowns and the row menu all
respond. No console error.

Failure looks like: the page looking correct but nothing responding to clicks (dead Vue app);
or a console error on the restored page. Reloading "fixes" it, which is exactly the trap — reload
is not the test.

- [ ] Pass
- [ ] Fail

## D. Security headers and the `Secure` cookie

The headers are set on the nginx layer (`deploy/docker/nginx.conf`) and repeated inside every
`location` block that sets `Cache-Control`, because **nginx replaces the whole inherited
`add_header` set as soon as a location declares one** — that inheritance loss was the actual bug.
`/storage/...` and `/build/...` are the two locations where it showed.

There is deliberately **no `Content-Security-Policy`** header.

### W1-S17 — The five headers are present on `/admin/login`

Live only.

```bash
curl -sI https://crm.mohamedlabs.com/admin/login \
  | grep -iE '^(x-content-type-options|x-frame-options|referrer-policy|permissions-policy|strict-transport-security):'
```

Expected, all five lines:

```
x-frame-options: SAMEORIGIN
x-content-type-options: nosniff
referrer-policy: strict-origin-when-cross-origin
permissions-policy: camera=(), microphone=(), geolocation=()
strict-transport-security: max-age=15552000
```

And this must print `0` — the missing CSP is intentional:

```bash
curl -sI https://crm.mohamedlabs.com/admin/login | grep -ci '^content-security-policy:'
```

Failure looks like: fewer than five header lines; a `Content-Security-Policy` present (which
would break the admin UI eventually, and is not what was agreed); or a header value that differs
from the strings above.

If it fails and the checkout says otherwise, check in this order. First, what the container is
actually reading: `deploy/docker/nginx.conf` is bind-mounted **read-only**, so the running nginx
serves whatever is in that file on the host —
`sudo sed -n '70,80p' /opt/<project>/deploy/docker/nginx.conf` must show the five `add_header`
lines. Second, make nginx read it again; a reload is enough, because the mount resolves on start
and nginx re-reads its configuration:

```bash
sudo docker compose -p <project> -f deploy/docker-compose.yml exec web nginx -s reload
```

A recreate also works:

```bash
sudo docker compose -p <project> -f deploy/docker-compose.yml \
  up -d --force-recreate --no-deps web
```

(For the **app** container the rule is different and stricter: `env_file` is read only at
creation, so an `.env` change needs a recreate and never a `restart` — see W1-S19.)

- [ ] Pass
- [ ] Fail

### W1-S18 — The headers survive on 404, `/storage/...` and `/build/...`

Covers: the inheritance bug precisely. A location that sets `Cache-Control` loses the parent
`add_header` set unless it repeats it.

```bash
for url in \
  https://crm.mohamedlabs.com/storage/does-not-exist.txt \
  https://crm.mohamedlabs.com/build/does-not-exist.js \
  https://crm.mohamedlabs.com/qa-404-probe ; do
  echo "== $url"
  curl -sI "$url" | grep -iE '^(HTTP/|x-content-type-options|x-frame-options|referrer-policy|permissions-policy|strict-transport-security):'
done
```

Expected: each of the three blocks shows the same five headers as W1-S17. The status codes will
differ (`/storage/...` and `/build/...` answer 404, `/qa-404-probe` answers whatever the
application does for an unknown path) — identical `x-frame-options` and `x-content-type-options`
lines in all three blocks is what matters.

Failure looks like: a block with a `cache-control`-style header but no `x-content-type-options`
or `x-frame-options`. That is the exact regression this scenario exists for.

- [ ] Pass
- [ ] Fail

### W1-S19 — The session cookie is `Secure` on live, and local login still works

Covers: `SESSION_SECURE_COOKIE`. `true` on the live instance, `false` on a local checkout — and a
local checkout that cannot log in means the flag is wrong somewhere, not that the browser is odd.

Live, the cookie attributes:

```bash
curl -sI https://crm.mohamedlabs.com/admin/login | grep -ic '; secure'
```

Expected: `2` — both cookies the login page sets (`XSRF-TOKEN` and the session cookie) carry
`Secure`. In the browser the same thing is visible at **DevTools → Application → Cookies →
https://crm.mohamedlabs.com**: the session cookie row shows a tick under `Secure`, and also
`HttpOnly` and `SameSite=Lax`.

Local:

```bash
make serve
curl -sI http://127.0.0.1:8000/admin/login | grep -ic '; secure'
```

Expected locally: `0`, and a real login at `http://127.0.0.1:8000/admin/login` succeeds. If the
local login bounces back to the login page, the local `SESSION_SECURE_COOKIE` is `true`: the
browser refuses to store a `Secure` cookie over plain HTTP, so the session never sticks.

> **The live instance needs this in order, and only this order.** `SESSION_SECURE_COOKIE=true`
> must be in the live `/opt/<project>/app/.env`, and the app container must then be **recreated**:
>
> ```bash
> sudo docker compose -p <project> -f deploy/docker-compose.yml \
>   up -d --force-recreate --no-deps app
> ```
>
> **Never `restart` for this.** `env_file` is read only when a container is created, so a
> `restart` keeps the old environment and the edit appears to do nothing.

Expected overall: live cookies `Secure`, local cookies not `Secure`, both logins working.

Failure looks like: a live session cookie without `Secure` (then `SESSION_SECURE_COOKIE` is not
in the live `.env`, or the container was restarted rather than recreated — check
`sudo docker inspect <project>-app --format '{{range .Config.Env}}{{println .}}{{end}}' | grep SESSION_SECURE_COOKIE`
and never quote the unfiltered output); or a local login that does not stick.

- [ ] Pass
- [ ] Fail

## E. The secrets runbook

Documentation only. Nothing in this section changes the instance.

### W1-S20 — Every relative link in the runbook resolves

Covers: `docs/ops/secrets-handling.md` links to its companions, and a dead link in a document
meant to be read during an incident is a real defect.

```bash
cd docs/ops
grep -oE '\]\([^)]*\)' secrets-handling.md | sed 's/^](//; s/)$//' | sort -u
```

Open each of the seven targets. From `docs/ops/` they are: `ci-workflows.md`,
`decisions-to-confirm.md`, `incident-response.md`, `security-review.md`, `support-sla.md`,
`../legal/dpa.md`, `../legal/data-residency.md`.

Expected: all seven exist and each lands on the section the sentence refers to (§3, §4, §6, §7,
§9 or the finding numbers F8/F15/F16/F17). Also confirm `docs/ops/secrets-handling.md` covers
each secret as its own row — `APP_KEY`, both database passwords, SMTP, IMAP, the inbound token,
the Google pair, the administrator password — and states a blast radius for each in §3b.

Failure looks like: a link that 404s or points at the wrong file; a section reference that does
not exist in the target; a secret named in §1 that §3b has no blast-radius row for.

- [ ] Pass
- [ ] Fail

### W1-S21 — Walk §6's 02:00 checklist and list what you cannot do

Covers: the runbook's own honesty. §6 is a one-column do-this-now list, and several of its steps
end in a **DECISION** that is still open in
[../ops/decisions-to-confirm.md](../ops/decisions-to-confirm.md) (D27–D34). **The list of steps
you cannot execute is the test output**, not a defect in the document.

Work down `secrets-handling.md` §6 (both the "think a secret is out" list and the "rotating on
purpose" list) and for each step record one of:

- **executable** — you know exactly what to run and where;
- **blocked by D<n>** — name the decision, and say in one line what you would need to know
  (for example: where the off-host copy of each instance's `.env` lives — D27; whether `APP_KEY`
  is ever rotated — D28; where the secret values are stored at all — D33; which `.env` mode and
  ownership is authoritative — D34);
- **not executable from this checkout** — the value lives on the host or at a provider.

Also check that §6 step 4's ordering is one you would actually follow (database password → SMTP
→ IMAP → inbound token → administrator passwords → `APP_KEY` last) and that §3c's warning is
unmissable: **there is no re-encryption command in this repository, so rotating `APP_KEY` destroys
every encrypted column.**

Expected: every step is either executable or blocked by a named decision. A step that is blocked
but names no decision is the failure — that is an instruction that reads actionable and is not.

**Do not execute the rotation steps as a test.** W1-S21 is reading and recording only.

- [ ] Pass
- [ ] Fail

## Results

Fill this in as you go. `pass` / `fail` / `not run`, plus one line of what you saw.

| ID | Result | Notes |
| --- | --- | --- |
| W1-S1 | | |
| W1-S2 | | |
| W1-S3 | | |
| W1-S4 | | |
| W1-S5 | | |
| W1-S6 | | |
| W1-S7 | | |
| W1-S8 | | |
| W1-S9 | | |
| W1-S10 | | |
| W1-S11 | | |
| W1-S12 | | |
| W1-S13 | | |
| W1-S14 | | |
| W1-S15 | | |
| W1-S16 | | |
| W1-S17 | | |
| W1-S18 | | |
| W1-S19 | | |
| W1-S20 | | |
| W1-S21 | | |

## What a failure means, and who to tell

| Failed | What it means | Do this |
| --- | --- | --- |
| W1-S1 … W1-S6 | A role without the delete permission can delete records. The server refusal is gone, not just the button. Treat as a security defect, not a UI bug | Tell the vendor owner (Mohamed) the same day. Do not hand the instance to a client until it is closed. If records were destroyed, start [../ops/incident-response.md](../ops/incident-response.md) §3 (preserve first) |
| W1-S7 | The permission check is inverted or too strict: nobody can mass-delete any more | Routine defect. Operators will work around it by deleting one row at a time, so it can wait for the next fix — but it is a regression |
| W1-S8, W1-S13 | A legitimate document or image is refused, or does not open | Routine defect. Note the format and the exact error text |
| W1-S9, W1-S10, W1-S11, W1-S12 | Active content was stored and is served from our own origin | Security defect: a stored `.html` or `.php` under `/storage/...` is same-origin content. Delete the record, keep the URL in the results table, and treat it as an incident (`../ops/incident-response.md` §3 — preserve, do not clean) |
| W1-S14, W1-S15, W1-S16 | A form loses what was typed, or a page is restored and never mounts | Usability defect. Record the page, the browser and the throttle setting; it is reproducible only under load, so the notes matter more than the verdict |
| W1-S17, W1-S18 | The instance is served without the agreed headers — a clickjacking and MIME-sniffing exposure on a public login page | Operator action first: recreate the `web` container (W1-S17). Only if that does not fix it, report it |
| W1-S19 | Live cookies are not `Secure` (they can travel over plain HTTP), or local development cannot log in | Fix the flag in the environment that is wrong, then **recreate** the container. Never `restart` |
| W1-S20, W1-S21 | The runbook has a dead link, or a step nobody can execute | Documentation defect. The blocked-step list from W1-S21 is the input to closing D27–D34 — hand it over rather than editing the decisions yourself |
