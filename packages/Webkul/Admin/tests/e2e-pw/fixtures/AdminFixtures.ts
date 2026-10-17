import { test as base, expect, type Page } from "@playwright/test";
import fs from "fs";
import { ADMIN_AUTH_STATE_PATH } from "../playwright.config";

/**
 * The admin layout mounts the Vue app on the window `load` event:
 *
 *     window.addEventListener("load", function () { app.mount("#app"); });
 *     -- packages/Webkul/Admin/src/Resources/views/components/layouts/index.blade.php
 *
 * Every form control is server-rendered Blade before that: each input is wrapped in a vee-validate
 * `<v-field>` and bound with `v-bind="field"`, so the mount re-renders the input from vee-validate's
 * state. A Playwright action that runs in the window between the navigation committing and
 * `app.mount()` therefore writes into a DOM node Vue has not taken over yet, and the mount replaces
 * the typed value with the still-empty component state. Only the first field acted on after a
 * navigation is exposed to this, which is exactly the observed failure: the create forms lose their
 * `Name` value, client-side validation answers "The Name field is required", no request is ever sent,
 * and the test then times out waiting for a list page that was never reached.
 *
 * Measured on a scratch instance by holding the admin bundle at the network layer, filling
 * `input[name="name"]` while the page was un-hydrated, and releasing it:
 *
 *     vue mounted before fill: false
 *     DOM value right after fill: "Pre Hydration Victim"
 *     DOM value after mount: ""
 *
 * This gate hides `#app` from interaction until Vue reports the mount, so Playwright's own
 * actionability wait holds every fill/click/select until the page is interactive. It is a test-
 * harness workaround: the product defect (a value typed before hydration is silently discarded, and
 * the pre-mount markup leaks `v-bind` / `:class` directives) lives in the core admin layout, which
 * this repository must not modify so upstream stays mergeable. See bead crm-b73.
 *
 * `visibility: hidden` rather than `display: none` is deliberate, and the difference is not
 * cosmetic. Actionability requires a *visible* element, so both properties block an early fill
 * equally — but only `display: none` also removes `#app` from layout. A component that measures its
 * own container during `mounted()` then measures zero, and the geometry it computes from that is
 * wrong for the life of the page. That is not hypothetical: a `display: none` gate failed
 * `tests/leads-toolbar.spec.ts` ("the settings control stays in place when switching between
 * views") deterministically, 4 failures in 4 fresh-database runs, on
 * `expect(Math.abs(kanbanX - tableX)).toBeLessThan(40)` receiving `48.578125`, while the same spec
 * passes with this gate. `visibility: hidden` gives up no actionability and keeps the size the
 * measurement reads.
 *
 * Two properties are what make the workaround hold:
 *
 * - The hiding style has to exist before the body is parsed. This runs at document-start, where
 *   `document.head` does not exist yet, and this page's own `DOMContentLoaded` is deferred by the
 *   very admin bundle the mount waits for — so arming on either one leaves the whole parse window
 *   unguarded, and that is the 39ms fill the gate has to prevent. The style is appended to
 *   `document.documentElement` instead: it exists first, and a `<style>` is applied document-wide
 *   wherever it sits, so it retries only for the few milliseconds before that element appears.
 * - The gate must be incapable of leaving a page permanently blank. The poll gives up after ~30s
 *   and, on giving up, reveals `#app` and retires the style, so an app that never mounts degrades to
 *   the pre-gate behaviour instead of hanging every action until the test timeout.
 */
const HYDRATION_GATE = () => {
    const READY_ATTR = "data-vue-ready";
    const GATE_ATTR = "data-e2e-hydration-gate";
    const TICK_MS = 10;
    const REVEAL_AFTER_MS = 30_000;

    const isMounted = () => {
        const app = document.querySelector("#app") as (Element & { __vue_app__?: unknown }) | null;

        return Boolean(app && app.__vue_app__);
    };

    /**
     * Reveal `#app` for the rest of the document's life. `retireStyle` is for the give-up path: it
     * also removes the hiding style, so even an `#app` that only shows up after the deadline is
     * never hidden again.
     */
    const reveal = (retireStyle = false) => {
        document.querySelector("#app")?.setAttribute(READY_ATTR, "");

        if (retireStyle) {
            document.querySelector(`style[${ GATE_ATTR }]`)?.remove();
        }
    };

    /**
     * Hide `#app` from the instant it is parsed. Appending to `documentElement` rather than `head`
     * is the point: it is the only root that exists at document-start, and it applies the rule to a
     * body that has not been parsed yet.
     */
    const installGateStyle = () => {
        const root = document.documentElement;

        if (! root) {
            return false;
        }

        const style = document.createElement("style");
        style.setAttribute(GATE_ATTR, "");
        style.textContent = `#app:not([${ READY_ATTR }]) { visibility: hidden !important; }`;
        root.appendChild(style);

        return true;
    };

    const startedAt = performance.now();

    const poll = () => {
        if (isMounted()) {
            reveal();
            return;
        }

        if (performance.now() - startedAt >= REVEAL_AFTER_MS) {
            reveal(true);
            return;
        }

        setTimeout(poll, TICK_MS);
    };

    if (! installGateStyle()) {
        // `documentElement` is absent for the first instant of a document only. If it never turns up
        // nothing is hidden, and the poll's give-up would reveal `#app` regardless.
        let attempts = 0;
        const retryInstall = () => {
            if (! installGateStyle() && ++attempts < 100) {
                setTimeout(retryInstall, TICK_MS);
            }
        };
        retryInstall();
    }

    poll();
};

import { AdminPage as Admin } from "../pages/AdminPage";

interface AdminPage extends Page {
    fillInTinymce: (iframeSelector: string, content: string) => Promise<void>;
}

type AdminFixtures = {
    adminPage: AdminPage;
};

export const test = base.extend<AdminFixtures>({
    adminPage: async ({ browser }, use) => {
        const authExists = fs.existsSync(ADMIN_AUTH_STATE_PATH);

        const context = await browser.newContext(
            authExists ? { storageState: ADMIN_AUTH_STATE_PATH } : {}
        );

        await context.addInitScript(HYDRATION_GATE);

        const page = await context.newPage();
        const admin = await new Admin(page)


        if (!authExists) {
            /**
            * Authenticate the admin user.
            */
            await admin.adminLogin("admin@example.com", "admin123");

            /**
            * Save authentication state to a file.
            */
            await context.storageState({ path: ADMIN_AUTH_STATE_PATH });
        } else {
            /**
            * Navigate to the dashboard.
            */
            await page.goto("admin/dashboard");
        }

        if (page.url().includes("admin/login")) {
            /**
            * Authenticate the admin user.
            */
            await admin.adminLogin("admin@example.com", "admin123");

            /**
            * Save authentication state to a file.
            */
            await context.storageState({ path: ADMIN_AUTH_STATE_PATH });
        }

        /**
        * Extend the page object with custom methods.}
        
        */
        (page as AdminPage).fillInTinymce = async function (
            iframeSelector: string,
            content: string
        ) {
            await page.waitForSelector(iframeSelector);
            const iframe = page.frameLocator(iframeSelector);
            const editorBody = iframe.locator("body");
            await editorBody.click();
            await editorBody.press("Control+a");
            await editorBody.press("Backspace");
            await editorBody.pressSequentially(content);
            await expect(editorBody).toHaveText(content);
        };

        await use(page as AdminPage);

        /**
         * A close error is swallowed on purpose. With `trace: "retain-on-failure"` the context's
         * trace is still being written as it closes, and the two race — the loser throws `ENOENT`
         * on a recording file that has already been moved. That is teardown noise: it says nothing
         * about the behaviour under test, but thrown from a fixture it fails a test whose
         * assertions all passed, and hides the real result.
         */
        try {
            await context.close();
        } catch {
            // The context is going away regardless; a cleanup failure is not a test failure.
        }
    },
});

export { expect };