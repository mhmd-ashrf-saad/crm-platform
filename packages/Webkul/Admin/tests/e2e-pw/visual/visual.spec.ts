import { expect, test } from "../fixtures/AdminFixtures";
import { BASE_URL } from "../fixtures/env";
import {
    HEIGHT,
    MODES,
    SURFACES,
    VR_LOCALE,
    snapshotName,
    type VrMode,
    type VrSurface,
    type VrWidth,
} from "./surfaces";
import type { BrowserContext, Page } from "@playwright/test";

/**
 * The capture half of the visual-regression harness.
 *
 * Two commands, and they are the same command with one flag between them:
 *
 *     npm run vr:baseline   # playwright test --config=…/playwright.visual.config.ts --update-snapshots
 *     npm run vr:compare    # playwright test --config=…/playwright.visual.config.ts
 *
 * `--update-snapshots` is Playwright's own regenerate switch: it writes every capture that has no
 * baseline, and rewrites the ones that no longer match. Without it, a missing baseline is a failure,
 * which is the behaviour a comparison gate must have — a snapshot that silently appears is not a
 * gate.
 *
 * The sign-in path is the suite's, not a second one: the authenticated cells use the `adminPage`
 * fixture from `../fixtures/AdminFixtures`, which signs in through `pages/AdminPage.adminLogin` with
 * the credentials `fixtures/env.ts` resolves and caches the session in `.state/admin-auth.json`.
 * Nothing here re-implements authentication. The anonymous cells are exactly the same browser
 * mechanism with no stored session, because `/admin/login` redirects an authenticated visitor to the
 * dashboard — the only way to photograph the sign-in page is to not be signed in.
 */

/**
 * Wait until the page is worth photographing.
 *
 * Three things have to be true and none of them is implied by `goto` resolving:
 *
 * - **Vue has mounted.** The admin layout renders server-side markup and then re-renders on mount.
 *   Everything the rebrand will touch — the header, the sidebar chrome, the toggle — is added or
 *   rewritten by that mount, so a capture taken before it photographs a document the user never sees.
 * - **Fonts are loaded.** A late webfont changes every glyph box in the image.
 * - **The network is quiet.** Datagrids, chart data and the mega search fetch after load; a page
 *   caught mid-fetch is a shimmer skeleton.
 *
 * Every wait is bounded, and none of them throws: a page that never settles is still captured, and
 * the comparison then fails against its baseline — which is the right outcome and a much better one
 * than a capture that silently never happened.
 */
async function settle(page: Page, extraMs = 250): Promise<void> {
    await page.waitForLoadState("domcontentloaded").catch(() => {});

    await page
        .waitForFunction(() => Boolean((document.querySelector("#app") as { __vue_app__?: unknown } | null)?.__vue_app__), undefined, {
            timeout: 20_000,
        })
        .catch(() => {});

    await page.evaluate(() => document.fonts?.ready).catch(() => {});

    await page.waitForLoadState("networkidle", { timeout: 20_000 }).catch(() => {});

    await page.evaluate(() => window.scrollTo(0, 0)).catch(() => {});

    await page.waitForTimeout(extraMs);
}

/**
 * Apply the colour mode the way the product applies it.
 *
 * `darkMode: 'class'` and the class is server-rendered from the `dark_mode` cookie
 * (`components/layouts/index.blade.php:4`), which the header's `v-dark` component writes when the
 * user toggles. Setting the cookie and loading the page therefore produces exactly the document the
 * toggle produces, and it does so without depending on a click landing on a hydrated component.
 *
 * The cookie is set for the base URL only, and cleared for light mode rather than set to `0`: PHP
 * treats the string `"0"` as falsy, so both work, but clearing is what the suite's own theme spec
 * does (`tests/ui-theme.spec.ts`) and there should be one way of saying "light".
 */
async function applyMode(context: BrowserContext, mode: VrMode): Promise<void> {
    if (mode === "dark") {
        /**
         * `url` only, never `url` and `path` together: Playwright refuses a cookie that carries both.
         * The trailing slash is stripped because the cookie is scoped to the origin, not to a route.
         */
        await context.addCookies([{ name: "dark_mode", value: "1", url: BASE_URL.replace(/\/+$/, "") }]);

        return;
    }

    await context.clearCookies({ name: "dark_mode" });
}

/**
 * The deliberate-perturbation hook, for the sensitivity proof this bead has to paste.
 *
 * `crm-7qu.2` asks for "a deliberate one-pixel CSS change … on one surface". The natural way to do
 * that is to edit a Blade view, and that is closed off here for a reason that has nothing to do with
 * this harness: the instance being photographed is served from the main checkout, where another
 * agent is editing `packages/Webkul/Admin/src/**` at the same time. A temporary edit to a shared
 * working tree, reverted seconds later, would race that writer and could revert their work.
 *
 * So the perturbation is applied to the rendered document instead: with `VR_SENSITIVITY=<slug>`, one
 * surface gets a 1px padding on `#app` before the capture. It is the same class of change — the
 * layout moves by one pixel, on one surface, in the real page — and it proves the only thing that
 * has to be proven: that the comparison can see a one-pixel change and names the surface it is on.
 */
async function maybePerturb(page: Page, surface: VrSurface): Promise<void> {
    if (process.env.VR_SENSITIVITY !== surface.slug) {
        return;
    }

    console.log(`[sensitivity] applying a deliberate 1px padding to #app on "${surface.slug}"`);

    await page.addStyleTag({ content: "#app { padding-top: 1px !important; }" });

    await page.waitForTimeout(100);
}

/**
 * Every avatar is masked before comparison, and this is a product defect, not a harness convenience.
 *
 * `components/avatar/index.blade.php` computes its colours with `Math.random()`:
 *
 *     colorClasses() {
 *         let index = Math.floor(Math.random() * this.colors.background.length);
 *
 * so the same person is yellow on one page load and blue on the next. Measured on the dashboard,
 * comparing two runs of unchanged code: a single 36px-wide column of the "top persons" list differed
 * by 3,210 pixels — the same four people, the same initials, four different colours. The record
 * detail's 36x36px avatar differed by 1,065 pixels the same way. Nothing else about the page moved.
 *
 * A gate that fails on its own randomness teaches people to re-baseline on reflex, so the random
 * region is masked (Playwright paints it over) and the rest of the page is still compared pixel for
 * pixel. The proper fix — deriving the colour from the name, which is stable and readable — is an
 * application change and belongs with the rebrand work, not inside the baseline harness.
 */
const RANDOMISED_AVATAR = "div.rounded-full.min-w-9";

/**
 * The mega-search modal's results list, masked, and the honest reason it has to be.
 *
 * It re-renders on a debounced keystroke and its rows animate in, so the frame that lands in the
 * file depends on where in that debounce the capture happened to arrive: two runs of unchanged code
 * differed by 1,800-3,000 pixels inside this one element, and typing a fixed query first (`example`)
 * reduced the spread without removing it. Everything else about the modal is compared — the input,
 * the keyboard hint, the tabs and their counts, the footer, the close control and the backdrop —
 * so the surface is still gated; what is not gated is the one list whose render timing is not stable.
 */
const SEARCH_RESULTS_LIST = 'div[class*="max-h-[50vh]"]';

/**
 * Photograph one cell, after checking that the page is the one the matrix thinks it is.
 *
 * The two assertions are not decoration. A capture in the wrong language, or one labelled `dark`
 * that is actually light, is a baseline that will silently pass every later comparison while
 * photographing something nobody asked about.
 */
async function capture(page: Page, surface: VrSurface, width: VrWidth, mode: VrMode): Promise<void> {
    const lang = await page.evaluate(() => document.documentElement.lang);

    expect(lang, `"${ surface.slug }" must be captured in ${ VR_LOCALE } (the instance's interface locale)`).toBe(VR_LOCALE);

    if (surface.honoursDark) {
        const isDark = await page.evaluate(() => document.documentElement.classList.contains("dark"));

        expect(isDark, `"${ surface.slug }" must render ${ mode } before it is captured`).toBe(mode === "dark");
    }

    const name = snapshotName(surface, width, mode);

    if (surface.kind === "page") {
        /**
         * A page is captured as a **viewport**, with the viewport grown until the whole document
         * fits — never with `fullPage: true`.
         *
         * Measured, because the difference is not cosmetic. Three consecutive screenshots of the same
         * untouched, already-settled dashboard at 360px:
         *
         *     fullPage: f2b165aa a11ecfa6 36189f94      <- different every time, in one session
         *     viewport: 1cc62c56 1cc62c56 1cc62c56      <- identical, and identical across runs
         *
         * `fullPage` resizes the viewport to the document height for the shot and restores it after.
         * ApexCharts listens for that resize and re-renders — with its entry animation — so the frame
         * that lands in the file is a frame of a re-drawn chart, at whatever moment it was caught.
         * Growing the viewport *is* the resize, but it happens once, before the stability gate, so the
         * charts re-render once and are then stable like everything else.
         */
        await growViewportToFit(page, width);
    }

    const target = surface.kind === "element" ? page.locator(surface.selector!).first() : page;

    const shoot = () => (surface.kind === "element" ? target.screenshot(SHOOT_OPTIONS) : page.screenshot(SHOOT_OPTIONS));

    await waitForStablePixels(shoot, `${ surface.slug } @ ${ width } ${ mode }`);

    await expect(target).toHaveScreenshot(name, {
        timeout: 60_000,
        mask: [page.locator(RANDOMISED_AVATAR), page.locator(SEARCH_RESULTS_LIST)],
    });
}

/**
 * What the stability gate photographs, matching what `toHaveScreenshot` will photograph.
 *
 * `caret: "hide"` matters here as much as it does in the assertion: with a blinking caret visible
 * the gate never sees two identical frames, and a caret captured by chance differs from one captured
 * by `toHaveScreenshot`, which always hides it.
 */
const SHOOT_OPTIONS = { animations: "disabled", caret: "hide" } as const;

/**
 * Grow the viewport until the document fits, so a normal viewport screenshot contains the whole page.
 *
 * Bounded at two passes: the first resize can itself change the layout (charts reflow, tables wrap),
 * so the height is measured again after it and the viewport adjusted once more. The ceiling keeps a
 * runaway page from asking for a 40,000px-tall browser surface.
 */
async function growViewportToFit(page: Page, width: VrWidth): Promise<void> {
    for (let pass = 0; pass < 2; pass++) {
        const contentHeight = await page.evaluate(() =>
            Math.max(document.body.scrollHeight, document.documentElement.scrollHeight)
        );

        const height = Math.min(Math.max(contentHeight, HEIGHT), 6000);

        const current = page.viewportSize();

        if (current && current.height === height && current.width === width) {
            break;
        }

        await page.setViewportSize({ width, height });

        await page.waitForLoadState("networkidle", { timeout: 10_000 }).catch(() => {});

        await page.waitForTimeout(300);
    }

    await page.evaluate(() => window.scrollTo(0, 0)).catch(() => {});
}

/**
 * Wait until two consecutive screenshots of the same target are byte-identical.
 *
 * "Network idle plus 1200ms" was not enough, and the measurement is worth recording because it is
 * the difference between a gate and a coin toss. Comparing two full runs against themselves:
 *
 * - `dashboard` differed inside a single 36x178px column — one bar of the "leads over time" chart
 *   still growing when the shot was taken;
 * - `record-detail` differed in one 36x36px square — a late-loading avatar-sized element;
 * - `modal` and `mobile-sidebar` differed behind their overlays for the same reason;
 * - and the page *height* differed between runs (3010 vs 3091px) on the narrow widths, which is the
 *   same late layout in another form.
 *
 * None of that is the product misbehaving; it is a photograph taken a few hundred milliseconds too
 * early. So the harness settles the pixels rather than guessing a duration: capture, wait, capture
 * again, and only hand the frame to `toHaveScreenshot` once two frames agree. A surface that never
 * stops moving is still photographed after the last attempt, and the comparison then fails against
 * its baseline — which is the honest outcome for a page that cannot hold still.
 */
async function waitForStablePixels(shoot: () => Promise<Buffer>, label: string, attempts = 6): Promise<void> {    let previous = await shoot();

    for (let attempt = 1; attempt <= attempts; attempt++) {
        await new Promise((resolve) => setTimeout(resolve, 300));

        const current = await shoot();

        if (current.equals(previous)) {
            return;
        }

        previous = current;
    }

    console.warn(`[stability] "${ label }" was still changing after ${ attempts } attempts; capturing anyway.`);
}

/**
 * Refuse to photograph an authenticated surface without a session.
 *
 * This guard exists because its absence was measured: run against a stale demo credential, the
 * sign-in fixture fills the form, the request is refused, `/admin/dashboard` redirects to
 * `/admin/login`, and the harness happily wrote the *login page* into a file named
 * `ar-dashboard-1440-light.png`. Every later comparison would then have compared the login page to
 * itself and passed, while the dashboard was never photographed at all.
 *
 * The layout's own landmark is what is checked, not a URL: `header` is rendered by the admin shell
 * (`components/layouts/header/index.blade.php`) and by no anonymous page, so its absence means there
 * is no signed-in layout to capture.
 */
async function requireSession(page: Page, surface: VrSurface): Promise<void> {
    if (page.url().includes("/admin/login")) {
        throw new Error(
            `"${ surface.slug }" needs a signed-in session, but the browser is on /admin/login. ` +
                "The sign-in fixture did not authenticate: check E2E_ADMIN_EMAIL / E2E_ADMIN_PASSWORD " +
                "against the instance being photographed (fixtures/env.ts resolves them, and a stale " +
                "credential fails exactly here rather than producing a login-page baseline)."
        );
    }

    await expect(page.locator("header").first(), `${ surface.slug } must render the admin shell`).toBeVisible({
        timeout: 15_000,
    });
}

/**
 * Anonymous surfaces: the pages an unauthenticated visitor sees.
 *
 * A context with no stored session, which is not a second authentication mechanism — it is the
 * absence of one. The mode cookie still applies, because the server renders the `dark` class from
 * it; what the anonymous layout does *not* do with that cookie is read it (see
 * `honoursDark` in `./surfaces.ts`), so both modes capture the same light document for these
 * surfaces, and the coverage report says so.
 */
test.describe("visual regression — anonymous surfaces", () => {
    for (const surface of SURFACES.filter((candidate) => candidate.anonymous)) {
        for (const width of surface.widths) {
            for (const mode of MODES) {
                test(`${ surface.slug } @ ${ width } ${ mode }`, async ({ browser }) => {
                    const context = await browser.newContext({ viewport: { width, height: HEIGHT } });

                    await applyMode(context, mode);

                    const page = await context.newPage();

                    const response = await page.goto(surface.path, { waitUntil: "domcontentloaded" });

                    if (surface.slug === "error-404") {
                        expect(response?.status(), "the 404 surface must actually be a 404").toBe(404);
                    } else {
                        expect(response?.status(), `${ surface.path } must load`).toBeLessThan(400);
                    }

                    await settle(page, surface.settleMs);

                    await maybePerturb(page, surface);

                    await capture(page, surface, width, mode);

                    await context.close();
                });
            }
        }
    }
});

/**
 * Authenticated surfaces, through the suite's own sign-in fixture.
 *
 * The mode cookie is applied before each navigation, so every cell renders its own colour mode from
 * the server rather than inheriting whatever the previous cell left behind.
 */
test.describe("visual regression — authenticated surfaces", () => {
    for (const surface of SURFACES.filter((candidate) => ! candidate.anonymous)) {
        for (const width of surface.widths) {
            for (const mode of MODES) {
                test(`${ surface.slug } @ ${ width } ${ mode }`, async ({ adminPage }) => {
                    test.skip(Boolean(surface.skip), surface.skip);

                    await adminPage.setViewportSize({ width, height: HEIGHT });

                    await applyMode(adminPage.context(), mode);

                    const response = await adminPage.goto(surface.path, { waitUntil: "domcontentloaded" });

                    expect(response?.status(), `${ surface.path } must load`).toBeLessThan(400);

                    await settle(adminPage, surface.settleMs);

                    /**
                     * Before the interaction, not after: `public-web-form` navigates away from the
                     * admin shell on purpose, so the signed-in layout has to be proven while it is
                     * still on screen.
                     */
                    await requireSession(adminPage, surface);

                    if (surface.prepare) {
                        await surface.prepare(adminPage);
                    }

                    /**
                     * The interaction surfaces load a fresh document of their own (the public form)
                     * or finish animating in (a drawer, a modal), so they settle again afterwards.
                     * A surface without `prepare` has already settled and is left alone.
                     */
                    if (surface.prepare || surface.settleMs) {
                        await settle(adminPage, surface.settleMs ?? 400);
                    }

                    await maybePerturb(adminPage, surface);

                    await capture(adminPage, surface, width, mode);
                });
            }
        }
    }
});
