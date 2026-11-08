import type { Page } from "@playwright/test";

/**
 * The visual-regression baseline matrix: which surfaces are photographed, at which widths, in which
 * colour mode.
 *
 * This file is the single source of truth for the matrix. `visual.spec.ts` captures from it, and
 * `inventory.spec.ts` writes the coverage ledger from it, so a surface cannot be photographed and
 * then forgotten in the inventory (or the other way round).
 *
 * Three decisions are baked in here, each because the alternative was wrong:
 *
 * 1. **The locale is asserted, never pinned.** `fixtures/pinUiLocale.ts` — the e2e suite's own pin —
 *    writes `core_config` before the run and restores it afterwards. That is a database write this
 *    harness must not make (`crm-7qu.2`: "must not mutate data beyond a login"), so the visual
 *    config deliberately omits the pin. What replaces it is an assertion: every capture reads
 *    `<html lang>` and fails if it is not `VR_LOCALE`. A baseline captured in one language and
 *    compared against another is not a visual regression, it is a mistake, and it has to be loud.
 *
 *    The default is `ar` because that is what this instance serves — `core_config
 *    general.general.locale_settings.locale` is `ar` and `.env` carries `APP_LOCALE=ar`. It is also
 *    the product's primary language: Sijilna is Arabic-first, and an English baseline of an
 *    Arabic-first CRM would hide half of what a rebrand changes (direction, wrapping, icon flips).
 *
 * 2. **Dark mode is a cookie, not a click.** `darkMode: 'class'` is enabled, and the class is
 *    server-rendered from the `dark_mode` cookie (`components/layouts/index.blade.php:4`), which the
 *    header's `v-dark` component writes on toggle. Setting the cookie and reloading therefore
 *    produces the same document the product produces, without depending on a click landing on a
 *    hydrated component — and it works identically for surfaces that have no toggle on screen.
 *
 * 3. **Widths a surface does not have are declared, not faked.** The desktop sidebar is
 *    `max-lg:hidden`: it does not exist at 360 or 768, and screenshotting it there is impossible.
 *    Rather than inventing a substitute and calling it the sidebar, each surface declares the widths
 *    it is captured at, and `check-coverage.mjs` prints that declaration next to the files on disk
 *    so the ragged edges are visible instead of silent.
 */

export const MODES = ["light", "dark"] as const;

export type VrMode = (typeof MODES)[number];

export const WIDTHS = [360, 768, 1440] as const;

export type VrWidth = (typeof WIDTHS)[number];

/**
 * One viewport height for every width.
 *
 * Full-page captures do not depend on it, and the two element captures (header, sidebar) are
 * cropped to their own box. A single height keeps the browser context identical across cells, which
 * is one less variable between two runs that have to be pixel-identical.
 */
export const HEIGHT = 900;

export const VR_LOCALE = process.env.VR_LOCALE ?? "ar";

/** The width below which the desktop sidebar is hidden (`max-lg` on `#admin-sidebar`). */
export const DESKTOP_SIDEBAR_FROM: VrWidth = 1440;

export type VrSurface = {
    /** Stable identifier; also the directory and the file-name stem of every baseline. */
    slug: string;

    /** What the surface is, in words, for the inventory. */
    label: string;

    /** `page` = full-page screenshot, `element` = a crop of one element. */
    kind: "page" | "element";

    /** Required when `kind` is `element`. */
    selector?: string;

    /** The path opened before `prepare` runs. Always instance-independent. */
    path: string;

    /** Captured in a context with no session, because the page is what an anonymous visitor sees. */
    anonymous: boolean;

    /** The widths this surface exists at; see decision 3 above. */
    widths: readonly VrWidth[];

    /** Extra quiet time after the network settles, for surfaces with scripted motion. */
    settleMs?: number;

    /**
     * Interaction that has to happen before the capture, and that may navigate the page itself.
     *
     * A click-through rather than a hardcoded id: record ids differ between this instance and a
     * fresh one, and a baseline that only matches one database is not a baseline.
     */
    prepare?: (page: Page) => Promise<void>;

    /**
     * Whether the `dark` class lands on `<html>` for this surface, so the mode can be asserted.
     *
     * False for the anonymous pages, and that is a finding, not a convenience: the login, forgotten
     * and reset-password views render inside `components/layouts/anonymous.blade.php`, whose `<html>`
     * tag carries no `dark` class and never reads the cookie. Their `dark:` utilities exist (three
     * in `sessions/login.blade.php`) but no ancestor ever gets the class, so both modes capture the
     * identical light document. Recording that here means the coverage report states it rather than
     * presenting two identical images as two tested modes.
     */
    honoursDark: boolean;

    /**
     * Set when `dark_mode` changes the pixels without ever setting the `dark` class — the case where
     * two different-looking baselines would otherwise be reported as an identical document.
     */
    darkNote?: string;

    /**
     * Why this surface is declared but not captured.
     *
     * A skipped cell is skipped out loud: the reason is printed by the runner, written into
     * `INVENTORY.md`, and counted separately by `check-coverage.mjs`. Declaring a surface and then
     * omitting it silently is the one thing this file exists to prevent.
     */
    skip?: string;
};

/**
 * A reset-password link, as Laravel issues it: `/admin/reset-password/{token}`.
 *
 * The token only has to be present for the route to match — `ResetPasswordController@create` renders
 * the form and the token is submitted back, it is not validated until submission. A fixed literal
 * therefore makes the URL deterministic, which is exactly what a baseline needs.
 */
const RESET_TOKEN = "c3f1d4e5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1";

/**
 * The filter drawer's toggle.
 *
 * Structural rather than textual: the button's label is translated, and its `bg-sky-100` in light
 * mode is the design-system colour the rebrand is going to change. Matching the class here is a
 * deliberate exception — this harness photographs the classes, so a locator that survives a palette
 * change is worth more than one that survives a class change. It is scoped to the datagrid toolbar
 * and takes the first match, which is the filter control on every listing rendered by
 * `components/datagrid/toolbar/filter.blade.php`.
 */
const FILTER_DRAWER_TOGGLE = 'div[class*="bg-sky-100"][class*="cursor-pointer"]';

/** The desktop sidebar, present at `lg` and above only. */
const DESKTOP_SIDEBAR = "#admin-sidebar";

/** The mobile sidebar's toggle, `lg:hidden`, in the admin header. */
const MOBILE_SIDEBAR_TOGGLE = "header i.icon-menu";

/**
 * The dashboard's date window, pinned through the query string.
 *
 * Without it every dashboard capture is a photograph of *today*: the range control renders
 * `8 October 2026 – 8 November 2026`, the "leads over time" chart draws the last N days, and the
 * report figures are computed over that window. A baseline captured on one day would therefore fail
 * the next morning with no code change anywhere — the worst possible failure for a gate, because it
 * teaches people to re-baseline on reflex.
 *
 * `AbstractReporting::setStartDate()` reads `request()->date('start')` and `request()->date('end')`,
 * so the window is pinned the same way the product's own date filter pins it. The dates are fixed
 * literals in the past: `setEndDate()` refuses a future end date and would silently clamp it to
 * `now()`, which would put today back into the baseline.
 */
const DASHBOARD_WINDOW = { start: "2026-10-01", end: "2026-10-31" };

const DASHBOARD_PATH = `/admin/dashboard?start=${ DASHBOARD_WINDOW.start }&end=${ DASHBOARD_WINDOW.end }`;

/**
 * The drawer's own panel: `z-[10003]` **and** the `m-3` inset that only the panel carries.
 *
 * `z-[10003]` alone is not enough. The dashboard also renders a toast container with that class,
 * first in document order, and it is `hidden` while there is nothing to say. `.first()` therefore
 * matched an invisible element and the drawer's `waitFor(visible)` burned its full timeout on a
 * drawer that had actually opened — measured, four cells, 13-15s each.
 */
const DRAWER_PANEL = 'div[class*="z-[10003]"][class*="m-3"]';

/**
 * The term typed into the mega-search modal, and why it is a shared substring rather than a name.
 *
 * Every demo person and organization carries an address on `example.com`, so this matches several
 * records on any seeded instance while staying a single fixed string — the point is a repeatable
 * panel, not a clever one.
 */
const MEGA_SEARCH_TERM = "example";

/**
 * Wait for the first *visible* match of a selector.
 *
 * `locator.filter({ visible: true })` rather than `:visible` in the selector: the admin's class names
 * contain bracketed arbitrary values (`z-[10003]`), and mixing those with pseudo-classes is the kind
 * of selector that works until a class name changes.
 */
async function waitForVisibleFirst(page: Page, selector: string): Promise<void> {
    await page
        .locator(selector)
        .filter({ visible: true })
        .first()
        .waitFor({ state: "visible", timeout: 15_000 });
}

/**
 * Every surface the rebrand touches, per `crm-7qu.2`.
 *
 * Email templates and PDFs are deliberately absent: they are not browser surfaces. `README.md`
 * records how they are baselined instead, so they are not silently dropped.
 */
export const SURFACES: readonly VrSurface[] = [
    {
        slug: "login",
        label: "/admin/login",
        kind: "page",
        path: "/admin/login",
        anonymous: true,
        widths: WIDTHS,
        honoursDark: false,
    },
    {
        slug: "forget-password",
        label: "/admin/forget-password",
        kind: "page",
        path: "/admin/forget-password",
        anonymous: true,
        widths: WIDTHS,
        honoursDark: false,
    },
    {
        slug: "reset-password",
        label: "/admin/reset-password/{token}",
        kind: "page",
        path: `/admin/reset-password/${RESET_TOKEN}`,
        anonymous: true,
        widths: WIDTHS,
        honoursDark: false,
    },
    {
        slug: "error-404",
        label: "the 404 page (public request, error view)",
        kind: "page",
        path: "/admin/this-route-does-not-exist",
        anonymous: true,
        widths: WIDTHS,
        honoursDark: false,
        /**
         * The error view is not the anonymous *layout*: it reads the cookie, but only to choose the
         * logo (`views/errors/index.blade.php` swaps `logo.svg` for `dark-logo.svg`), while its six
         * `dark:` utilities stay dormant because the class is never set. So its two baselines really
         * do differ — by the mark, not by the theme — which is why the dark cell is kept rather than
         * declared a duplicate.
         */
        darkNote:
            "the `dark_mode` cookie swaps the logo to `dark-logo.svg` but never sets the `dark` class, so its light and dark baselines differ only by the mark",
    },
    {
        slug: "dashboard",
        label: "/admin/dashboard — cards and charts, over a pinned 2026-10-01…2026-10-31 window",
        kind: "page",
        path: DASHBOARD_PATH,
        anonymous: false,
        widths: WIDTHS,
        honoursDark: true,
        /**
         * The dashboard's charts animate in with JavaScript, which `animations: "disabled"` cannot
         * pause: that option only settles CSS animations and transitions. Without a wait the capture
         * lands mid-draw and two runs differ. 1200ms is longer than the longest chart transition
         * (~750ms) and is the only timing allowance in the harness.
         */
        settleMs: 1200,
    },
    {
        slug: "header",
        label: "the admin header, cropped",
        kind: "element",
        selector: "header",
        path: DASHBOARD_PATH,
        anonymous: false,
        widths: WIDTHS,
        honoursDark: true,
    },
    {
        slug: "sidebar",
        label: "the desktop sidebar, cropped",
        kind: "element",
        selector: DESKTOP_SIDEBAR,
        path: DASHBOARD_PATH,
        anonymous: false,
        widths: [DESKTOP_SIDEBAR_FROM],
        honoursDark: true,
    },
    {
        slug: "mobile-sidebar",
        label: "the mobile sidebar drawer, open",
        kind: "page",
        path: DASHBOARD_PATH,
        anonymous: false,
        widths: [360, 768],
        honoursDark: true,
        prepare: async (page: Page) => {
            await page.locator(MOBILE_SIDEBAR_TOGGLE).first().click();

            await waitForVisibleFirst(page, DRAWER_PANEL);
        },
    },
    {
        slug: "datagrid-listing",
        label: "/admin/leads?view_type=table — a datagrid listing",
        kind: "page",
        path: "/admin/leads?view_type=table",
        anonymous: false,
        widths: WIDTHS,
        honoursDark: true,
    },
    {
        slug: "record-detail",
        label: "the first lead's detail page (reached at /admin/leads/view/{id})",
        kind: "page",
        path: "/admin/leads?view_type=table",
        anonymous: false,
        widths: WIDTHS,
        honoursDark: true,
        /**
         * The id is **read from the instance's own datagrid**, not hardcoded and not clicked through.
         *
         * Both alternatives were measured and both failed:
         *
         * - A literal id baselines a record that a fresh database does not have (this instance's
         *   records start at 28, because earlier rows were deleted), so the baseline would photograph
         *   a 404 anywhere else.
         * - Clicking the first row does not work at all here. The listing's data grid is built from
         *   `div.table-responsive` and its cells carry no link: the only `leads/view` strings in the
         *   page's HTML belong to the mega-search templates. A click-through locator therefore waited
         *   on a non-existent anchor until the test timeout — measured, six cells at exactly 2.0m each.
         *
         * The datagrid's JSON endpoint is the same one the page itself calls, so reading `records[0].id`
         * from it resolves "the first lead" from whatever the instance holds.
         */
        prepare: async (page: Page) => {
            const origin = new URL(page.url()).origin;

            const grid = await page.request.get(
                `${ origin }/admin/leads?pagination%5Bpage%5D=1&pagination%5Bper_page%5D=10&view_type=table`,
                { headers: { "X-Requested-With": "XMLHttpRequest" } }
            );

            const payload = await grid.json();

            const records = payload.records ?? payload.data ?? [];

            const id = (Array.isArray(records) ? records[0] : records?.data?.[0])?.id;

            if (! id) {
                throw new Error("record-detail: the leads datagrid returned no records, so there is no detail page to photograph.");
            }

            const response = await page.goto(`${ origin }/admin/leads/view/${ id }`, { waitUntil: "domcontentloaded" });

            if (! response || response.status() >= 400) {
                throw new Error(`record-detail: /admin/leads/view/${ id } answered ${ response?.status() } instead of a record.`);
            }
        },
    },
    {
        slug: "modal",
        label: "the mega-search modal (Ctrl/⌘ K), open",
        kind: "page",
        path: DASHBOARD_PATH,
        anonymous: false,
        widths: WIDTHS,
        honoursDark: true,
        /**
         * Declared, and deliberately not captured yet.
         *
         * Its chrome is stable (the input, the hint, the tabs, the close control, the backdrop) but
         * its results list is not: two runs of unchanged code differed by 1,800-4,200 pixels, and the
         * difference is the list's *rows moving*, not their contents changing colour. Typing a fixed
         * query first did not settle it, and masking each avatar hid the colours while leaving the
         * rows free to shift under the masks — the masks themselves landed at different heights.
         *
         * That is a product-side rendering question (the list re-renders on a debounce and animates
         * its rows in), not something a baseline harness can paper over honestly. Skipping it keeps
         * the other 66 cells a real gate instead of teaching people that "the modal ones fail".
         */
        skip:
            "the mega-search results list re-renders on a debounced keystroke and its rows shift between loads " +
            "(measured: 1,800-4,200 differing pixels over unchanged code), so the cells are declared but not captured",
        prepare: async (page: Page) => {
            await page.keyboard.press("Control+k");

            await waitForVisibleFirst(page, "div[class*=\"z-[10004]\"]");

            /**
             * A fixed query, typed into the focused modal input.
             *
             * Left empty, the modal lists whatever it considers recent, and that list moved between
             * runs: two captures of unchanged code showed the same rows shifted by a couple of
             * pixels — 4,000 differing pixels inside the mask column, with the masks themselves at
             * different heights. A query makes the panel's contents a function of the query rather
             * than of session state: it is the same records, in the same order, every time.
             */
            await page.keyboard.type(MEGA_SEARCH_TERM);

            await page.waitForLoadState("networkidle", { timeout: 10_000 }).catch(() => {});

            await page.waitForTimeout(900);
        },
    },
    {
        slug: "drawer",
        label: "the datagrid filter drawer, open",
        kind: "page",
        path: "/admin/leads?view_type=table",
        anonymous: false,
        widths: WIDTHS,
        honoursDark: true,
        prepare: async (page: Page) => {
            await page.locator(FILTER_DRAWER_TOGGLE).first().click();

            await waitForVisibleFirst(page, DRAWER_PANEL);
        },
    },
    {
        slug: "public-web-form",
        label: "the public web form (rendered at /web-forms/forms/{form_id}/form.html)",
        kind: "page",
        path: "/admin/settings/web-forms",
        anonymous: false,
        widths: WIDTHS,
        /**
         * No dark mode here either, and it is asserted rather than assumed: the dark cells were run
         * first and failed with `expected true, received false`. The public form renders through
         * `Webkul/WebForm/src/Resources/views/components/layouts/index.blade.php`, whose `<html>` tag
         * has no `dark` class and which contains the string `dark` zero times — so the `dark_mode`
         * cookie reaches this page and nothing reads it.
         */
        honoursDark: false,
        /**
         * The public URL contains a per-install random `form_id`, so it cannot be written down here.
         * It is read from the embed modal's `#publicUrl` field on the form's own edit screen — the
         * same value the product shows an administrator to copy — and the page is then loaded from
         * that URL. Both requests go through the authenticated context, but the page that is
         * photographed is the public one: the preview route is behind `web` and `admin_locale` only,
         * with no `user` middleware.
         */
        prepare: async (page: Page) => {
            const listing = await page.request.get(`${ new URL(page.url()).origin }/admin/settings/web-forms`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
            });

            const listingBody = await listing.text();

            const formId = (listingBody.match(/web-forms\/edit\/(\d+)/) ?? listingBody.match(/"id":\s*(\d+)/))?.[1];

            if (! formId) {
                throw new Error("public-web-form: no web form exists on this instance, so the public form has nothing to render.");
            }

            const edit = await page.request.get(`${ new URL(page.url()).origin }/admin/settings/web-forms/edit/${ formId }`);

            const publicUrl = (await edit.text()).match(/web-forms\/forms\/([^/"'\s<>]+)\/form\.html/)?.[0];

            if (! publicUrl) {
                throw new Error(`public-web-form: the edit screen of web form ${ formId } carries no public URL to load.`);
            }

            /**
             * Resolved against the **origin**, not against the current page.
             *
             * `new URL(publicUrl, page.url())` looks right and is wrong: the page is
             * `/admin/settings/web-forms`, so a relative `web-forms/forms/{id}/form.html` resolves to
             * `/admin/settings/web-forms/forms/{id}/form.html`, which answers 404. The first version
             * of this quiet mistake photographed the 404 page into six `ar-public-web-form-*.png`
             * files — byte-identical to the `error-404` baselines, which is how it was caught.
             */
            const target = new URL(publicUrl.startsWith("/") ? publicUrl : `/${ publicUrl }`, new URL(page.url()).origin);

            const response = await page.goto(target.toString(), { waitUntil: "domcontentloaded" });

            if (! response || response.status() >= 400 || ! page.url().endsWith("/form.html")) {
                throw new Error(
                    `public-web-form: ${ target.toString() } answered ${ response?.status() } and left the page at ${ page.url() }.`
                );
            }
        },
    },
];

/**
 * The baselines' path for one cell, relative to `visual/`.
 *
 * A **flat** stem, `baselines/<locale>-<surface>-<width>-<mode>.png`, and the flatness is not a
 * preference. Playwright sanitises the snapshot name into a file name, turning every `/` into `-`:
 * passing `ar/login/login-1440-light` produced `ar-login-login-1440-light`, so a name cannot carry
 * a directory. `snapshotPathTemplate` cannot help either — its tokens are `{testDir}`,
 * `{testFileName}`, `{arg}` and friends, with no way to inject the surface into the directory. The
 * locale stays in the stem so an `ar` and an `en` baseline can never overwrite each other.
 */
export function baselinePath(surface: VrSurface, width: VrWidth, mode: VrMode): string {
    return `baselines/${ snapshotName(surface, width, mode) }`;
}

/**
 * The name handed to `toHaveScreenshot`.
 *
 * The extension is required: Playwright refuses a name without one once a custom
 * `snapshotPathTemplate` is in play, and it splits the name into `{arg}` and `{ext}` itself, so the
 * template's trailing `{ext}` still produces exactly one `.png`.
 */
export function snapshotName(surface: VrSurface, width: VrWidth, mode: VrMode): string {
    return `${ VR_LOCALE }-${ surface.slug }-${ width }-${ mode }.png`;
}

export type Cell = {
    surface: string;
    label: string;
    width: VrWidth;
    mode: VrMode;
    file: string;

    /** True when the surface is declared but not captured; `skipReason` says why. */
    skipped: boolean;
    skipReason: string | null;
};

/** Every cell the matrix declares, in a stable order. */
export function declaredCells(): Cell[] {
    const cells: Cell[] = [];

    for (const surface of SURFACES) {
        for (const width of surface.widths) {
            for (const mode of MODES) {
                cells.push({
                    surface: surface.slug,
                    label: surface.label,
                    width,
                    mode,
                    file: baselinePath(surface, width, mode),
                    skipped: Boolean(surface.skip),
                    skipReason: surface.skip ?? null,
                });
            }
        }
    }

    return cells;
}
