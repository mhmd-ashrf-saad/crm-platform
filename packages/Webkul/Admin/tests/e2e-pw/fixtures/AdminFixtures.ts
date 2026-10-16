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
 * This gate keeps `#app` out of layout until Vue reports the mount, so Playwright's own
 * actionability wait holds every fill/click/select until the page is interactive. It is a test-
 * harness workaround: the product defect (a value typed before hydration is silently discarded, and
 * the pre-mount markup leaks `v-bind` / `:class` directives) lives in the core admin layout, which
 * this repository must not modify so upstream stays mergeable. See bead crm-b73.
 */
const HYDRATION_GATE = () => {
    const READY_ATTR = "data-vue-ready";

    const isMounted = () => {
        const app = document.querySelector("#app") as (Element & { __vue_app__?: unknown }) | null;

        return Boolean(app && app.__vue_app__);
    };

    const arm = () => {
        if (! document.head) {
            return false;
        }

        const style = document.createElement("style");
        style.setAttribute("data-e2e-hydration-gate", "");
        style.textContent = `#app:not([${ READY_ATTR }]) { display: none !important; }`;
        document.head.appendChild(style);

        let attempts = 0;
        const poll = () => {
            const app = document.querySelector("#app");

            if (app && isMounted()) {
                app.setAttribute(READY_ATTR, "");
                return;
            }

            // Give up after ~30s so a bundle that never mounts cannot leave a timer running.
            if (++attempts < 3000) {
                setTimeout(poll, 10);
            }
        };
        poll();

        return true;
    };

    if (! arm()) {
        document.addEventListener("DOMContentLoaded", arm, { once: true });
    }
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