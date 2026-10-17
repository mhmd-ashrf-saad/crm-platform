import { defineConfig, devices } from "@playwright/test";
import dotenv from "dotenv";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export const TESTS_ROOT_PATH = __dirname;
export const STATE_DIR_PATH = `${ TESTS_ROOT_PATH }/.state/`;
export const ADMIN_AUTH_STATE_PATH = `${ STATE_DIR_PATH }/admin-auth.json`;

dotenv.config({ path: path.resolve(__dirname, "../../../../../.env") });

export default defineConfig({
    /**
     * Resolved from this file rather than from the directory the command was run in: a relative
     * path sent Playwright looking for specs beside whatever happened to be the working
     * directory, which is how an editor or a root-level invocation found none.
     */
    testDir: path.resolve(__dirname, "tests"),

    /**
     * Sized for a cold CI runner, not for this laptop.
     *
     * The budget covers fixture setup as well as the test body: `adminPage` opens a browser
     * context, navigates to the dashboard and may sign in, which on its own can take several
     * seconds - and Playwright charges that to the same clock as the test, so it belongs in the
     * arithmetic rather than being assumed to fit inside the slack.
     *
     * The arithmetic, from measurements rather than taste:
     *
     * - A create-or-update case performs 6-7 full document loads. `lead.spec.ts`'s "create a new
     *   lead" goes dashboard -> persons list -> person form -> products list -> product form ->
     *   leads list -> lead form before it fills a single lead field, and under the emulation below
     *   it was killed at 30.2s doing exactly that, on the Title fill at `LeadPage.ts:61`.
     * - The CI trace of the failing run shows what those loads cost there: `GET
     *   /admin/contacts/organizations 200 in 2704ms` for a list page, `870ms` for a create page.
     *   Seven loads at 2.7s is ~19s of navigation before any field is filled.
     * - Reproducing that runner locally by holding the admin bundle 3s per load
     *   (`E2E_SLOW_BUNDLE=3000`, scratch harness only), the cost distribution is plain: eleven
     *   cases pass at 14.1-25.0s, and five - every lead case plus both update-import cases - ended
     *   at exactly 30.2-30.3s with `Test timeout of 30000ms exceeded`, each still waiting for a
     *   locator (`Title *`, the lead link, the delete icon, `Save Import` twice). The slowest case
     *   that did pass took 25.0s here and 27.0s in the run that first showed this split - 3s under
     *   the old ceiling, which is luck rather than margin. Two of the five, lead update and delete,
     *   were collateral: they wait for the record lead create had not been given time to make, so
     *   the split was one budget failure plus its fallout.
     *
     * So 30s sat inside this suite's natural spread, and the failures it produced were the budget
     * expiring, not assertions failing. Once the budget stops cutting them off, the same emulation
     * measures what the cases actually cost: 39.5s for lead create and 35-38s for both update-import
     * cases. So 90s leaves ~2.3x over the slowest case measured here and ~10x the slowest case with
     * no delay (8.8s) - the headroom a runner slower than this laptop needs, and the reason 90s
     * rather than 60s.
     *
     * The price of that patience is worst-case failure time: a locator action (`click`, `fill`) that
     * waits for an element which never appears rides the test timeout, not the expectation timeout,
     * so it can now burn up to 90s before reporting where it used to burn 30s. Assertions are
     * unaffected - they still fail at the 15s expectation timeout, with their own message.
     *
     * What this does not do: it does not make the product faster, and it does not replace the
     * hydration gate in `fixtures/AdminFixtures.ts` - a value typed before Vue mounts is still
     * discarded. It stops a slow runner from being reported as a product failure. No assertion was
     * weakened and no spec or page object was touched; only the patience changed.
     */
    timeout: 90 * 1000,

    /**
     * Raised with the test timeout, and still kept well below it: at 15s a failing assertion
     * reports its own message and locator long before the 90s budget expires, instead of being cut
     * off by the test timeout, which reports nothing about what was expected. 15s is also sized
     * against a slow runner's page load - the CI trace above has single requests at 2.7s - so an
     * assertion that depends on a fresh document is not racing the request that produces it.
     */
    expect: { timeout: 15 * 1000 },

    outputDir: "./test-results",

    fullyParallel: false,

    workers: 1,

    forbidOnly: !!process.env.CI,

    retries: 0,

    reportSlowTests: null,

    reporter: [
        [
            "html",
            {
                outputFolder: "./playwright-report",
            },
        ],
    ],

    use: {
        baseURL: `${process.env.APP_URL}/`.replace(/\/+$/, "/"),
        screenshot: { mode: "only-on-failure", fullPage: true },
        video: "retain-on-failure",
        trace: "retain-on-failure",
    },

    projects: [
        {
            name: "chromium",
            use: { ...devices["Desktop Chrome"] },
        },
    ],
});
