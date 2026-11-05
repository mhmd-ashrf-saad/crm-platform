import { execFileSync } from "child_process";
import fs from "fs";
import path from "path";

import {
    BASE_URL,
    fixturesDir,
    pinDatabase,
    STATE_DIR_PATH,
    UI_LOCALE,
    UI_LOCALE_PIN_PATH,
} from "./env";

/**
 * Pin the admin interface locale for the duration of a run.
 *
 * Almost every spec left in this suite asserts English text — `Save Account`, `Account updated
 * successfully`, `Email Address`. That is not a defect in those specs on an English install, and
 * rewriting thirty of them into locale-derived lookups would make each one harder to read while
 * testing the same behaviour. What was actually missing is determinism: on an Arabic-first checkout
 * the same assertions are run against Arabic and fail, which says nothing about the product.
 *
 * So the locale is made explicit instead of implied:
 *
 *   1. Ask the instance which locale it is serving (`<html lang>` on the login page — served before
 *      authentication, so no administrator is needed to read it).
 *   2. If it is already English, change nothing at all and leave no state behind.
 *   3. Otherwise write the locale the admin panel reads and remember what was there, so
 *      `restoreUiLocale.ts` can put it back.
 *
 * Where the pin is written matters. `Webkul\Admin\Http\Middleware\Locale` reads
 * `general.general.locale_settings.locale` from `core_config` on every admin request and only falls
 * back to `APP_LOCALE` when that row is absent — and on any installed instance the row exists. A
 * `UI_LOCALE=en` environment variable on an `artisan serve` process would therefore be silently
 * ignored. `ui-locale.php` writes that row through the application's own bootstrap, and it reads
 * back exactly the value the middleware will resolve.
 *
 * Booting Laravel is also how that write could land in the wrong database: the bootstrap reads
 * `.env`, so a suite aimed at another instance still resolved the *live* database, and the pin wrote
 * the live instance's row. `pinDatabase()` in `fixtures/env.ts` now decides which database is
 * allowed — explicitly exported by the shell, or `.env`'s own while the suite drives the instance
 * `.env` describes — and that name is handed to the helper and re-checked against the connection the
 * helper actually opens. Nothing is written when the two disagree.
 */

/**
 * The interface locale the instance is actually serving, normalised the way the product normalises
 * it (`pt_BR` is not a BCP-47 tag and `Intl` would throw on it).
 */
async function servedLocale(): Promise<string> {
    const response = await fetch(`${ BASE_URL }admin/login`, { redirect: "manual" });

    if (! response.ok) {
        throw new Error(
            `Could not read the admin login page from ${ BASE_URL }admin/login (HTTP ${ response.status }). ` +
                "The suite needs a running instance at APP_URL; start it with `make serve` or point APP_URL at one."
        );
    }

    const html = await response.text();

    /**
     * The attribute list spans several lines in `components/layouts/anonymous.blade.php`, so the
     * pattern has to allow anything but the closing bracket, newlines included.
     */
    const match = html.match(/<html\b[^>]*\blang="([^"]*)"/i);

    return (match?.[1] ?? "").trim().replace(/_/g, "-").toLowerCase();
}

/**
 * Run the pin helper against the same application the server is running.
 *
 * `php` and the checkout are both local to the harness; there is deliberately no fallback to a raw
 * SQL client, because a second write path is a second thing that can disagree with the middleware
 * about which row matters.
 */
function runLocaleScript(...args: string[]): {
    previous: string | null;
    current: string | null;
    database: string;
} {
    const script = path.join(fixturesDir, "ui-locale.php");

    const output = execFileSync("php", [script, ...args], {
        encoding: "utf-8",
        env: process.env,
        stdio: ["ignore", "pipe", "pipe"],
    });

    const lastLine = output.trim().split("\n").pop() ?? "";

    try {
        return JSON.parse(lastLine);
    } catch {
        throw new Error(`ui-locale.php ${ args.join(" ") } did not answer with JSON: ${ output.trim() }`);
    }
}

/**
 * Refuse to change the interface language of anything that is not this machine.
 *
 * The pin is a write to the instance's configuration. Doing that to a shared or deployed instance
 * because someone pointed `APP_URL` at it is not something this harness should be able to do by
 * accident.
 */
function assertLocalInstance(): void {
    const { hostname } = new URL(BASE_URL);

    const loopback = ["127.0.0.1", "localhost", "::1", "[::1]", "0.0.0.0"];

    if (! loopback.includes(hostname)) {
        throw new Error(
            `Refusing to pin the admin UI locale of ${ BASE_URL }: it is not a loopback instance. ` +
                `The suite asserts ${ UI_LOCALE } text, so a non-local instance must already be serving ${ UI_LOCALE }.`
        );
    }
}

export default async function pinUiLocale(): Promise<void> {
    const before = await servedLocale();

    if (before === UI_LOCALE) {
        console.log(`[ui-locale] ${ BASE_URL } already serves "${ UI_LOCALE }"; nothing pinned.`);

        return;
    }

    assertLocalInstance();

    /**
     * The database is resolved before the write and passed to the helper as an argument, not left to
     * the helper's `.env` bootstrap. `pinDatabase()` refuses when the run cannot name the database the
     * instance under test reads; see its comment in `fixtures/env.ts` for the two cases that may be
     * written and the accident the refusal exists to prevent.
     */
    const { database, fromEnvFile } = pinDatabase();

    const target = [`--database=${ database }`, ...(fromEnvFile ? ["--allow-env-database"] : [])];

    const { previous } = runLocaleScript(...target, "set", UI_LOCALE);

    fs.mkdirSync(STATE_DIR_PATH, { recursive: true });
    fs.writeFileSync(
        UI_LOCALE_PIN_PATH,
        JSON.stringify({ previous, pinned: UI_LOCALE, database, fromEnvFile }, null, 2)
    );

    const after = await servedLocale();

    if (after !== UI_LOCALE) {
        /**
         * A write that did not take is worth failing on: every English assertion in the suite would
         * otherwise fail with a missing-element message that names a label but never mentions the
         * locale, which is exactly the confusion this setup exists to remove.
         */
        throw new Error(
            `Pinned the admin UI locale to "${ UI_LOCALE }", but ${ BASE_URL } still serves "${ after }". ` +
                "The pin must target the same database the instance under test reads " +
                "(`general.general.locale_settings.locale` in `core_config`)."
        );
    }

    console.log(
        `[ui-locale] pinned ${ BASE_URL } from "${ before }" to "${ UI_LOCALE }" in database "${ database }"; ` +
            "will be restored afterwards."
    );
}
