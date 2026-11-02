import { test, type Browser, type BrowserContext, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

import { renderMarkdown, type AuditCell, type AuditMatrix, type CellMetrics, type ProbeResult, type ScrolledOverflow } from "./responsive-audit-report";

/**
 * Responsive audit harness — a measuring instrument, not a test.
 *
 * It drives the real admin UI at seven widths, records what it measures into `matrix.json`, and
 * renders `matrix.md`. It asserts almost nothing on purpose: the point is an honest baseline that a
 * later phase can be compared against, so a cell that overflows is *recorded*, not failed.
 *
 * Gated by `RESPONSIVE_AUDIT=1`. Without it the whole file skips, for three reasons:
 *
 * - the normal suite must not depend on the demo dataset (it uses `admin@example.com`, the demo
 *   instance uses `manager@sijilna.demo`), and the audit needs 27 leads, 19 persons and 486
 *   activities to be interesting;
 * - 210 cells x screenshots is minutes of work, which does not belong in a CI run;
 * - the audit writes artifacts to a scratch directory that only exists on this machine.
 *
 * Everything it needs is environment-supplied, because the demo credentials must never be
 * committed:
 *
 *     RESPONSIVE_AUDIT=1 \
 *     RESPONSIVE_AUDIT_STATE=/var/tmp/pi-scratch/resp-audit/admin-auth.json \
 *     npx playwright test tests/responsive-audit.spec.ts --project=chromium
 *
 * Three environment facts are baked into the measurement, each because ignoring it silently
 * produces a wrong number:
 *
 * 1. **The UI renders Arabic and RTL.** `core_config general.general.locale_settings.locale` is
 *    `ar`, which beats `.env`'s `APP_LOCALE=en`. Every locator here is therefore locale-
 *    independent — ids, `button.primary-button`, class and structural selectors. The rendered
 *    `<html dir>` and `<html lang>` are recorded per cell so a regression in that is visible.
 * 2. **Laravel Debugbar injects thousands of nodes and a 33px fixed bar at z-index 6000000.** It is
 *    hidden by an init script (see `killDebugbar`) and the measurement additionally excludes any
 *    node inside a `phpdebugbar` subtree, and asserts per cell that none of them still has a box.
 *    Without both, `scrollWidth` and the touch-target counts are dominated by a debugging widget.
 * 3. **`php artisan serve` is single-threaded.** The suite must stay at `workers: 1`, so the matrix
 *    is walked sequentially and each cell is written to disk before the next one starts.
 */

const ENABLED = process.env.RESPONSIVE_AUDIT === "1";

const BASE_URL = (process.env.RESPONSIVE_AUDIT_BASE_URL ?? process.env.APP_URL ?? "http://127.0.0.1:8000").replace(/\/+$/, "");

const STATE_PATH = process.env.RESPONSIVE_AUDIT_STATE ?? "/var/tmp/pi-scratch/resp-audit/admin-auth.json";

const OUT_DIR = process.env.RESPONSIVE_AUDIT_OUT ?? "/var/tmp/pi-scratch/resp-audit";

const SHOTS_DIR = path.join(OUT_DIR, "shots");

/**
 * Fallback credential source. Only a path lives in the repository, never a credential: the values
 * are read at run time and never printed.
 */
const CREDENTIALS_FILE = process.env.RESPONSIVE_AUDIT_CREDENTIALS ?? "/var/tmp/pi-scratch/demo-seed/credentials.txt";

const MATRIX_JSON = path.join(OUT_DIR, "matrix.json");

const MATRIX_MD = path.join(OUT_DIR, "matrix.md");

const PROBES_JSON = path.join(OUT_DIR, "probes.json");

const HEIGHT = 800;

const ALL_WIDTHS = [360, 390, 414, 768, 1024, 1280, 1440];

/**
 * Optional narrowing, for a smoke run or for a bounded subset when the full sweep does not fit a
 * budget: `RESPONSIVE_AUDIT_WIDTHS=360,768` and `RESPONSIVE_AUDIT_VARIANTS=dashboard,leads-kanban`.
 * Unset means all of them.
 */
const parseList = (value: string | undefined): string[] =>
    (value ?? "")
        .split(",")
        .map((entry) => entry.trim())
        .filter(Boolean);

const WIDTH_FILTER = parseList(process.env.RESPONSIVE_AUDIT_WIDTHS).map((entry) => Number.parseInt(entry, 10));

const VARIANT_FILTER = parseList(process.env.RESPONSIVE_AUDIT_VARIANTS);

const WIDTHS = WIDTH_FILTER.length ? ALL_WIDTHS.filter((width) => WIDTH_FILTER.includes(width)) : ALL_WIDTHS;

const NAV_TIMEOUT = 45_000;

const SETTLE_TIMEOUT = 20_000;

/**
 * How long a cell waits for `body.is-scrolling-down` after being scrolled to the bottom.
 *
 * The class is added by a Vue `scroll` listener that ignores deltas under 2px, so a jump to the
 * bottom lands well inside one frame; the budget only has to cover that frame plus a slow tab.
 */
const SCROLLED_TIMEOUT = 5_000;

/**
 * Every id a variant can depend on. All of them are resolved from the live datagrid JSON at run
 * time (see `resolveIds`); none is ever hardcoded, because a hardcoded id measures a record that
 * may not exist in the instance the audit is pointed at.
 */
type Ids = {
    leadId: string | null;
    personId: string | null;
    attributeId: string | null;
    workflowId: string | null;
    webFormId: string | null;
    warehouseId: string | null;
    productId: string | null;
    quoteId: string | null;
};

type Variant = {
    slug: string;
    label: string;
    /** Resolved at run time; `ids` is filled from the live list pages, never hardcoded. */
    path: string | ((ids: Ids) => string);
    anonymous?: boolean;
    /** Dependant id this variant needs before it can be audited. */
    needs?: keyof Ids;
    /**
     * Optional interaction that has to happen before the page is worth measuring, returning a
     * description of what was clicked (recorded per cell) or `null` when the trigger was missing.
     *
     * The warehouse locations table needs one: `v-locations` does not fetch until its tab is
     * activated, so loading the route and measuring straight away records a page whose table has not
     * been rendered yet — a clean-looking zero that says nothing.
     */
    prepare?: (page: Page) => Promise<string | null>;
    /**
     * Marks the routes that exist in this sweep because they render the shared `x-admin::table`.
     * Their cells are checked for the component's own marker, so "this route was audited" cannot be
     * satisfied by a page that rendered everything except the table the route is listed for.
     */
    usesSharedTable?: boolean;
};

/**
 * Click the last tab in a `v-activities` tab strip.
 *
 * Structural rather than text-based on purpose: the admin renders Arabic, so "click the tab called
 * Location" would be a locale-dependent locator. Every view that passes extra types to the activities
 * component puts them after the built-in ones, so the extra type is the last tab in the strip.
 */
async function openLastActivityTab(page: Page): Promise<string | null> {
    const tabs = page.locator("div.cursor-pointer.px-3");

    const count = await tabs.count();

    if (count === 0) {
        return null;
    }

    const tab = tabs.nth(count - 1);

    const label = (await tab.innerText().catch(() => "")).trim().replace(/\s+/g, " ").slice(0, 30);

    await tab.click({ timeout: 10_000 });

    /**
     * Wait for the table itself, not just the XHR: the tab's Vue component renders its rows after the
     * fetch resolves, so a `networkidle`-only wait measured the previous tab and reported a visible
     * table with zero rows. Bounded, so a tab that legitimately has no table still returns.
     */
    await page.locator("[data-e2e-shared-table]").first().waitFor({ state: "visible", timeout: SETTLE_TIMEOUT }).catch(() => {});

    /**
     * Then wait for its first row, bounded. `waitFor` above resolves as soon as the wrapper is
     * displayed, which is before the Vue component that owns the rows has re-rendered after its fetch:
     * the wrapper measured `rows: 0` while the screenshot taken a moment later showed four. A table
     * that is legitimately empty just spends this timeout and reports zero rows.
     */
    await page
        .waitForFunction(() => Boolean(document.querySelector("[data-e2e-shared-table] tbody tr")), undefined, { timeout: 5_000 })
        .catch(() => {});

    await page.waitForLoadState("networkidle", { timeout: SETTLE_TIMEOUT }).catch(() => {});

    return `clicked the last activity tab${ label ? ` ("${ label }")` : "" } of ${ count } and waited for its XHR and its table`;
}

/**
 * The 30 route variants. Detail routes use the shapes the router actually defines
 * (`/admin/leads/view/{id}`, `/admin/contacts/persons/view/{id}`, `/admin/settings/attributes/edit/{id}`);
 * the shapes the task brief assumed are probed separately and their real status recorded in the
 * notes, so the difference is measured rather than papered over.
 *
 * The last eleven exist because the shared table had never been measured outside `leads/create` and
 * `quotes/create`. Two different blast radii, and the difference matters: `<x-admin::table>` (the
 * component whose wrapper carries the width floor) has **9** callers, while `<x-admin::table.th>` and
 * `<x-admin::table.td>` (whose nowrap moved behind `xl`) are composed by **13** views. These variants
 * cover all 13:
 *
 * | Caller | Variant | Wrapper caller? |
 * |---|---|---|
 * | `leads/common/products` | `leads-create` (already measured) | yes |
 * | `leads/view/products` | `lead-view-products` (`?tab=products`) | yes |
 * | `leads/view/quotes` | `lead-view-quotes` (`?tab=quotes`) | yes |
 * | `products/view/inventory` | `product-view` (+ tab click) | yes |
 * | `quotes/create` | `quotes-create` (already measured) | yes |
 * | `quotes/edit` | `quotes-edit` | yes |
 * | `settings/attributes/{create,edit}` | `settings-attributes-create`, `settings-attributes-edit` | yes |
 * | `settings/warehouses/view/locations` | `settings-warehouse-view` (+ tab click) | yes |
 * | `settings/web-forms/{create,edit}` | `settings-web-forms-create`, `settings-web-forms-edit` | no — th/td only |
 * | `settings/workflows/{create,edit}` | `settings-workflows-create`, `settings-workflows-edit` | no — th/td only |
 */
const VARIANTS: Variant[] = [
    { slug: "login", label: "/admin/login (anonymous)", path: "/admin/login", anonymous: true },
    { slug: "dashboard", label: "/admin/dashboard", path: "/admin/dashboard" },
    { slug: "leads-kanban", label: "/admin/leads (kanban default)", path: "/admin/leads" },
    { slug: "leads-table", label: "/admin/leads?view_type=table", path: "/admin/leads?view_type=table" },
    { slug: "leads-create", label: "/admin/leads/create", path: "/admin/leads/create", usesSharedTable: true },
    { slug: "lead-view", label: "/admin/leads/view/{firstLeadId}", path: (ids) => `/admin/leads/view/${ ids.leadId }`, needs: "leadId" },
    { slug: "persons", label: "/admin/contacts/persons", path: "/admin/contacts/persons" },
    { slug: "person-view", label: "/admin/contacts/persons/view/{firstPersonId}", path: (ids) => `/admin/contacts/persons/view/${ ids.personId }`, needs: "personId" },
    { slug: "organizations", label: "/admin/contacts/organizations", path: "/admin/contacts/organizations" },
    { slug: "organizations-create", label: "/admin/contacts/organizations/create", path: "/admin/contacts/organizations/create" },
    { slug: "products", label: "/admin/products", path: "/admin/products" },
    { slug: "quotes", label: "/admin/quotes", path: "/admin/quotes" },
    { slug: "quotes-create", label: "/admin/quotes/create", path: "/admin/quotes/create", usesSharedTable: true },
    { slug: "activities", label: "/admin/activities (table default)", path: "/admin/activities" },
    { slug: "activities-calendar", label: "/admin/activities?view-type=calendar", path: "/admin/activities?view-type=calendar" },
    { slug: "mail", label: "/admin/mail", path: "/admin/mail" },
    { slug: "settings-users", label: "/admin/settings/users", path: "/admin/settings/users" },
    { slug: "settings-attributes", label: "/admin/settings/attributes", path: "/admin/settings/attributes" },
    { slug: "account", label: "/admin/account", path: "/admin/account" },

    /* The shared table's remaining callers. */
    { slug: "lead-view-products", label: "/admin/leads/view/{firstLeadId}?tab=products (shared table)", path: (ids) => `/admin/leads/view/${ ids.leadId }?tab=products`, needs: "leadId", usesSharedTable: true },
    { slug: "lead-view-quotes", label: "/admin/leads/view/{firstLeadId}?tab=quotes (shared table)", path: (ids) => `/admin/leads/view/${ ids.leadId }?tab=quotes`, needs: "leadId", usesSharedTable: true, prepare: openLastActivityTab },
    { slug: "product-view", label: "/admin/products/view/{firstProductId} (inventory table)", path: (ids) => `/admin/products/view/${ ids.productId }`, needs: "productId", usesSharedTable: true, prepare: openLastActivityTab },
    { slug: "quotes-edit", label: "/admin/quotes/edit/{firstQuoteId} (shared table)", path: (ids) => `/admin/quotes/edit/${ ids.quoteId }`, needs: "quoteId", usesSharedTable: true },
    { slug: "settings-attributes-create", label: "/admin/settings/attributes/create (shared table)", path: "/admin/settings/attributes/create", usesSharedTable: true },
    { slug: "settings-attributes-edit", label: "/admin/settings/attributes/edit/{firstAttributeId} (shared table)", path: (ids) => `/admin/settings/attributes/edit/${ ids.attributeId }`, needs: "attributeId", usesSharedTable: true },
    { slug: "settings-workflows-create", label: "/admin/settings/workflows/create (shared table)", path: "/admin/settings/workflows/create" },
    { slug: "settings-workflows-edit", label: "/admin/settings/workflows/edit/{firstWorkflowId} (shared table)", path: (ids) => `/admin/settings/workflows/edit/${ ids.workflowId }`, needs: "workflowId" },
    { slug: "settings-web-forms-create", label: "/admin/settings/web-forms/create (shared table)", path: "/admin/settings/web-forms/create" },
    { slug: "settings-web-forms-edit", label: "/admin/settings/web-forms/edit/{firstWebFormId} (shared table)", path: (ids) => `/admin/settings/web-forms/edit/${ ids.webFormId }`, needs: "webFormId" },
    { slug: "settings-warehouse-view", label: "/admin/settings/warehouses/view/{firstWarehouseId} (locations table)", path: (ids) => `/admin/settings/warehouses/view/${ ids.warehouseId }`, needs: "warehouseId", usesSharedTable: true, prepare: openLastActivityTab },
];

/* -------------------------------------------------------------------------------------------- */
/* Environment plumbing                                                                          */
/* -------------------------------------------------------------------------------------------- */

/**
 * Credentials resolved at run time from the environment, falling back to a `email: password` file
 * that lives outside the repository. Nothing is ever logged from here — only lengths, so a mistake
 * cannot leak a password into a CI log.
 */
function resolveCredentials(): { email: string; password: string } | null {
    const envEmail = process.env.E2E_ADMIN_EMAIL;

    const envPassword = process.env.E2E_ADMIN_PASSWORD;

    if (envEmail && envPassword) {
        return { email: envEmail, password: envPassword };
    }

    if (! fs.existsSync(CREDENTIALS_FILE)) {
        return null;
    }

    const lines = fs.readFileSync(CREDENTIALS_FILE, "utf8").split("\n");

    const pairs: { email: string; password: string }[] = [];

    for (const line of lines) {
        const trimmed = line.trim();

        if (! trimmed || trimmed.startsWith("#")) {
            continue;
        }

        const separator = trimmed.indexOf(":");

        if (separator === -1) {
            continue;
        }

        const email = trimmed.slice(0, separator).trim();

        const password = trimmed.slice(separator + 1).trim();

        if (email && password) {
            pairs.push({ email, password });
        }
    }

    if (envEmail) {
        const match = pairs.find((pair) => pair.email === envEmail);

        if (match) {
            return match;
        }
    }

    return pairs[0] ?? null;
}

/**
 * Hide the Debugbar before a single byte of the document is painted, and keep hiding it.
 *
 * The style is appended to `document.head` with a retry, because at document-start the head may not
 * exist yet and a style appended to nothing silently does nothing; it is re-applied on
 * `DOMContentLoaded` in case the bar's own markup landed after the first insertion. `visibility` is
 * set as well as `display` so a rule the bar sets later cannot un-hide it through a more specific
 * selector on a child.
 */
function killDebugbar(): void {
    const RULE = "#phpdebugbar,.phpdebugbar,.phpdebugbar *{display:none !important;visibility:hidden !important}";

    const apply = (attempt = 0): void => {
        const root = document.head ?? document.documentElement;

        if (! root) {
            if (attempt < 100) {
                setTimeout(() => apply(attempt + 1), 2);
            }

            return;
        }

        const existing = document.getElementById("e2e-debugbar-kill");

        if (existing) {
            existing.textContent = RULE;

            return;
        }

        const style = document.createElement("style");

        style.id = "e2e-debugbar-kill";

        style.textContent = RULE;

        root.appendChild(style);
    };

    apply();

    document.addEventListener("DOMContentLoaded", () => apply(), { once: true });

    window.addEventListener("load", () => apply(), { once: true });
}

async function createContext(browser: Browser, options: { anonymous?: boolean; width: number }): Promise<BrowserContext> {
    const storageState = options.anonymous ? undefined : STATE_PATH;

    const context = await browser.newContext({
        viewport: { width: options.width, height: HEIGHT },
        storageState: storageState && fs.existsSync(storageState) ? storageState : undefined,
        locale: "ar",
        timezoneId: "Asia/Riyadh",
    });

    await context.addInitScript(killDebugbar);

    return context;
}

/**
 * Sign in using locale-independent selectors. `#email` / `#password` / `button.primary-button` are
 * structural: the labels and placeholders are Arabic, so anything text-based would miss.
 */
async function signIn(page: Page): Promise<void> {
    const credentials = resolveCredentials();

    if (! credentials) {
        throw new Error(`no credentials: set E2E_ADMIN_EMAIL/E2E_ADMIN_PASSWORD or provide ${ CREDENTIALS_FILE }`);
    }

    await page.goto(`${ BASE_URL }/admin/login`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });

    await page.waitForFunction(() => Boolean(document.querySelector("#app")?.__vue_app__), undefined, { timeout: SETTLE_TIMEOUT }).catch(() => {});

    await page.locator("#email").fill(credentials.email);

    await page.locator("#password").fill(credentials.password);

    await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT }).catch(() => {}),
        page.locator("button.primary-button").first().click(),
    ]);

    if (page.url().includes("/admin/login")) {
        throw new Error("sign-in did not leave /admin/login");
    }
}

/**
 * Wait until the page is worth measuring: Vue mounted (the admin shell renders server-side HTML and
 * then re-renders on mount, so measuring before that reads markup the user never sees) and the
 * network has gone quiet (the datagrids and the kanban fetch their rows over XHR, so a page captured
 * too early measures a shimmer skeleton).
 *
 * Both waits are bounded and their outcome is recorded per cell. A cell that did not settle is a
 * FAIL, not a quiet zero: a Vue app that threw leaves the server-rendered shell in place, where
 * `scrollWidth` equals `innerWidth` and the overflow measure is a satisfied zero on a broken page.
 */
async function settle(page: Page): Promise<boolean> {
    const mounted = await page
        .waitForFunction(() => Boolean(document.querySelector("#app")?.__vue_app__), undefined, { timeout: SETTLE_TIMEOUT })
        .then(() => true)
        .catch(() => false);

    const idle = await page
        .waitForLoadState("networkidle", { timeout: SETTLE_TIMEOUT })
        .then(() => true)
        .catch(() => false);

    return mounted && idle;
}

/**
 * Runtime-error recorder.
 *
 * Without it the matrix can report PASS on a page whose Vue app threw: an uncaught exception leaves
 * the server-rendered shell in place, so `documentElement.scrollWidth - innerWidth` is zero and the
 * one FAIL condition the audit had is satisfied by a dead page. "No console errors" was therefore
 * necessarily a claim made from outside the harness. These listeners put it inside.
 *
 * Playwright attaches `page.on` handlers to the page, not to a navigation, so each page gets one
 * recorder at construction and every cell drains it first — a cell then reports only the errors it
 * caused, rather than the 400 cells before it. The lists are capped because a page stuck in a
 * failing render loop can emit thousands of identical lines and bury the first, most useful one.
 */
type RuntimeErrors = { pageErrors: string[]; consoleErrors: string[] };

function attachErrorRecorder(page: Page): () => RuntimeErrors {
    let pageErrors: string[] = [];

    let consoleErrors: string[] = [];

    const push = (bucket: string[], value: string): void => {
        if (bucket.length < 20) {
            bucket.push(value.replace(/\s+/g, " ").slice(0, 300));
        }
    };

    page.on("pageerror", (error: Error) => push(pageErrors, `${ error.name }: ${ error.message.split("\n")[0] }`));

    page.on("console", (message) => {
        if (message.type() !== "error") {
            return;
        }

        const location = message.location();

        const where = location.url ? ` @ ${ location.url.split("/").slice(-2).join("/") }:${ location.lineNumber }` : "";

        push(consoleErrors, `${ message.text() }${ where }`);
    });

    return () => {
        const drained = { pageErrors, consoleErrors };

        pageErrors = [];

        consoleErrors = [];

        return drained;
    };
}

/* -------------------------------------------------------------------------------------------- */
/* Measurement                                                                                   */
/* -------------------------------------------------------------------------------------------- */

/**
 * Scroll the document to its bottom and wait for the scroll-direction state to be live.
 *
 * The admin adds `body.is-scrolling-down` from a `scroll` listener and removes it again at scroll
 * position 0, so a rule written under that class is invisible to any measurement taken at rest.
 * Scrolling programmatically fires the same listener; the two-argument `scrollTo` is used because it
 * is instant by definition, so the measurement never races a smooth-scroll animation.
 *
 * Nothing here fails on its own: a document shorter than the viewport has no scroll state to engage,
 * and that is recorded (`maxScrollY: 0`, `applied: false`) rather than treated as a defect. What the
 * cell is judged on is the overflow measured once the state *is* live.
 */
async function scrolledState(page: Page): Promise<ScrolledOverflow> {
    const maxScrollY = await page.evaluate(() => {
        const root = document.documentElement;

        const max = Math.max(0, root.scrollHeight - root.clientHeight);

        window.scrollTo(0, max);

        return max;
    });

    if (maxScrollY > 0) {
        await page
            .waitForFunction(() => document.body.classList.contains("is-scrolling-down"), undefined, { timeout: SCROLLED_TIMEOUT })
            .catch(() => {});
    }

    const measured = await page.evaluate(measureScrolledOverflow);

    return { ...measured, maxScrollY };
}

/**
 * The scrolled-state measurement, in the page's own coordinates.
 *
 * Deliberately narrower than `measurePage`: the rest-state metrics (touch targets, font sizes,
 * overlay risk) describe the page as it first renders and must not be re-derived from a scrolled
 * layout. This answers one question — with the scroll-direction rules live, does the document still
 * fit the viewport — and names the elements that say otherwise, with the tag, classes, position and
 * rect a fix would need.
 */
function measureScrolledOverflow(): Omit<ScrolledOverflow, "maxScrollY"> {
    const DEBUGBAR = "#phpdebugbar,[class*=\"phpdebugbar\"]";

    const round = (value: number): number => Math.round(value * 10) / 10;

    const root = document.documentElement;

    const innerWidth = window.innerWidth;

    const clientWidth = root.clientWidth;

    const offences = Array.from(document.querySelectorAll("body *"))
        .filter((element) => ! element.closest(DEBUGBAR))
        .map((element) => {
            const rect = element.getBoundingClientRect();

            if (rect.width <= 0 || rect.height <= 0) {
                return null;
            }

            /** Both edges: an RTL document overflows past the left edge, not the right. */
            const pastRight = rect.right - clientWidth;

            const pastLeft = -rect.left;

            if (pastRight <= 1 && pastLeft <= 1) {
                return null;
            }

            return { element, rect, pastRight, pastLeft };
        })
        .filter((entry): entry is { element: Element; rect: DOMRect; pastRight: number; pastLeft: number } => entry !== null);

    /** Leaf-most only: a wrapper and the child that overflows it are one defect, not two. */
    const leaves = offences.filter(
        (candidate) => ! offences.some((other) => other.element !== candidate.element && candidate.element.contains(other.element))
    );

    const culprits = leaves
        .sort((a, b) => Math.max(b.pastRight, b.pastLeft) - Math.max(a.pastRight, a.pastLeft))
        .slice(0, 5)
        .map(({ element, rect, pastRight, pastLeft }) => {
            const style = window.getComputedStyle(element);

            return {
                selector: `${ element.tagName.toLowerCase() }${ Array.from(element.classList)
                    .filter((name) => name.length < 40 && ! /[[\]:/@%!()]/.test(name))
                    .slice(0, 3)
                    .map((name) => `.${ name }`)
                    .join("") }`,
                tag: element.tagName.toLowerCase(),
                classes: Array.from(element.classList).slice(0, 4),
                position: style.position,
                rect: { x: round(rect.x), y: round(rect.y), width: round(rect.width), height: round(rect.height) },
                overshootPx: round(Math.max(pastRight, pastLeft)),
                side: (pastRight >= pastLeft ? "right" : "left") as "left" | "right",
            };
        });

    /**
     * The reactive header bar's own box. Without it, "the bleed was fixed" and "the bar was deleted"
     * are indistinguishable: both report zero overflow.
     */
    const bar = document.querySelector(".scroll-reactive-sticky");

    const barRect = bar ? bar.getBoundingClientRect() : null;

    const barStyle = bar ? window.getComputedStyle(bar) : null;

    return {
        applied: document.body.classList.contains("is-scrolling-down"),
        scrollY: Math.round(window.scrollY),
        overflowPxScrolled: round(root.scrollWidth - clientWidth),
        bodyOverflowPxScrolled: round(document.body.scrollWidth - clientWidth),
        innerWidth,
        clientWidth,
        stickyBar:
            barRect && barStyle
                ? {
                      left: round(barRect.left),
                      right: round(barRect.right),
                      width: round(barRect.width),
                      marginInlineStart: barStyle.marginInlineStart,
                      marginInlineEnd: barStyle.marginInlineEnd,
                  }
                : null,
        culprits,
    };
}

/**
 * Everything measured in one `page.evaluate`, in the page's own coordinates.
 *
 * Kept as a single self-contained function because Playwright serialises it: it cannot close over
 * anything in this module, so all helpers live inside it.
 */
function measurePage(): CellMetrics {
    const DEBUGBAR = "#phpdebugbar,[class*=\"phpdebugbar\"]";

    const inDebugbar = (element: Element): boolean => Boolean(element.closest(DEBUGBAR));

    const box = (element: Element): DOMRect => element.getBoundingClientRect();

    const round = (value: number): number => Math.round(value * 10) / 10;

    const isVisible = (element: Element): boolean => {
        const style = window.getComputedStyle(element);

        if (style.display === "none" || style.visibility === "hidden" || style.opacity === "0") {
            return false;
        }

        const rect = box(element);

        return rect.width > 0 && rect.height > 0;
    };

    /**
     * A readable selector. Classes holding brackets, colons or slashes are dropped rather than
     * escaped: this string is for a human reading the report, not for re-selecting the node, and
     * `div.max-h-[420px]` reads worse than `div.absolute.flex`.
     */
    const selectorFor = (element: Element): string => {
        const parts: string[] = [];

        let node: Element | null = element;

        let depth = 0;

        while (node && depth < 4 && node !== document.documentElement) {
            if (node.id) {
                parts.unshift(`#${ node.id }`);

                break;
            }

            let part = node.tagName.toLowerCase();

            const classes = Array.from(node.classList).filter(
                (name) => name.length < 40 && ! /[[\]:/@%!()]/.test(name) && ! name.includes("phpdebugbar")
            );

            if (classes.length) {
                part += `.${ classes.slice(0, 3).join(".") }`;
            }

            const parent = node.parentElement;

            if (parent) {
                const siblings = Array.from(parent.children).filter((child) => child.tagName === node!.tagName);

                if (siblings.length > 1) {
                    part += `:nth-of-type(${ siblings.indexOf(node) + 1 })`;
                }
            }

            parts.unshift(part);

            if (node === document.body) {
                break;
            }

            node = node.parentElement;

            depth += 1;
        }

        return parts.join(" > ");
    };

    const screenshotSafe = (element: Element): string => (element.textContent ?? "").trim().replace(/\s+/g, " ").slice(0, 40);

    const all = Array.from(document.querySelectorAll("body *")).filter((element) => ! inDebugbar(element));

    /* Page overflow --------------------------------------------------------------------------- */

    const innerWidth = window.innerWidth;

    const innerHeight = window.innerHeight;

    const documentScrollWidth = document.documentElement.scrollWidth;

    const bodyScrollWidth = document.body.scrollWidth;

    /**
     * Both edges, because the UI is RTL: in a right-to-left document an element that overflows
     * commonly expands past the *left* edge, so a right-edge-only check would report zero on a page
     * that clearly scrolls sideways.
     */
    const overflowCandidates = all
        .map((element) => {
            const rect = box(element);

            if (rect.width <= 0 || rect.height <= 0) {
                return null;
            }

            const pastRight = rect.right - innerWidth;

            const pastLeft = -rect.left;

            if (pastRight <= 1 && pastLeft <= 1) {
                return null;
            }

            return { element, rect, pastRight, pastLeft };
        })
        .filter((entry): entry is { element: Element; rect: DOMRect; pastRight: number; pastLeft: number } => entry !== null);

    /**
     * Keep the leaf-most offenders. A wrapper and its overflowing child are both past the edge; the
     * child is the one a fix would touch, and reporting both buries it.
     */
    const leaves = overflowCandidates.filter(
        (candidate) => ! overflowCandidates.some((other) => other.element !== candidate.element && candidate.element.contains(other.element))
    );

    /**
     * Whether the offender sits inside a container that clips or scrolls horizontally. In that case
     * its position past the viewport edge is the container's business, not the page's.
     */
    const scrollerFor = (element: Element): string | null => {
        let node: Element | null = element.parentElement;

        let depth = 0;

        while (node && node !== document.documentElement && depth < 30) {
            const overflowX = window.getComputedStyle(node).overflowX;

            const clips = overflowX === "auto" || overflowX === "scroll" || overflowX === "hidden" || overflowX === "clip";

            if (clips && node.scrollWidth > node.clientWidth + 1) {
                return selectorFor(node);
            }

            node = node.parentElement;

            depth += 1;
        }

        return null;
    };

    const culprits = leaves
        .sort((a, b) => Math.max(b.pastRight, b.pastLeft) - Math.max(a.pastRight, a.pastLeft))
        .slice(0, 5)
        .map(({ element, rect, pastRight, pastLeft }) => ({
            selector: selectorFor(element),
            tag: element.tagName.toLowerCase(),
            rect: { x: round(rect.x), y: round(rect.y), width: round(rect.width), height: round(rect.height) },
            overshootPx: round(Math.max(pastRight, pastLeft)),
            side: (pastRight >= pastLeft ? "right" : "left") as "left" | "right",
            overflowX: window.getComputedStyle(element).overflowX,
            insideScroller: scrollerFor(element),
            text: screenshotSafe(element),
        }));

    /* Internal / clipped horizontal scroll ----------------------------------------------------- */

    const internalScroll = all
        .filter((element) => {
            const html = element as HTMLElement;

            if (html.clientWidth <= 0) {
                return false;
            }

            if (html.scrollWidth - html.clientWidth <= 1) {
                return false;
            }

            return isVisible(element);
        })
        .map((element) => {
            const html = element as HTMLElement;

            const overflowX = window.getComputedStyle(element).overflowX;

            const scrolls = overflowX === "auto" || overflowX === "scroll";

            return {
                selector: selectorFor(element),
                scrollWidth: html.scrollWidth,
                clientWidth: html.clientWidth,
                deltaPx: html.scrollWidth - html.clientWidth,
                overflowX,
                kind: (scrolls ? "scrolls" : "clipped") as "scrolls" | "clipped",
            };
        })
        .sort((a, b) => b.deltaPx - a.deltaPx);

    /* Touch targets ---------------------------------------------------------------------------- */

    const INTERACTIVE = "a, button, input[type=submit], [role=button], .cursor-pointer";

    const touchCandidates = Array.from(document.querySelectorAll(INTERACTIVE))
        .filter((element) => ! inDebugbar(element) && isVisible(element))
        .map((element) => {
            const rect = box(element);

            const style = window.getComputedStyle(element);

            /**
             * An inline link's box is its text, and its padding is not in the hit area, so a small
             * one is expected rather than a defect. Those are counted separately instead of being
             * dropped, so the number stays honest.
             */
            const isInlineLink = element.tagName === "A" && style.display === "inline";

            return {
                selector: selectorFor(element),
                tag: element.tagName.toLowerCase(),
                text: screenshotSafe(element),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
                minSide: Math.round(Math.min(rect.width, rect.height)),
                severity: (isInlineLink ? "low" : "high") as "low" | "high",
            };
        })
        .filter((target) => target.width < 44 || target.height < 44);

    /* iOS zoom trigger ------------------------------------------------------------------------- */

    const TEXT_INPUT_TYPES = new Set([
        "text",
        "search",
        "email",
        "tel",
        "url",
        "password",
        "number",
        "date",
        "datetime-local",
        "month",
        "week",
        "time",
        "",
    ]);

    const smallFontCandidates = Array.from(document.querySelectorAll("input, select, textarea"))
        .filter((element) => {
            if (inDebugbar(element) || ! isVisible(element)) {
                return false;
            }

            if (element.tagName === "INPUT") {
                const type = (element.getAttribute("type") ?? "").toLowerCase();

                if (! TEXT_INPUT_TYPES.has(type)) {
                    return false;
                }
            }

            return true;
        })
        .map((element) => {
            const fontSizePx = round(parseFloat(window.getComputedStyle(element).fontSize) || 0);

            const rect = box(element);

            return {
                selector: selectorFor(element),
                tag: element.tagName.toLowerCase(),
                type: element.getAttribute("type") ?? element.tagName.toLowerCase(),
                fontSizePx,
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            };
        })
        .filter((field) => field.fontSizePx > 0 && field.fontSizePx < 16)
        .sort((a, b) => a.fontSizePx - b.fontSizePx);

    /* Overlay risk ----------------------------------------------------------------------------- */

    const overlayCandidates = all
        .filter((element) => {
            if (! isVisible(element)) {
                return false;
            }

            const position = window.getComputedStyle(element).position;

            return position === "fixed" || position === "absolute";
        })
        .map((element) => {
            const rect = box(element);

            return {
                selector: selectorFor(element),
                tag: element.tagName.toLowerCase(),
                position: window.getComputedStyle(element).position,
                zIndex: window.getComputedStyle(element).zIndex,
                height: Math.round(rect.height),
                viewportFraction: Math.round((rect.height / innerHeight) * 100) / 100,
            };
        })
        .filter((overlay) => overlay.height > innerHeight)
        .sort((a, b) => b.height - a.height);

    /* Header collision ------------------------------------------------------------------------- */

    const header = document.querySelector("header");

    const headerRect = header ? box(header) : null;

    const headerCollision = {
        headerFound: Boolean(header),
        headerHeight: headerRect ? Math.round(headerRect.height) : 0,
        headerWrapped: Boolean(headerRect && headerRect.height > 80),
        collidingCount: 0,
        worst: [] as { selector: string; overlapHeightPx: number; rect: { x: number; y: number; width: number; height: number } }[],
    };

    if (header && headerRect) {
        const colliding = all
            .filter((element) => {
                if (header.contains(element)) {
                    return false;
                }

                /**
                 * A container that *wraps* the header — `#app` is the obvious one — trivially spans
                 * the header band. Collision means page content sitting under the bar, not a layout
                 * ancestor of the bar itself.
                 */
                if (element.contains(header)) {
                    return false;
                }

                const style = window.getComputedStyle(element);

                if (style.position === "fixed" || style.position === "absolute") {
                    return false;
                }

                const rect = box(element);

                if (rect.width < 20 || rect.height < 20 || rect.width * rect.height < 1000) {
                    return false;
                }

                return rect.top < headerRect.bottom - 1 && rect.bottom > headerRect.top + 1;
            })
            .map((element) => {
                const rect = box(element);

                return {
                    selector: selectorFor(element),
                    overlapHeightPx: Math.round(Math.min(rect.bottom, headerRect.bottom) - Math.max(rect.top, headerRect.top)),
                    rect: { x: round(rect.x), y: round(rect.y), width: round(rect.width), height: round(rect.height) },
                };
            })
            .sort((a, b) => b.overlapHeightPx - a.overlapHeightPx);

        headerCollision.collidingCount = colliding.length;

        headerCollision.worst = colliding.slice(0, 3);
    }

    /* Shared table ------------------------------------------------------------------------------ */

    /**
     * The `x-admin::table` component marks its wrapper with `data-e2e-shared-table`, so "this page
     * rendered the shared table" is a fact rather than a guess from `document.querySelector('table')`
     * — plenty of pages render some other table, and several shared-table callers render the wrapper
     * with zero rows while the surrounding page still looks finished. That distinction is the whole
     * point of auditing these routes.
     */
    const sharedWrappers = Array.from(document.querySelectorAll("[data-e2e-shared-table]"));

    const sharedTable = sharedWrappers.map((wrapper) => {
        const table = wrapper.querySelector("table");

        const wrapperRect = box(wrapper);

        const tableRect = table ? box(table) : null;

        const style = table ? window.getComputedStyle(table) : null;

        return {
            visible: isVisible(wrapper) && Boolean(tableRect && tableRect.width > 0),
            wrapperWidth: Math.round(wrapperRect.width),
            wrapperClientWidth: (wrapper as HTMLElement).clientWidth,
            tableWidth: tableRect ? Math.round(tableRect.width) : 0,
            internalScrollPx: Math.max(0, (wrapper as HTMLElement).scrollWidth - (wrapper as HTMLElement).clientWidth),
            overflowX: window.getComputedStyle(wrapper).overflowX,
            layout: style ? style.tableLayout : null,
            minWidth: style ? style.minWidth : null,
            columns: table ? table.querySelectorAll("thead th").length : 0,
            rows: table ? table.querySelectorAll("tbody tr").length : 0,
        };
    });

    const visibleShared = sharedTable.filter((entry) => entry.visible);

    /* Debugbar proof --------------------------------------------------------------------------- */

    const debugbarNodes = Array.from(document.querySelectorAll(DEBUGBAR));

    const debugbarVisibleNodes = debugbarNodes.filter((element) => {
        const style = window.getComputedStyle(element);

        if (style.display === "none" || style.visibility === "hidden") {
            return false;
        }

        const rect = box(element);

        return rect.width > 0 && rect.height > 0;
    }).length;

    return {
        dir: document.documentElement.getAttribute("dir"),
        lang: document.documentElement.getAttribute("lang"),
        title: document.title,
        innerWidth,
        innerHeight,
        documentScrollWidth,
        documentScrollHeight: document.documentElement.scrollHeight,
        bodyScrollWidth,
        overflowPx: round(documentScrollWidth - innerWidth),
        bodyOverflowPx: round(bodyScrollWidth - innerWidth),
        culprits,
        internalScroll: internalScroll.slice(0, 5),
        clippedOverflowCount: internalScroll.filter((item) => item.kind === "clipped").length,
        touchTargets: {
            count: touchCandidates.length,
            lowSeverityCount: touchCandidates.filter((target) => target.severity === "low").length,
            worst: touchCandidates.sort((a, b) => a.minSide - b.minSide).slice(0, 5),
        },
        smallFontInputs: { count: smallFontCandidates.length, worst: smallFontCandidates.slice(0, 5) },
        overlayRisk: { count: overlayCandidates.length, worst: overlayCandidates.slice(0, 5) },
        headerCollision,
        sharedTable: {
            /** How many `x-admin::table` wrappers the page contains (`clipped` ones are not counted). */
            wrappers: sharedTable.length,
            visible: visibleShared.length,
            rendered: visibleShared.some((entry) => entry.rows > 0),
            entries: sharedTable.slice(0, 4),
        },
        debugbar: {
            nodes: debugbarNodes.length,
            visibleNodes: debugbarVisibleNodes,
            styleTagPresent: Boolean(document.getElementById("e2e-debugbar-kill")),
        },
    };
}

/* -------------------------------------------------------------------------------------------- */
/* Artifact writing                                                                              */
/* -------------------------------------------------------------------------------------------- */

function ensureOutDir(): void {
    fs.mkdirSync(SHOTS_DIR, { recursive: true });
}

function writeJson(file: string, value: unknown): void {
    fs.writeFileSync(file, `${ JSON.stringify(value, null, 2) }\n`, "utf8");
}

/**
 * Written after every cell rather than once at the end: a run that dies at cell 90 must still leave
 * 90 honest cells behind. Rendering the markdown on every cell is cheap and means `matrix.md`
 * exists the moment the first cell does.
 */
function persist(matrix: AuditMatrix): void {
    writeJson(MATRIX_JSON, matrix);

    fs.writeFileSync(MATRIX_MD, renderMarkdown(matrix, readProbes()), "utf8");
}

function readProbes(): ProbeResult[] {
    if (! fs.existsSync(PROBES_JSON)) {
        return [];
    }

    try {
        return JSON.parse(fs.readFileSync(PROBES_JSON, "utf8")) as ProbeResult[];
    } catch {
        return [];
    }
}

/* -------------------------------------------------------------------------------------------- */
/* Id resolution                                                                                 */
/* -------------------------------------------------------------------------------------------- */

/**
 * Entity ids are read from the datagrid JSON the list pages themselves use, rather than scraped from
 * the DOM: the persons and products grids render their rows from an XHR payload and rows are not
 * always anchors, so a DOM scrape is both slow and fragile.
 */
async function resolveIds(page: Page, notes: string[]): Promise<Ids> {
    const headers = { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" };

    const firstId = async (url: string): Promise<string | null> => {
        try {
            const response = await page.request.get(`${ BASE_URL }/${ url }?pagination[page]=1&pagination[per_page]=1`, { headers });

            if (! response.ok()) {
                notes.push(`id resolution: \`${ url }\` answered ${ response.status() }`);

                return null;
            }

            const payload = (await response.json()) as { records?: { id?: number | string }[] };

            const id = payload.records?.[0]?.id;

            return id === undefined ? null : String(id);
        } catch (error) {
            notes.push(`id resolution: \`${ url }\` threw ${ (error as Error).message.split("\n")[0] }`);

            return null;
        }
    };

    const leadId = await firstId("admin/leads");

    const personId = await firstId("admin/contacts/persons");

    const productId = await firstId("admin/products");

    const quoteId = await firstId("admin/quotes");

    const attributeId = await firstId("admin/settings/attributes");

    const workflowId = await firstId("admin/settings/workflows");

    const webFormId = await firstId("admin/settings/web-forms");

    const warehouseId = await firstId("admin/settings/warehouses");

    const resolved: Ids = { leadId, personId, attributeId, workflowId, webFormId, warehouseId, productId, quoteId };

    notes.push(
        `id resolution (from each list's own datagrid JSON, never hardcoded): ${ Object.entries(resolved)
            .map(([key, value]) => `${ key }=${ value ?? "unresolved" }`)
            .join(", ") }`
    );

    return resolved;
}

/**
 * The task brief assumed detail routes at `/admin/leads/{id}` and `/admin/contacts/persons/{id}`.
 * The router defines `/view/{id}`. This measures what the assumed shape actually answers so the
 * report can say so with a status code instead of an opinion.
 */
async function probeAssumedRouteShapes(page: Page, ids: Ids, notes: string[]): Promise<void> {
    const checks: [string, string | null][] = [
        ["/admin/leads/{id} (assumed shape)", ids.leadId ? `/admin/leads/${ ids.leadId }` : null],
        ["/admin/leads/view/{id} (routed shape)", ids.leadId ? `/admin/leads/view/${ ids.leadId }` : null],
        ["/admin/contacts/persons/{id} (assumed shape)", ids.personId ? `/admin/contacts/persons/${ ids.personId }` : null],
        ["/admin/contacts/persons/view/{id} (routed shape)", ids.personId ? `/admin/contacts/persons/view/${ ids.personId }` : null],

        /**
         * The three shapes the wave-3 brief guessed for the per-module edit and detail screens.
         * Recorded whether or not they resolve, because "the brief said `/attributes/{id}/edit`" is
         * exactly the kind of assumption that later reads as "the page was measured".
         */
        ["/admin/settings/attributes/{id}/edit (assumed shape)", ids.attributeId ? `/admin/settings/attributes/${ ids.attributeId }/edit` : null],
        ["/admin/settings/attributes/edit/{id} (routed shape)", ids.attributeId ? `/admin/settings/attributes/edit/${ ids.attributeId }` : null],
        ["/admin/settings/workflows/{id}/edit (assumed shape)", ids.workflowId ? `/admin/settings/workflows/${ ids.workflowId }/edit` : null],
        ["/admin/settings/workflows/edit/{id} (routed shape)", ids.workflowId ? `/admin/settings/workflows/edit/${ ids.workflowId }` : null],
        ["/admin/settings/web-forms/{id}/edit (assumed shape)", ids.webFormId ? `/admin/settings/web-forms/${ ids.webFormId }/edit` : null],
        ["/admin/settings/web-forms/edit/{id} (routed shape)", ids.webFormId ? `/admin/settings/web-forms/edit/${ ids.webFormId }` : null],
        ["/admin/settings/warehouses/{id}/locations (assumed shape)", ids.warehouseId ? `/admin/settings/warehouses/${ ids.warehouseId }/locations` : null],
        ["/admin/settings/warehouses/view/{id} (routed shape)", ids.warehouseId ? `/admin/settings/warehouses/view/${ ids.warehouseId }` : null],
        ["/admin/products/{id}/inventory (assumed shape)", ids.productId ? `/admin/products/${ ids.productId }/inventory` : null],
        ["/admin/products/view/{id} (routed shape)", ids.productId ? `/admin/products/view/${ ids.productId }` : null],
        ["/admin/quotes/{id}/edit (assumed shape)", ids.quoteId ? `/admin/quotes/${ ids.quoteId }/edit` : null],
        ["/admin/quotes/edit/{id} (routed shape)", ids.quoteId ? `/admin/quotes/edit/${ ids.quoteId }` : null],
        ["/admin/leads/view/{id}?tab=products", ids.leadId ? `/admin/leads/view/${ ids.leadId }?tab=products` : null],
        ["/admin/leads/view/{id}?tab=quotes", ids.leadId ? `/admin/leads/view/${ ids.leadId }?tab=quotes` : null],
    ];

    for (const [label, url] of checks) {
        if (! url) {
            notes.push(`${ label }: skipped, no id resolved`);

            continue;
        }

        const response = await page.goto(`${ BASE_URL }${ url }`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT }).catch(() => null);

        notes.push(`${ label }: HTTP ${ response ? response.status() : "navigation error" } -> \`${ page.url().replace(BASE_URL, "") }\``);
    }
}

/* -------------------------------------------------------------------------------------------- */
/* Interaction probes                                                                            */
/* -------------------------------------------------------------------------------------------- */

type ProbePanel = NonNullable<ProbeResult["panel"]>;

type OverlaySample = Omit<ProbePanel, "content"> & { id: string };

/**
 * Snapshot the overlays on the page and stamp each one with an id, so a panel opened later can be
 * told apart from chrome that was already there.
 *
 * This is why the probe does not filter by z-index or class: a dropdown that opens after the click is
 * *new*, and "new absolute/fixed box of real area" is a signal that does not depend on which
 * component owns the panel. The only thing excluded is a box that lies entirely inside the header's
 * own rectangle — that is header chrome, not a panel that hangs off it.
 */
async function collectOverlays(page: Page, tag: string): Promise<OverlaySample[]> {
    return page.evaluate((stamp) => {
        const DEBUGBAR = "#phpdebugbar,[class*=\"phpdebugbar\"]";

        const header = document.querySelector("header");

        const headerRect = header ? header.getBoundingClientRect() : null;

        const nodes = Array.from(document.querySelectorAll("body *")).filter((element) => {
            if (element.closest(DEBUGBAR)) {
                return false;
            }

            const style = window.getComputedStyle(element);

            if (style.display === "none" || style.visibility === "hidden" || style.opacity === "0") {
                return false;
            }

            if (style.position !== "fixed" && style.position !== "absolute") {
                return false;
            }

            const rect = element.getBoundingClientRect();

            if (rect.width < 80 || rect.height < 60) {
                return false;
            }

            if (headerRect && rect.top >= headerRect.top - 1 && rect.bottom <= headerRect.bottom + 1 && rect.left >= headerRect.left - 1 && rect.right <= headerRect.right + 1) {
                return false;
            }

            return true;
        });

        nodes.forEach((element, index) => {
            const html = element as HTMLElement;

            if (! html.dataset.e2eOverlay) {
                html.dataset.e2eOverlay = `${ stamp }${ index }`;
            }
        });

        return nodes.map((element) => {
            const html = element as HTMLElement;

            const rect = element.getBoundingClientRect();

            const style = window.getComputedStyle(element);

            const classes = Array.from(element.classList).filter((name) => name.length < 40 && ! /[[\]:/@%!()]/.test(name)).slice(0, 3);

            const innerWidth = window.innerWidth;

            const innerHeight = window.innerHeight;

            return {
                id: html.dataset.e2eOverlay ?? "",
                selector: `${ element.tagName.toLowerCase() }${ classes.length ? `.${ classes.join(".") }` : "" }`,
                rect: {
                    x: Math.round(rect.x),
                    y: Math.round(rect.y),
                    width: Math.round(rect.width),
                    height: Math.round(rect.height),
                },
                position: style.position,
                zIndex: style.zIndex,
                scrollHeight: html.scrollHeight,
                clientHeight: html.clientHeight,
                exceedsViewportHeight: rect.height > innerHeight,
                exceedsViewportWidth: rect.right > innerWidth + 1 || rect.left < -1,
                overflowPxRight: Math.round(rect.right - innerWidth),
                overflowPxLeft: Math.round(-rect.left),
                internalScrollY: html.scrollHeight - html.clientHeight > 1,
            };
        });
    }, tag);
}

/**
 * Pick the panel that was just opened.
 *
 * Innermost first: a modal opens as a full-viewport wrapper (the backdrop) that *contains* the
 * dialog, and the interesting geometry is the dialog's — a backdrop is the viewport by definition,
 * so reporting it would answer "does it fit?" with a tautology. Containment is decided on rects,
 * because the samples are plain data by the time they get here. Highest z-index breaks the tie among
 * siblings, and largest area breaks the remaining one (two stacked cards in one popover).
 */
function pickSample(overlays: OverlaySample[]): OverlaySample | null {
    const contains = (outer: OverlaySample, inner: OverlaySample): boolean =>
        outer !== inner &&
        outer.rect.x <= inner.rect.x + 1 &&
        outer.rect.y <= inner.rect.y + 1 &&
        outer.rect.x + outer.rect.width >= inner.rect.x + inner.rect.width - 1 &&
        outer.rect.y + outer.rect.height >= inner.rect.y + inner.rect.height - 1;

    const innermost = overlays.filter((candidate) => ! overlays.some((other) => contains(candidate, other)));

    const ranked = [...(innermost.length ? innermost : overlays)].sort(
        (a, b) => (Number.parseInt(b.zIndex, 10) || 0) - (Number.parseInt(a.zIndex, 10) || 0) || b.rect.width * b.rect.height - a.rect.width * a.rect.height
    );

    return ranked[0] ?? null;
}

/**
 * Measure the tallest visible block inside a stamped panel — in practice the modal dialog or the
 * dropdown's option list, which is the box that can be too tall or too wide.
 */
async function measurePanelContent(page: Page, overlayId: string): Promise<NonNullable<ProbePanel["content"]> | null> {
    return page.evaluate((id) => {
        const DEBUGBAR = "#phpdebugbar,[class*=\"phpdebugbar\"]";

        const panel = document.querySelector(`[data-e2e-overlay="${ id }"]`);

        if (! panel) {
            return null;
        }

        const innerWidth = window.innerWidth;

        const innerHeight = window.innerHeight;

        const candidates = Array.from(panel.querySelectorAll("*")).filter((element) => {
            if (element.closest(DEBUGBAR)) {
                return false;
            }

            const style = window.getComputedStyle(element);

            if (style.display === "none" || style.visibility === "hidden" || style.opacity === "0") {
                return false;
            }

            const rect = element.getBoundingClientRect();

            return rect.width > 100 && rect.height > 80 && rect.width * rect.height > 3000;
        });

        if (! candidates.length) {
            return null;
        }

        /**
         * Pick the tallest block inside the panel that still fits the viewport, and fall back to the
         * tallest when nothing fits.
         *
         * A modal renders as `fixed inset-0` backdrop > centring flex wrapper > dialog card, where the
         * first two are the viewport by definition. Taking a plain "tallest" therefore reports a
         * number that cannot fail and answers nothing. Preferring blocks shorter than the viewport
         * lands on the dialog card; if every candidate is at least viewport height, the dialog itself
         * is the too-tall case and the tallest is exactly what should be reported.
         */
        const measured = candidates.map((element) => ({ element, rect: element.getBoundingClientRect() }));

        const fitting = measured.filter((entry) => entry.rect.height < innerHeight - 1);

        const pool = fitting.length ? fitting : measured;

        const tallest = [...pool].sort((a, b) => b.rect.height - a.rect.height)[0];

        const html = tallest.element as HTMLElement;

        const classes = Array.from(tallest.element.classList).filter((name) => name.length < 40 && ! /[[\]:/@%!()]/.test(name)).slice(0, 3);

        return {
            selector: `${ tallest.element.tagName.toLowerCase() }${ classes.length ? `.${ classes.join(".") }` : "" }`,
            rect: {
                x: Math.round(tallest.rect.x),
                y: Math.round(tallest.rect.y),
                width: Math.round(tallest.rect.width),
                height: Math.round(tallest.rect.height),
            },
            scrollHeight: html.scrollHeight,
            clientHeight: html.clientHeight,
            exceedsViewportHeight: tallest.rect.height > innerHeight,
            exceedsViewportWidth: tallest.rect.right > innerWidth + 1 || tallest.rect.left < -1,
            internalScrollY: html.scrollHeight - html.clientHeight > 1,
        };
    }, overlayId);
}

/**
 * Click, then look for an overlay that did not exist before the click. Returns `undefined` when the
 * click could not be made at all, and `null` for the panel when nothing new appeared.
 */
async function attemptOpen(page: Page, click: () => Promise<string | null>): Promise<{ trigger: string; panel: ProbePanel | null } | null> {
    await collectOverlays(page, "pre");

    const trigger = await click();

    if (! trigger) {
        return null;
    }

    await page.waitForTimeout(500);

    const after = await collectOverlays(page, "post");

    const fresh = after.filter((overlay) => overlay.id.startsWith("post"));

    const sample = pickSample(fresh);

    if (! sample) {
        await page.keyboard.press("Escape").catch(() => {});

        await page.waitForTimeout(200);

        return { trigger, panel: null };
    }

    const content = await measurePanelContent(page, sample.id);

    const { id: _id, ...panel } = sample;

    return { trigger, panel: { ...panel, content } };
}

/**
 * The three interactions the brief calls out, all at width 360 where they are most likely to break:
 * a dropdown on the lead create form, the settings/users create modal, and the notifications panel.
 */
async function runInteractionProbes(browser: Browser, ids: Ids): Promise<ProbeResult[]> {
    const results: ProbeResult[] = [];

    const context = await createContext(browser, { width: 360 });

    const page = await context.newPage();

    const probe = async (name: string, open: () => Promise<{ trigger: string; panel: ProbePanel | null } | null>, notes: string[]): Promise<void> => {
        try {
            const attempt = await open();

            const metrics = await page.evaluate(measurePage);

            results.push({
                name,
                width: 360,
                ok: Boolean(attempt?.trigger) && Boolean(attempt?.panel),
                notes: attempt ? notes : [...notes, "no trigger was found for this probe"],
                trigger: attempt?.trigger ?? null,
                panel: attempt?.panel ?? null,
                metrics,
            });
        } catch (error) {
            results.push({
                name,
                width: 360,
                ok: false,
                notes: [...notes, `probe threw: ${ (error as Error).message.split("\n")[0] }`],
                trigger: null,
                panel: null,
                metrics: null,
            });
        }
    };

    /* 1. Lead create form and its primary dropdown. */
    await page.goto(`${ BASE_URL }/admin/leads/create`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });

    await settle(page);

    await probe(
        "Lead create: primary dropdown",
        async () => {
            /**
             * The form's controls are rendered from the attribute set, so which dropdown is first is
             * an attribute-order question rather than a design decision. Candidates are tried in
             * order and the first one that actually opens a panel is the one reported — guessing a
             * single selector and reporting "no panel" when the guess is wrong would be a false
             * negative.
             *
             * Native `<select>` elements are skipped on purpose: Chromium paints their popup outside
             * the document, so nothing about its geometry is measurable.
             */
            const candidates = [
                "form div.cursor-pointer:has(i.icon-down-arrow)",
                "form div.select-none:has(i.icon-down-arrow)",
                "form div.cursor-pointer:has(i.icon-arrow-down)",
                "form i.icon-down-arrow",
            ];

            const tried: string[] = [];

            for (const selector of candidates) {
                const count = await page.locator(selector).count();

                for (let index = 0; index < Math.min(count, 3); index += 1) {
                    const locator = page.locator(selector).nth(index);

                    if (! (await locator.isVisible().catch(() => false))) {
                        continue;
                    }

                    const label = (await locator.innerText().catch(() => "")).trim().replace(/\s+/g, " ").slice(0, 30);

                    const attempt = await attemptOpen(page, async () => {
                        await locator.click({ timeout: 8_000 });

                        return `${ selector } [${ index }]${ label ? ` — "${ label }"` : "" }`;
                    });

                    if (attempt?.panel) {
                        return attempt;
                    }

                    tried.push(attempt?.trigger ?? `${ selector } [${ index }]`);
                }
            }

            return tried.length
                ? { trigger: tried[0], panel: null }
                : null;
        },
        [
            "Candidate dropdown triggers are tried in order and the first one that opens a panel is reported, because the lead create form renders its controls from the attribute set.",
        ]
    );

    /* 2. Settings/users create modal. */
    await page.goto(`${ BASE_URL }/admin/settings/users`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });

    await settle(page);

    await probe(
        "Settings/users: create modal",
        async () => {
            const trigger = page.locator("button.primary-button").first();

            if ((await trigger.count()) === 0) {
                return null;
            }

            return attemptOpen(page, async () => {
                await trigger.click({ timeout: 10_000 });

                return "button.primary-button (create user)";
            });
        },
        ["Opened from the users list rather than by URL: the create form is a modal, not a page."]
    );

    /* 3. Notifications panel. */
    await page.goto(`${ BASE_URL }/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });

    await settle(page);

    await probe(
        "Header: notifications panel",
        async () => {
            const bell = page.locator("span.icon-notification").last();

            if ((await bell.count()) === 0) {
                return null;
            }

            return attemptOpen(page, async () => {
                await bell.click({ timeout: 10_000 });

                return "span.icon-notification (header bell)";
            });
        },
        [
            "The panel hangs off the header, anchored `top: 70px` with a fixed 340px width capped by `calc(100vw - 32px)` — so it is expected to survive 360px, and this checks that it does.",
        ]
    );

    await context.close();

    if (ids.leadId === null) {
        results.push({
            name: "Id resolution",
            width: 360,
            ok: false,
            notes: ["no lead id was resolved, so the lead-create dropdown probe may have measured a different first dropdown"],
            trigger: null,
            panel: null,
            metrics: null,
        });
    }

    return results;
}

/* -------------------------------------------------------------------------------------------- */
/* The audit                                                                                     */
/* -------------------------------------------------------------------------------------------- */

test.describe("responsive audit", () => {
    test.skip(! ENABLED, "responsive audit is opt-in: set RESPONSIVE_AUDIT=1 and point RESPONSIVE_AUDIT_STATE at a real admin session");

    /**
     * The matrix is one test, not 210. Each cell costs a navigation and a full-page screenshot, and a
     * single sequential body keeps the write-to-disk-after-every-cell discipline simple. The budget is
     * sized for 210 cells at ~4s each with room for a slow datagrid.
     */
    test.setTimeout(60 * 60 * 1000);

    test("matrix: 30 route variants x 7 widths", async ({ browser }) => {
        ensureOutDir();

        if (! fs.existsSync(STATE_PATH) && ! resolveCredentials()) {
            test.skip(true, `no session: ${ STATE_PATH } is absent and no E2E_ADMIN_EMAIL/E2E_ADMIN_PASSWORD or ${ CREDENTIALS_FILE } was found`);
        }

        const variants = VARIANT_FILTER.length ? VARIANTS.filter((variant) => VARIANT_FILTER.includes(variant.slug)) : VARIANTS;

        const matrix: AuditMatrix = {
            generatedAt: new Date().toISOString(),
            baseUrl: BASE_URL,
            height: HEIGHT,
            widths: WIDTHS,
            expectedCells: VARIANTS.length * ALL_WIDTHS.length,
            notes: [
                "Measured against `php artisan serve` with `workers: 1`: the dev server is single-threaded, so any parallelism would queue requests and time out.",
                "Debugbar neutralisation: an init script inserts `#phpdebugbar,.phpdebugbar,.phpdebugbar *{display:none !important;visibility:hidden !important}` into `document.head` (retried until a root exists, re-applied on DOMContentLoaded and load). Each cell additionally asserts that no `phpdebugbar` node still has a non-zero box, and every measurement excludes nodes inside a `phpdebugbar` subtree.",
                "All locators are locale-independent (ids, `button.primary-button`, class and structural selectors): the admin renders Arabic, so English text and placeholder locators do not match.",
                "Overflow is measured on both edges. In an RTL document overflow usually extends past the left edge, so a right-edge-only check would under-report.",
                "`/admin/leads` really is kanban by default (`request()->view_type ?? \"kanban\"` in the leads index view) and `/admin/leads?view_type=table` really is the datagrid, so variants 3 and 4 measure two different layouts.",
                "`/admin/activities` is **table by default**, not calendar: the view sets `viewType: \"{{ request('view-type') }}\" || 'table'`. The calendar is reached with the hyphenated `?view-type=calendar` (underscore `view_type` is ignored and leaves the table), which is the parameter used for the calendar variant; it fires its own `XHR /admin/activities/get?view_type=calendar&startDate=...&endDate=...` and renders a week grid, so the two activity variants are distinct pages.",
                "`/admin/mail` redirects to `/admin/mail/inbox`; the cell records the final URL rather than the requested one.",
                "Every cell records uncaught `pageerror`s and console errors raised while it was loading (see the runtime-error section of this report). A cell that never settles — Vue never mounted, or the network never went idle — is a FAIL rather than a quiet zero, because a dead page has no overflow to measure.",
                "Every cell is measured twice: at rest and again after scrolling to the bottom with `body.is-scrolling-down` engaged. Rules written under that class cannot be seen at scroll position 0, which is how a 16px horizontal scrollbar on every page with a `scroll-reactive-sticky` header bar survived the first sweep. A scrolled overflow above 1px fails the cell, and the offending elements are recorded in `scrolledOverflow.culprits` with tag, classes, position and rect.",
            ],
            cells: [],
        };

        writeJson(PROBES_JSON, []);

        /**
         * The authenticated context is created once and reused: `storageState` is a real Laravel
         * session, and creating a new context per cell would cost more than the measurement.
         */
        const anonymousContext = await createContext(browser, { anonymous: true, width: WIDTHS[0] });

        const authenticatedContext = await createContext(browser, { width: WIDTHS[0] });

        const anonymousPage = await anonymousContext.newPage();

        const page = await authenticatedContext.newPage();

        /**
         * Attached before the first navigation so nothing during start-up is missed, and drained at
         * the head of every cell so each cell reports only its own runtime errors.
         */
        const recorders = new Map<Page, () => RuntimeErrors>([
            [anonymousPage, attachErrorRecorder(anonymousPage)],
            [page, attachErrorRecorder(page)],
        ]);

        /**
         * Resolve ids from a settled authenticated page, and record what the assumed detail-route
         * shapes actually answer.
         */
        await page.goto(`${ BASE_URL }/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });

        await settle(page);

        if (page.url().includes("/admin/login")) {
            await signIn(page);

            matrix.notes.push("The saved session was rejected on arrival; the harness signed in again from the environment credentials and saved nothing back.");
        } else {
            matrix.notes.push(`The saved session at ${ STATE_PATH } authenticated on arrival; no sign-in was needed.`);
        }

        const ids = await resolveIds(page, matrix.notes);

        await probeAssumedRouteShapes(page, ids, matrix.notes);

        persist(matrix);

        let cellIndex = 0;

        if (variants.length !== VARIANTS.length || WIDTHS.length !== ALL_WIDTHS.length) {
            matrix.notes.push(`This run was narrowed on purpose: variants ${ variants.map((variant) => variant.slug).join(", ") } at widths ${ WIDTHS.join(", ") }. Expected full sweep is ${ VARIANTS.length } variants x ${ ALL_WIDTHS.length } widths = ${ matrix.expectedCells } cells.`);
        }

        for (const variant of variants) {
            for (const width of WIDTHS) {
                cellIndex += 1;

                const target = variant.anonymous ? anonymousPage : page;

                const drainRuntimeErrors = recorders.get(target) ?? (() => ({ pageErrors: [], consoleErrors: [] }));

                drainRuntimeErrors();

                await target.setViewportSize({ width, height: HEIGHT });

                const resolvedPath = typeof variant.path === "function" ? variant.path(ids) : variant.path;

                const cell: AuditCell = {
                    index: cellIndex,
                    variantSlug: variant.slug,
                    variantLabel: variant.label,
                    path: resolvedPath,
                    width,
                    height: HEIGHT,
                    url: "",
                    status: "n/a",
                    redirectedToLogin: false,
                    settled: false,
                    screenshot: null,
                    error: null,
                    metrics: null,
                    scrolledOverflow: null,
                    pageErrors: [],
                    consoleErrors: [],
                    verdict: "PASS",
                    failReasons: [],
                };

                /**
                 * A detail variant with an unresolved id has nothing to load. Recorded as an ERROR
                 * cell rather than dropped, so the matrix shows the gap instead of hiding it.
                 */
                if (variant.needs && ! ids[variant.needs]) {
                    cell.verdict = "ERROR";
                    cell.error = `unresolved id: ${ variant.needs }`;
                    cell.failReasons.push(cell.error);
                    matrix.cells.push(cell);
                    persist(matrix);

                    continue;
                }

                try {
                    let response = await target.goto(`${ BASE_URL }${ resolvedPath }`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });

                    /**
                     * Re-authenticate once when the session expires or is rejected. The password is
                     * read from the environment at that moment; it is never stored.
                     */
                    if (! variant.anonymous && target.url().includes("/admin/login")) {
                        await signIn(target);

                        response = await target.goto(`${ BASE_URL }${ resolvedPath }`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });
                    }

                    cell.status = response ? response.status() : "no response";

                    cell.settled = await settle(target);

                    cell.url = target.url().replace(BASE_URL, "");

                    cell.redirectedToLogin = cell.url.includes("/admin/login");

                    if (variant.prepare) {
                        const prepared = await variant.prepare(target);

                        const prepareNote = `${ variant.slug }: ${ prepared } before measuring (the table is rendered by a tab that fetches on activation, not on page load).`;

                        if (prepared) {
                            if (! matrix.notes.includes(prepareNote)) {
                                matrix.notes.push(prepareNote);
                            }

                            /** The interaction may mount components and fire XHRs of its own. */
                            cell.settled = (await settle(target)) && cell.settled;
                        } else {
                            cell.failReasons.push(`interaction required before measuring (\`${ variant.slug }\`) but no trigger was found`);
                        }
                    }

                    cell.metrics = await target.evaluate(measurePage);

                    const shot = path.join(SHOTS_DIR, `${ variant.slug }-${ width }.png`);

                    await target.screenshot({ path: shot, fullPage: true }).catch((error: Error) => {
                        matrix.notes.push(`screenshot for ${ variant.slug }@${ width } failed: ${ error.message.split("\n")[0] }`);
                    });

                    cell.screenshot = shot;

                    /**
                     * Second measurement, at the bottom of the scroll.
                     *
                     * Taken after the screenshot so the rest-state artifacts stay as they were, and
                     * before the verdict is decided so the scrolled overflow can fail the cell. A
                     * throw here is a harness failure, not a quiet zero: a cell whose scrolled state
                     * was never measured must not read as one that passed it.
                     */
                    try {
                        cell.scrolledOverflow = await scrolledState(target);
                    } catch (error) {
                        cell.failReasons.push(`scrolled measurement failed: ${ (error as Error).message.split("\n")[0] }`);
                    }

                    const statusNumber = typeof cell.status === "number" ? cell.status : Number.parseInt(String(cell.status), 10);

                    if (! Number.isFinite(statusNumber) || statusNumber < 200 || statusNumber >= 300) {
                        cell.failReasons.push(`HTTP ${ cell.status }`);
                    }

                    if (cell.redirectedToLogin && ! variant.anonymous) {
                        cell.failReasons.push("bounced to /admin/login");
                    }

                    if (cell.metrics.overflowPx > 1) {
                        cell.failReasons.push(`page overflow ${ cell.metrics.overflowPx }px`);
                    }

                    if (cell.scrolledOverflow && cell.scrolledOverflow.overflowPxScrolled > 1) {
                        cell.failReasons.push(
                            `page overflow ${ cell.scrolledOverflow.overflowPxScrolled }px while scrolled` +
                                ` (${ cell.scrolledOverflow.applied ? "body.is-scrolling-down engaged" : "scroll state not engaged" })` +
                                (cell.scrolledOverflow.culprits[0]
                                    ? `, worst \`${ cell.scrolledOverflow.culprits[0].selector }\` ${ cell.scrolledOverflow.culprits[0].position } ${ cell.scrolledOverflow.culprits[0].overshootPx }px past the ${ cell.scrolledOverflow.culprits[0].side } edge`
                                    : "")
                        );
                    }

                    const runtime = drainRuntimeErrors();

                    cell.pageErrors = runtime.pageErrors;

                    cell.consoleErrors = runtime.consoleErrors;

                    if (! cell.settled) {
                        cell.failReasons.push("did not settle: Vue never mounted or the network never went idle");
                    }

                    if (cell.pageErrors.length) {
                        cell.failReasons.push(`${ cell.pageErrors.length } uncaught page error(s)`);
                    }

                    cell.verdict = cell.failReasons.length ? "FAIL" : "PASS";
                } catch (error) {
                    cell.verdict = "ERROR";
                    cell.error = (error as Error).message.split("\n")[0];

                    cell.failReasons.push(`harness error: ${ cell.error }`);
                }

                matrix.cells.push(cell);

                persist(matrix);
            }
        }

        /**
         * Say out loud which shared-table callers did not actually measure a table. A cell whose page
         * is complete but whose table is absent, hidden behind an inactive tab, or empty still
         * measures as a clean zero overflow, and silence there reads as "that route is fine" when it
         * means "that route's table was never exercised".
         */
        const tableGaps: string[] = [];

        for (const variant of variants.filter((entry) => entry.usesSharedTable)) {
            const variantCells = matrix.cells.filter((cell) => cell.variantSlug === variant.slug && cell.metrics);

            if (! variantCells.length) {
                continue;
            }

            const withoutWrapper = variantCells.filter((cell) => cell.metrics!.sharedTable.wrappers === 0).map((cell) => cell.width);

            const hidden = variantCells.filter((cell) => cell.metrics!.sharedTable.wrappers > 0 && cell.metrics!.sharedTable.visible === 0).map((cell) => cell.width);

            const empty = variantCells.filter((cell) => cell.metrics!.sharedTable.visible > 0 && ! cell.metrics!.sharedTable.rendered).map((cell) => cell.width);

            if (withoutWrapper.length) {
                tableGaps.push(`\`${ variant.slug }\` rendered **no** \`x-admin::table\` wrapper at ${ withoutWrapper.join(", ") }px — the route's table is created by an interaction this sweep does not drive, so those cells measure the surrounding page only.`);
            }

            if (hidden.length) {
                tableGaps.push(`\`${ variant.slug }\` carried the shared table but never made it visible at ${ hidden.join(", ") }px (its tab stayed inactive, or the content it needs is missing), so a \`display:none\` table is not what those cells measured.`);
            }

            if (empty.length) {
                tableGaps.push(`\`${ variant.slug }\` made the shared table visible with **zero rows** at ${ empty.join(", ") }px: the wrapper and its columns were measured, the body was empty.`);
            }
        }

        if (tableGaps.length) {
            matrix.notes.push(`Shared-table coverage gaps (not failures — an absent, hidden or empty table cannot overflow): ${ tableGaps.join(" ") }`);
        }

        await anonymousContext.close();

        await authenticatedContext.close();

        persist(matrix);
    });

    test("interaction probes at width 360", async ({ browser }) => {
        ensureOutDir();

        if (! fs.existsSync(STATE_PATH) && ! resolveCredentials()) {
            test.skip(true, `no session: ${ STATE_PATH } is absent and no credentials were supplied`);
        }

        test.setTimeout(6 * 60 * 1000);

        /**
         * The ids the probes need come from the same resolution the matrix uses, so the probes run
         * against whichever lead is actually first rather than a guess.
         */
        const context = await createContext(browser, { width: 360 });

        const page = await context.newPage();

        await page.goto(`${ BASE_URL }/admin/dashboard`, { waitUntil: "domcontentloaded", timeout: NAV_TIMEOUT });

        await settle(page);

        if (page.url().includes("/admin/login")) {
            await signIn(page);
        }

        const ids = await resolveIds(page, []);

        await context.close();

        const probes = await runInteractionProbes(browser, ids);

        writeJson(PROBES_JSON, probes);

        if (fs.existsSync(MATRIX_JSON)) {
            const matrix = JSON.parse(fs.readFileSync(MATRIX_JSON, "utf8")) as AuditMatrix;

            fs.writeFileSync(MATRIX_MD, renderMarkdown(matrix, probes), "utf8");
        }
    });
});
