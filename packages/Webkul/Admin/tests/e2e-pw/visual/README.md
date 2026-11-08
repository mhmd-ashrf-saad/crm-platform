# Visual-regression baselines

Photographs of the current admin UI, taken before any rebrand change lands, so that
"the design changed on purpose" and "the design broke" stop being opinions.

This directory holds the harness (`surfaces.ts`, `visual.spec.ts`, `inventory.spec.ts`,
`check-coverage.mjs`), the committed baselines (`baselines/`), and the coverage ledger
(`inventory.json`, `INVENTORY.md` — both generated, both committed).

Bead: `crm-7qu.2`. Parent: `crm-7qu` (Rebrand Sijilna).

## The three commands

```bash
npm run vr:baseline   # regenerate every baseline (writes files; rewrites the ones that moved)
npm run vr:compare    # compare against the baselines; exit 0 only when nothing differs
npm run vr:coverage   # no browser: every declared cell exists on disk as a real PNG
```

`vr:baseline` is `playwright test … --update-snapshots`; `vr:compare` is the same command without
that flag. It runs from `packages/Webkul/Admin` — the package that owns the suite's Playwright
install, which is also how CI invokes the e2e suite. Running it from the repository root loads a
second copy of `@playwright/test` and fails with "test.describe() was called here".

Useful variations:

```bash
# one surface, or a handful (test titles are "<surface> @ <width> <mode>")
npm run vr:compare -- -g "login|forget-password"
npm run vr:baseline -- -g "dashboard"
```

The run needs a signed-in administrator. Credentials come from the suite's own configuration —
`E2E_ADMIN_EMAIL` / `E2E_ADMIN_PASSWORD` in the environment, defaulting to the installer's
`admin@example.com` / `admin123` (`fixtures/env.ts`). The instance this baseline was captured from
does not have that account, so its own administrator's credentials were exported for the run; they
are never written into the repository.

## The matrix

3 widths (360, 768, 1440 px) × 2 colour modes (light, dark) × 13 surfaces, which is 78 cells at the
full cross-product. **66 baselines exist; 6 are declared and skipped** (see "Known gaps").

| Surface | What it is |
| ------- | ---------- |
| `login`, `forget-password`, `reset-password` | the anonymous authentication pages |
| `error-404` | the framework error page |
| `dashboard` | cards and charts, over a pinned 2026-10-01…2026-10-31 window |
| `header`, `sidebar`, `mobile-sidebar` | the chrome: header crop, desktop sidebar crop (1440 only — it is `max-lg:hidden`), mobile sidebar drawer (360/768 only) |
| `datagrid-listing`, `record-detail`, `drawer` | a listing, a record page, the filter drawer |
| `public-web-form` | the public form, at its own `/web-forms/forms/{id}/form.html` URL |
| `modal` | the mega-search modal — **skipped**, see below |

`inventory.json` and `INVENTORY.md` are the authoritative list; both are written from
`surfaces.ts` by `inventory.spec.ts`, so the ledger cannot drift from the captures.

## Baseline storage: committed images, in this directory

The suite's own artefacts are gitignored (`/playwright-report`, `/test-results`, `/.state`) because
they are *outputs of a run*. Baselines are not that: they are **inputs** to every later comparison,
in the same class as `data/images/*.webp` and `data/data-transfer/*.csv`, which this suite already
tracks. So:

- **The PNGs are committed** under `visual/baselines/`, named
  `<locale>-<surface>-<width>-<mode>.png`. No `.gitignore` exception was needed — nothing in the
  repository's ignore files covers this path — and the whole set is 5.5 MB.
- **Rejected: storing baselines as a CI artefact.** An artefact expires, cannot be reviewed in a
  pull request, and refreshing it means running a job rather than editing a file. A rebrand PR that
  changes 60 screenshots should show those images in the diff, where a reviewer can see the recipe.
- **The locale is part of the file name** so an Arabic and an English baseline can never overwrite
  each other. This set is `ar`.

The name is flat (`ar-dashboard-1440-light.png`, not `ar/dashboard/…`) because Playwright sanitises
the snapshot name into a file name, replacing `/` with `-`, and `snapshotPathTemplate` has no token
that can inject the surface into the directory. The platform suffix (`-linux`) is deliberately
dropped from the template: this set is captured and compared on Linux, and if that ever stops being
true it should fail rather than silently look in an empty directory.

## Colour mode, locale, and what is asserted

- **Dark is a cookie, not a click.** `darkMode: 'class'` and the class is server-rendered from the
  `dark_mode` cookie (`components/layouts/index.blade.php:4`). Setting the cookie and loading the
  page produces exactly the document the header's `v-dark` toggle produces, without depending on a
  click landing on a hydrated component.
- **The locale is asserted, never pinned.** The e2e suite pins the admin UI language by writing
  `core_config` in `globalSetup` — a database write this harness must not make. Instead every
  capture reads `<html lang>` and fails unless it is `VR_LOCALE` (default `ar`, which is what this
  instance serves and the product's primary language). A run against an instance configured for
  another language fails loudly rather than baselining the wrong language.
- **Dark is asserted on every surface that has it.** A cell labelled `dark` that is actually light
  would be a duplicate baseline nobody notices; the harness checks `documentElement.classList`
  against the mode before capturing.

## Determinism: what it took

Two consecutive `vr:compare` runs on unchanged code: **67 passed, 6 skipped, exit 0**, twice.
Getting there needed four things, each measured rather than guessed:

1. **No `fullPage: true` on page surfaces.** The viewport is grown until the document fits, then the
   viewport is captured. Three consecutive screenshots of the same settled dashboard at 360px:

   ```text
   fullPage: f2b165aa a11ecfa6 36189f94      <- different every time, within one session
   viewport: 1cc62c56 1cc62c56 1cc62c56      <- identical, and identical across runs
   ```

   `fullPage` resizes the viewport for the shot and restores it; ApexCharts re-renders on that
   resize, animation and all, so the file held a frame of a re-drawn chart.
2. **A stability gate**: two consecutive screenshots must be byte-identical before the frame is
   handed to `toHaveScreenshot`, with `caret: "hide"` and `animations: "disabled"` in *both* the gate
   and the assertion.
3. **The randomised regions are masked.** `components/avatar/index.blade.php` picks its colours with
   `Math.random()`, so the same person is yellow on one load and blue on the next (measured: 3,210
   differing pixels in one 36px column of the dashboard's "top persons", and 1,065 in the record
   page's avatar). Those avatars are masked; everything else on those pages is still compared.
   **This is a product defect worth fixing** — derive the colour from the name — and doing so is
   follow-up work, not part of this bead.
4. **A pinned dashboard window.** The dashboard's date range defaults to "the last 30 days", so
   without `?start=&end=` every capture is a photograph of today and the baseline would fail the
   next morning. The window is pinned to 2026-10-01…2026-10-31 through the same query parameters the
   product's own date filter uses.

The comparison tolerance is `threshold: 0` and `maxDiffPixels: 40` — 0.003% of a 1440px dashboard.
That slack absorbs the one residual instability measured after all of the above (15-25 pixels: two
card borders sitting one pixel lower in one run than the other, i.e. chart height rounding). For
scale: the deliberate one-pixel change used for the sensitivity proof moves **11,962** pixels.

## Sensitivity: proof the gate can see change

`VR_SENSITIVITY=<surface>` applies a deliberate 1px padding to `#app` on one surface only,
immediately before the capture. Against the committed baselines:

```text
[sensitivity] applying a deliberate 1px padding to #app on "login"
  ✘ login @ 360 light   Expected an image 360px by 900px, received 360px by 902px.
                        11962 pixels (ratio 0.04 of all image pixels) are different.
  … all six login cells fail …
  6 failed
```

Removing the perturbation and re-running the same command: `6 passed`. A gate that cannot fail is
not a gate, and this is the measurement that says this one can.

The perturbation is applied to the rendered document rather than to a Blade view because the
instance being photographed is served from the main checkout, where other work edits
`packages/Webkul/Admin/src/**` concurrently: a temporary edit there, reverted seconds later, would
race that writer.

## CI wiring plan (a plan, not new workflow YAML)

`admin_playwright_tests.yml` runs the e2e suite in **15 shards**. This harness is deliberately a
separate Playwright config (`playwright.visual.config.ts`) with its own `testDir`, so the shard
command (`--config=tests/e2e-pw/playwright.config.ts`) does not see it at all. That is the whole
reason for the separate config: nothing below requires editing a shard.

1. **Its own job, not a 16th shard.** Screenshot comparison is serial, stateful and slow (about 4-5
   minutes for 66 cells against one `php artisan serve`); spreading it across shards would multiply
   the install cost by fifteen and let two shards fight over one single-threaded server.
2. **Runs on pull requests and nightly, not on every push.** PR-only catches the change before it
   merges; a nightly run catches drift the PR did not have (a dependency, a font, a browser build).
   Pushes to `main` that already passed the PR add nothing but minutes.
3. **Baselines come from the repository, not from the CI cache.** The job checks out the branch,
   so the committed PNGs are exactly the ones under review, and a PR that re-baselines shows those
   images in its diff.
4. **Refresh is a deliberate act**: `npm run vr:baseline` on a developer machine, committed with the
   change that justifies it. The job never passes `--update-snapshots`; a snapshot that appears by
   itself is not a gate.
5. **Shard budget impact: zero on the existing 15 shards** (separate config, nothing added to
   `tests/`). The new job costs one checkout, one composer install, one Krayin install, one npm
   install and one browser download — the same fixed cost as a single shard — plus ~5 minutes of
   comparison, and it needs **its own database seeded with the demo dataset** (`DemoSeeder`), because
   the baselines contain real records: a fresh empty install renders empty grids and would fail
   every cell. Until that seeding exists in CI, the job must be PR-only and advisory, not blocking;
   the alternative — baselines of an empty database — would gate nothing that matters.
6. **Fail closed.** A job timeout (say 20 minutes) and a failure on any differing cell; no
   auto-retry, because a flaky screenshot is a real finding about either the product or the harness.

Implementing any of this is follow-up work; this bead records the plan.

## Email templates and PDFs are not screenshots

The rebrand touches mail templates and the dashboard's PDF export, and neither is a browser page. So
they are not silently missing from the matrix; they are baselined differently, by rendering and
diffing their **HTML**:

- An email template is rendered through its own preview (the mail-template editor's server-rendered
  HTML) and the resulting markup is normalised — attribute order, absolute URLs and the `csrf` token
  stripped — and compared as a text diff. Its pixels depend on the mail client, which is not
  reproducible in this harness.
- A PDF export is compared as its extracted text plus its page/box geometry, not as rasterised
  pages: PDF rendering differs between viewers and font substitution would make every comparison
  noise.

Both belong to the bead that restyles them; this note exists so that "no email baseline" is a
recorded decision rather than an omission.

## Known gaps, recorded

- **The mega-search modal is declared but skipped.** Its chrome is stable, but its results list
  re-renders on a debounced keystroke and its rows shift between loads (measured: 1,800-4,200
  differing pixels over unchanged code). Typing a fixed query first reduced the spread without
  removing it, and masking each avatar left the rows free to slide under the masks. The six cells are
  counted as skipped by `vr:coverage` and printed with this reason by the runner.
- **No dark mode on the public pages.** The anonymous layout and the web-form layout never put the
  `dark` class on `<html>` and never read the `dark_mode` cookie, so `login`, `forget-password`,
  `reset-password` and `public-web-form` have byte-identical light and dark baselines. They are
  captured in both modes so that the day dark support lands, the comparison shows it.
  `error-404` is the exception: it reads the cookie only to swap the logo, so its two baselines
  differ by the mark and not by the theme.
- **Arabic only.** This set is `ar` (which is also RTL). English baselines do not exist; adding them
  is a `VR_LOCALE=en` run into a second locale prefix once an instance or a pin provides English.
- **One dataset.** The baselines contain the seeded demo records (27 leads, 19 persons, 2 web
  forms). Regenerating against a different database produces a different, equally valid set — which
  is why the refresh is a deliberate, reviewed commit and not a CI step.
