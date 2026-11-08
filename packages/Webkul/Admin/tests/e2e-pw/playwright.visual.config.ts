import { defineConfig } from "@playwright/test";
import path from "path";
import { fileURLToPath } from "url";

import { ADMIN_AUTH_STATE_PATH, BASE_URL, STATE_DIR_PATH, TESTS_ROOT_PATH } from "./fixtures/env";
import { VR_LOCALE } from "./visual/surfaces";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Re-exported for the same reason `playwright.config.ts` does it: the sign-in state file and the base
 * URL come from one place, so the visual run cannot authenticate against one instance and photograph
 * another.
 */
export { ADMIN_AUTH_STATE_PATH, BASE_URL, STATE_DIR_PATH, TESTS_ROOT_PATH, VR_LOCALE };

/**
 * The visual-regression suite, kept in its own config rather than added to `playwright.config.ts`.
 *
 * `ci.yml`'s browser workflow runs `npx playwright test --config=tests/e2e-pw/playwright.config.ts`
 * in 15 shards. Anything living under that config's `testDir` runs in all fifteen of them, and a
 * screenshot comparison is exactly the thing `crm-7qu.2` says must not be added to every shard. A
 * separate config with its own `testDir` is what keeps the comparison out of the shards: the shard
 * command does not know this file exists. The CI plan for running it deliberately lives in
 * `visual/README.md`; no workflow YAML is touched by this bead.
 *
 * The other difference from `playwright.config.ts` is the missing `globalSetup`/`globalTeardown`.
 * Those exist to pin the admin UI locale through `core_config`, which is a database write. This
 * harness photographs an instance and must not write to it, so the locale is asserted per capture
 * instead of imposed — see `visual/surfaces.ts`.
 */
export default defineConfig({
    testDir: path.resolve(__dirname, "visual"),

    /** The inventory and the captures share one registry, so both live under one directory. */
    testMatch: "**/*.spec.ts",

    /**
     * Sized for the whole matrix rather than for one cell: a capture is a page load (measured at
     * 0.5-2.5s against `php artisan serve`) plus a settle plus the encode of a full-page PNG.
     */
    timeout: 120 * 1000,

    expect: {
        timeout: 30 * 1000,

        toHaveScreenshot: {
            /**
             * One threshold for the whole matrix: zero per-pixel tolerance, and forty differing
             * pixels of slack — 0.003% of a 1440px-wide dashboard.
             *
             * The slack is not a guess. With every known-random region masked (the avatars, see
             * `visual.spec.ts`), two runs of unchanged code still differed by 15-25 pixels on the
             * dashboard: single-pixel horizontal runs on rows 335-336 and 904-906, i.e. two card
             * borders sitting one pixel lower in one run than the other. That is chart height
             * rounding, not a design change, and chasing it with more settling produced nothing.
             *
             * The number is calibrated against what the gate has to catch: the deliberate one-pixel
             * padding change used for the sensitivity proof moves **9,041** pixels on the same
             * surface, 226 times this budget. Anything a rebrand does to a colour, a radius or a font
             * is far above forty pixels too; what fits inside it is a hairline that moved by one
             * pixel, which is exactly the noise this exists to absorb.
             */
            threshold: 0,
            maxDiffPixels: 40,
            animations: "disabled",
            caret: "hide",
            scale: "css",
        },
    },

    outputDir: "./test-results/visual",

    /**
     * `php artisan serve` is single-threaded, so parallel workers would only queue against it and
     * make the run slower and less deterministic. Serial is also what keeps the sign-in fixture from
     * racing itself on the first cell.
     */
    fullyParallel: false,
    workers: 1,
    retries: 0,
    forbidOnly: !!process.env.CI,

    reporter: [
        ["list"],
        ["html", { outputFolder: "./playwright-report/visual", open: "never" }],
    ],

    use: {
        baseURL: BASE_URL,

        /** Overridden per cell with `setViewportSize`; this is only the context's starting size. */
        viewport: { width: 1440, height: 900 },

        /**
         * A fixed timezone, because the dashboard and the activity feeds render dates relative to
         * "now". An unpinned zone makes the baseline depend on the machine's clock configuration.
         */
        timezoneId: "Asia/Riyadh",

        screenshot: "off",
        video: "off",
        trace: "off",
    },

    /**
     * One flat naming scheme: `baselines/<locale>/<surface>/<surface>-<width>-<mode>.png`.
     *
     * The default template appends `-<platform>` (and a project name), which would put `linux` in
     * every committed path and silently look for a different set of files on macOS. The platform
     * suffix is dropped on purpose: this baseline is captured on Linux locally and compared on Linux
     * in CI, and if that ever stops being true the paths should not pretend otherwise — a mismatch
     * has to fail, not resolve to an empty directory and pass.
     */
    snapshotPathTemplate: "{testDir}/baselines/{arg}{ext}",

    projects: [
        {
            name: "chromium",
            use: { browserName: "chromium" },
        },
    ],
});
