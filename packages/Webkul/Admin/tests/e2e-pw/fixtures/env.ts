import dotenv from "dotenv";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

/**
 * Everything `playwright.config.ts` and the locale-pinning setup/teardown both need.
 *
 * It lives here rather than in the config file because Playwright evaluates the config first and
 * the global setup/teardown second: a setup that imported `../playwright.config` would import
 * `defineConfig` a second time, and a config that imported the setup would close that loop. A leaf
 * module with no Playwright import keeps both directions open.
 *
 * `.env` is read with dotenv's default precedence, so a variable already set in the shell wins.
 * That is what lets a run be aimed at a different instance or database — `APP_URL=... DB_DATABASE=...
 * npx playwright test` — without editing a tracked file, and it is the same precedence
 * `scripts/prepare-test-db.sh` and `phpunit.xml` rely on.
 */

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * This directory, for the helpers that shell out to `ui-locale.php` next to them. The ESM build has
 * no `__dirname` of its own, so it is derived once here and shared.
 */
export const fixturesDir = __dirname;

/**
 * The repository root, found by climbing to the directory that owns `artisan`.
 *
 * Climbing beats counting `../` segments: this file sits six directories below the root, and a count
 * that is one segment short resolves to the sibling `packages/` directory and fails with a
 * confusing ENOENT rather than a wrong-root message.
 */
const projectRoot = (() => {
    let directory = __dirname;

    while (directory !== path.dirname(directory)) {
        if (fs.existsSync(path.join(directory, "artisan"))) {
            return directory;
        }

        directory = path.dirname(directory);
    }

    throw new Error(`Could not find the repository root (a directory owning \`artisan\`) above ${ __dirname }.`);
})();

const dotenvPath = path.join(projectRoot, ".env");

/**
 * The database the *shell* exported, captured before `.env` is loaded.
 *
 * The order is the point, not an accident. `dotenv.config` does not overwrite a variable that is
 * already set, so once it has run, `process.env.DB_DATABASE` can no longer say whether an operator
 * exported a database or `.env` supplied the live one — and the difference is exactly what decides
 * whether the locale pin below may write. Capturing it first keeps the two apart.
 */
const exportedDatabase = process.env.DB_DATABASE;

/**
 * `.env` as authored, read independently of `process.env` and of whatever the shell exported.
 *
 * Only the two values the pin has to reason about are exposed; every other value keeps being read
 * through `process.env` exactly as before.
 */
const envFile: Record<string, string> = (() => {
    if (! fs.existsSync(dotenvPath)) {
        return {};
    }

    return dotenv.parse(fs.readFileSync(dotenvPath));
})();

dotenv.config({ path: dotenvPath });

export const TESTS_ROOT_PATH = path.resolve(__dirname, "..");

/**
 * The admin instance the suite drives.
 *
 * `use.baseURL` and the locale pin both read this one value, so a suite cannot authenticate against
 * one instance and pin the locale of another.
 */
export const BASE_URL = `${ process.env.APP_URL ?? "http://127.0.0.1:8000" }/`.replace(/\/+$/, "/");

/**
 * `BASE_URL` with the trailing slash and case differences removed, for the one comparison below that
 * has to decide whether two URLs mean the same instance.
 */
const normalizeUrl = (url: string): string => url.trim().replace(/\/+$/, "").toLowerCase();

/**
 * The instance `.env` describes. `BASE_URL` is the instance the *run* drives; this is the one the
 * checkout was configured for, so the two are equal for an ordinary local run and different for a
 * run aimed at a scratch instance.
 */
export const ENV_FILE_APP_URL = normalizeUrl(envFile.APP_URL ?? "http://127.0.0.1:8000");

/** The database `.env` configures — the one the checkout's own instance reads, and the live one. */
export const ENV_FILE_DATABASE = (envFile.DB_DATABASE ?? "").trim() || null;

/** The database the shell exported for this run, or `null` when nothing was exported. */
export const EXPORTED_DATABASE = (exportedDatabase ?? "").trim() || null;

/**
 * The interface language the run is pinned to.
 *
 * Nearly every remaining spec asserts an English label, placeholder or flash message, and rewriting
 * all of them into locale-derived lookups would trade a readable test for a translation table. The
 * honest alternative is the one taken here: run them against a known locale. The pin is applied to
 * the instance before the first test and put back afterwards — see `fixtures/pinUiLocale.ts`.
 *
 * `E2E_UI_LOCALE` exists so the locale-neutral specs — sign-in and sign-out are the ones written so
 * far — can be exercised in another language with the same harness:
 *
 *     E2E_UI_LOCALE=ar npx playwright test tests/admin.spec.ts
 */
export const UI_LOCALE = process.env.E2E_UI_LOCALE ?? "en";

/**
 * The administrator the suite signs in as.
 *
 * The installer's own defaults, kept as the default so a stock install runs unmodified, and
 * overridable so a checkout whose administrator has another address can run without editing
 * tracked files. `.state/admin-auth.json` is derived from these, which is why they live in one
 * place instead of being repeated per call site.
 */
export const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL ?? "admin@example.com";

export const ADMIN_PASSWORD = process.env.E2E_ADMIN_PASSWORD ?? "admin123";

export const STATE_DIR_PATH = path.join(TESTS_ROOT_PATH, ".state");

export const ADMIN_AUTH_STATE_PATH = path.join(STATE_DIR_PATH, "admin-auth.json");

/**
 * What the pin changed, so the teardown can put it back.
 *
 * A file rather than a value threaded through Playwright: setup and teardown are separate processes
 * from each other and from the workers, and the state directory is already this harness's scratch
 * space.
 */
export const UI_LOCALE_PIN_PATH = path.join(STATE_DIR_PATH, "ui-locale-pin.json");

export type PinDatabase = {
    /** The database the interface locale may be written to. */
    database: string;

    /** Whether that database is `.env`'s own, on the instance `.env` itself describes. */
    fromEnvFile: boolean;
};

/**
 * Which database the UI-locale pin may write to — decided here, before anything is written.
 *
 * The pin is a write to `core_config`, and `fixtures/ui-locale.php` resolves its connection by
 * booting Laravel, which reads `.env`. That resolution is right only while the suite drives the
 * instance `.env` configures. Aim the run at another instance — `APP_URL=http://127.0.0.1:8011 npx
 * playwright test` — and the same boot still resolves `.env`'s database, so the pin wrote the *live*
 * instance's locale row while the assertions were made against the scratch one. That happened on
 * this checkout, and it only self-restored because teardown happened to run.
 *
 * Two rules, and nothing is written unless one of them holds:
 *
 *   1. `DB_DATABASE` was exported by the shell. That is "the suite's own environment": a run aimed
 *      at a scratch instance names the database it is aimed at and the pin takes it. The one
 *      combination refused is an exported database equal to `.env`'s while the suite drives a
 *      *different* instance — which is the accident this function exists to prevent.
 *   2. Nothing was exported, and the suite drives exactly the instance `.env` describes. Then
 *      `.env`'s database is by definition the instance's own database, which is the ordinary local
 *      run and the case that already worked.
 *
 * Everything else is refused, with the database it would otherwise have written named in the
 * message so the fix (export `DB_DATABASE`) is obvious. The refusal happens during `globalSetup`: no
 * test runs and nothing is written.
 */
export function pinDatabase(): PinDatabase {
    const suiteDrivesEnvInstance = normalizeUrl(BASE_URL) === ENV_FILE_APP_URL;

    if (EXPORTED_DATABASE) {
        if (! suiteDrivesEnvInstance && EXPORTED_DATABASE === ENV_FILE_DATABASE) {
            throw new Error(
                `Refusing to pin the admin UI locale: DB_DATABASE=${ EXPORTED_DATABASE } is the database .env configures, ` +
                    `but this run drives ${ BASE_URL }, not ${ ENV_FILE_APP_URL }. ` +
                    "Point DB_DATABASE at the database that instance reads."
            );
        }

        return { database: EXPORTED_DATABASE, fromEnvFile: false };
    }

    if (suiteDrivesEnvInstance && ENV_FILE_DATABASE) {
        return { database: ENV_FILE_DATABASE, fromEnvFile: true };
    }

    throw new Error(
        `Refusing to pin the admin UI locale: this run drives ${ BASE_URL }, which is not the instance .env describes ` +
            `(${ ENV_FILE_APP_URL }), and DB_DATABASE is not exported, so there is no way to know which database that ` +
            "instance reads. The pin writes `general.general.locale_settings.locale` into `core_config`, and resolving " +
            `it from .env would write ${ ENV_FILE_DATABASE ?? "(no database configured)" } instead. ` +
            `Export DB_DATABASE=<the database ${ BASE_URL } is configured with> and run again.`
    );
}
