import { execFileSync } from "child_process";
import fs from "fs";
import path from "path";

import { fixturesDir, UI_LOCALE_PIN_PATH } from "./env";

/**
 * Put the interface locale back the way `pinUiLocale.ts` found it.
 *
 * The pin is a change to a shared instance's configuration, so it is only defensible if it is
 * undone. Leaving the checkout's admin panel in English because a test run was interrupted would be
 * a worse outcome than the failing assertion the pin exists to prevent.
 *
 * The restore targets the database the pin recorded, not the one `.env` happens to name: setup
 * resolved it once (`pinDatabase()`), and a teardown that re-resolved it could restore a different
 * database than the one that was changed.
 *
 * A missing pin file means setup changed nothing (the instance was already serving English) — that
 * is the normal case on an English install and not an error.
 *
 * A restore that cannot run is thrown rather than printed: it leaves an instance changed, and the
 * one place that is visible is Playwright's teardown report.
 */
export default async function restoreUiLocale(): Promise<void> {
    if (! fs.existsSync(UI_LOCALE_PIN_PATH)) {
        return;
    }

    const { previous, database, fromEnvFile } = JSON.parse(fs.readFileSync(UI_LOCALE_PIN_PATH, "utf-8")) as {
        previous: string | null;
        pinned: string;
        database: string;
        fromEnvFile: boolean;
    };

    if (! database) {
        /**
         * A pin file written by an older version of the setup records no database, so restoring it
         * would mean guessing one — the exact guess that wrote a live database during a scratch run.
         * Name the file and stop instead.
         */
        throw new Error(
            `${ UI_LOCALE_PIN_PATH } records no database, so the pinned locale cannot be restored without ` +
                `guessing which database was changed. Read the row it wrote and set it back by hand: php ` +
                `${ path.join(fixturesDir, "ui-locale.php") } read`
        );
    }

    const script = path.join(fixturesDir, "ui-locale.php");

    const output = execFileSync(
        "php",
        [
            script,
            `--database=${ database }`,
            ...(fromEnvFile ? ["--allow-env-database"] : []),
            ...(previous === null || previous === "" ? ["unset"] : ["set", previous]),
        ],
        { encoding: "utf-8", env: process.env, stdio: ["ignore", "pipe", "pipe"] }
    );

    fs.rmSync(UI_LOCALE_PIN_PATH);

    console.log(
        `[ui-locale] restored the served locale to "${ previous ?? "(unset)" }" in database "${ database }": ${ output.trim() }`
    );
}
