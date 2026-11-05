import { expect, test } from "../fixtures/AdminFixtures";

/**
 * The dashboard's date filter is a single range control rather than a pair of start/end inputs.
 *
 * The pair made the common case — "last 30 days", "this month" — a manual job of picking two dates
 * and knowing today's, and neither input showed the range as a whole. The control now carries the
 * range on its face and opens a panel with the usual periods beside a two-month calendar.
 *
 * Every label and every rendered date below is asserted against the interface locale the app
 * actually served, not against English. The shipped default is Arabic (`APP_LOCALE=ar` in
 * `.env.example`), so `Oct 5, 2026` and `Today` are not what this screen says any more: the presets
 * read `اليوم` and friends, and a date renders as `1 أكتوبر 2026` — day first, no comma. Reading
 * the locale from `<html lang>` rather than pinning Arabic is deliberate: the same spec then holds
 * if the deployment is switched to English, instead of trading one hardcoded language for another.
 */

/**
 * The presets, as the interface renders them.
 *
 * Taken from `Resources/lang/{ar,en}/app.php` under `dashboard.index.date-presets` rather than read
 * back out of the panel: reading a label from the control and then asserting the control shows it
 * proves nothing at all. Arabic is listed first because it is the shipped default.
 */
const PRESET_LABELS = {
    ar: {
        today: "اليوم",
        yesterday: "أمس",
        last30Days: "آخر 30 يومًا",
        thisMonth: "هذا الشهر",
        lastMonth: "الشهر الماضي",
        thisYear: "هذه السنة",
    },
    en: {
        today: "Today",
        yesterday: "Yesterday",
        last30Days: "Last 30 Days",
        thisMonth: "This Month",
        lastMonth: "Last Month",
        thisYear: "This Year",
    },
} as const;

/**
 * `dashboard.index.export-pdf`, likewise.
 */
const EXPORT_LABEL = {
    ar: "تصدير PDF",
    en: "Export PDF",
} as const;

type UiLocale = keyof typeof PRESET_LABELS;

/**
 * The separator the control joins the two ends with — space, U+2013 EN DASH, space, exactly as
 * `label()` in `components/flat-picker/date-range.blade.php` builds it.
 */
const RANGE_SEPARATOR = " – ";

const escapeRegex = (value: string) =>
    value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

/**
 * The interface locale the app served, normalised the way the product normalises it.
 *
 * `pt_BR` and `zh_CN` ship with an underscore, which is not a BCP-47 subtag separator and makes the
 * `Intl` constructor throw; an empty or missing `lang` would leave the tag malformed too.
 */
const appLocale = (adminPage: any): Promise<string> =>
    adminPage.evaluate(() =>
        (document.documentElement.lang || "en").replace(/_/g, "-")
    );

/**
 * Pick the label set for the served locale.
 *
 * An unknown locale throws instead of quietly indexing `undefined`: a deployment in a third
 * language should fail with a message that names the missing label set, not with a `TypeError`
 * from inside an assertion several lines later.
 */
const labelsFor = (locale: string): (typeof PRESET_LABELS)[UiLocale] => {
    if (locale in PRESET_LABELS) {
        return PRESET_LABELS[locale as UiLocale];
    }

    throw new Error(
        `this spec carries preset labels for ${ Object.keys(PRESET_LABELS).join(", ") } only, but the app served "${locale}"`
    );
};

/**
 * A date rendered the way the control renders it.
 *
 * Mirrors `displayDate()` in `components/flat-picker/date-range.blade.php`: the app locale with
 * `-u-nu-latn` appended, which keeps the *language* — so Arabic month names stay Arabic — and pins
 * the numbering system to Western digits, which is this product's numeral rule. The formatting
 * happens inside the page so the browser's own ICU data produces the expectation; formatting it in
 * Node instead would compare two different ICU builds and fail on a difference that is not a
 * product defect.
 *
 * `toLocaleDateString(undefined, …)`, which this spec used to build its expectation with, followed
 * the *browser's* locale instead of the app's — on an Arabic screen it produced English months for
 * a control showing Arabic ones, so the assertion could not pass.
 */
const formatDate = (
    adminPage: any,
    date: { year: number; month: number; day: number }
): Promise<string> =>
    adminPage.evaluate(({ year, month, day }: any) => {
        const locale = (document.documentElement.lang || "en").replace(/_/g, "-");

        return new Intl.DateTimeFormat(`${ locale }-u-nu-latn`, {
            year: "numeric",
            month: "short",
            day: "numeric",
        }).format(new Date(year, month, day));
    }, date);

const formatRange = async (
    adminPage: any,
    start: { year: number; month: number; day: number },
    end: { year: number; month: number; day: number }
): Promise<string> =>
    `${ await formatDate(adminPage, start) }${ RANGE_SEPARATOR }${ await formatDate(adminPage, end) }`;

/**
 * The structure of a formatted date in this locale, with the digits and the words relaxed.
 *
 * Derived from a real formatted date rather than written down, because the shape is language
 * dependent: English is `Oct 5, 2026`, Arabic is `5 أكتوبر 2026`. The month word is relaxed along
 * with the digits because the date being checked is not the date the sample came from — the default
 * range starts a month back, so its month name differs by design.
 *
 * What survives is the claim the assertion is actually making: the value has the structure of a
 * rendered date — grouped numbers around a word, in this locale — rather than a raw `Y-m-d` or a
 * stray fragment.
 *
 * The words are substituted before the digits deliberately: the replacement for a digit run is
 * `\d+`, which contains a letter, so running the letter pass second would rewrite its own output
 * into `\p{L}++` and throw "Incomplete quantifier".
 */
const dateShape = (sample: string) =>
    new RegExp(
        `^${ escapeRegex(sample).replace(/\p{L}+/gu, "\\p{L}+").replace(/\d+/g, "\\d+") }$`,
        "u"
    );

const todayParts = () => {
    const today = new Date();

    return { year: today.getFullYear(), month: today.getMonth(), day: today.getDate() };
};

test.describe("dashboard date range filter", () => {
    const control = (adminPage: any) =>
        adminPage.locator("button:has(.icon-calendar)").first();

    const preset = (adminPage: any, label: string) =>
        adminPage.locator(`button:text-is("${ label }")`).first();

    test.beforeEach(async ({ adminPage }) => {
        await adminPage.goto("admin/dashboard");
    });

    test("the control shows the range it currently holds", async ({ adminPage }) => {
        await expect(control(adminPage)).toBeVisible();

        const today = todayParts();

        const todayLabel = await formatDate(adminPage, today);

        const label = (await control(adminPage).textContent()) ?? "";

        /**
         * Both ends on one control, which is what the pair of inputs could not show.
         */
        const parts = label.split("–").map((part: string) => part.trim());

        expect(parts, `the control should carry one range, got "${ label }"`).toHaveLength(2);

        /**
         * The default range ends today (`AbstractReporting::setEndDate()`), so the right end is a
         * date the spec can name, and the assertion does not have to guess what the left one is.
         */
        expect(parts[1], "the default range should end today").toBe(todayLabel);

        /**
         * The start is a month back, and PHP's month overflow makes that awkward to name — the
         * comment on "choosing a preset updates the range" below records why. So it is checked for
         * shape instead: it has to have the structure of a date the app's own formatter produced,
         * which is what proves a formatter ran over it rather than a raw `Y-m-d` slipping through.
         */
        expect(
            parts[0],
            `the left end should be a date in the app locale, said "${ parts[0] }"`
        ).toMatch(dateShape(todayLabel));
    });

    test("opening the control reveals the presets and a two-month calendar", async ({
        adminPage,
    }) => {
        const labels = labelsFor(await appLocale(adminPage));

        await control(adminPage).click();

        for (const label of [
            labels.today,
            labels.yesterday,
            labels.last30Days,
            labels.thisMonth,
            labels.thisYear,
        ]) {
            await expect(
                preset(adminPage, label),
                `the ${ label } preset should be offered`
            ).toBeVisible();
        }

        /**
         * Two months are shown so a range crossing a month boundary is picked in one go.
         */
        await expect(adminPage.locator(".flatpickr-month")).toHaveCount(2);
    });

    /**
     * "Last Month" rather than "This Month", and the expectation is the range itself rather than
     * "different from whatever was there".
     *
     * `This Month` can be the range the dashboard already shows, and on some days it is: the
     * default start is `now()->subMonth()` (`AbstractReporting::getDefaultStartDate()`), and PHP
     * overflows a month subtraction that lands on a day the target month does not have --
     * 2026-10-31 minus one month is `2026-10-01`, not `2026-09-30` -- so on the 31st of a month
     * that follows a 30-day one the dashboard opens on exactly this month's range. Choosing the
     * preset then correctly changes nothing, and the old "must not equal the previous label"
     * assertion failed on a control that was working. That is what reddened CI on 2026-10-31.
     *
     * `Last Month` cannot coincide with the default: the default end is always today
     * (`AbstractReporting::setEndDate()`) and this preset always ends on the last day of the
     * previous month. Its range also does not depend on today's day number, so the expectation
     * holds on every date, and comparing against that exact range is a stronger claim than
     * "something changed": the control has to report the preset, not merely a different string.
     */
    test("choosing a preset updates the range", async ({ adminPage }) => {
        const labels = labelsFor(await appLocale(adminPage));

        const before = await control(adminPage).textContent();

        await control(adminPage).click();

        await preset(adminPage, labels.lastMonth).click();

        /**
         * Built in the page, with the formatter the control itself uses: the range is the app's
         * month, rendered in the app's locale with Latin digits. Day `0` of a month is the last day
         * of the month before it, which is the same thing the preset's `case 'last-month'` does.
         */
        const today = todayParts();

        const startParts = { year: today.year, month: today.month - 1, day: 1 };

        const endParts = { year: today.year, month: today.month, day: 0 };

        const expected = await formatRange(adminPage, startParts, endParts);

        const beforeText = before ?? "";

        expect(beforeText, "the preset has to move the range, or nothing is proved").not.toBe(
            expected
        );

        await expect
            .poll(() => control(adminPage).textContent(), {
                message: "the control should report the preset's range",
            })
            .toBe(expected);

        /**
         * "Last Month" runs from the first of the previous month to its last day, so the start
         * must be the 1st — asserted through the locale's own rendering of the 1st, because the
         * English-shaped `/\w+ 1, \d{4}/` this used to use matches nothing on an Arabic screen.
         */
        const startLabel = await formatDate(adminPage, startParts);

        await expect(control(adminPage)).toHaveText(
            new RegExp(`^${ escapeRegex(startLabel) }\\s*–`)
        );
    });

    test("the selected preset is marked in the list", async ({ adminPage }) => {
        const labels = labelsFor(await appLocale(adminPage));

        await control(adminPage).click();

        await preset(adminPage, labels.thisMonth).click();

        await control(adminPage).click();

        await expect(
            preset(adminPage, labels.thisMonth),
            "the active period should be distinguishable from the rest"
        ).toHaveClass(/font-semibold/);
    });

    test("the panel closes when clicking away", async ({ adminPage }) => {
        const labels = labelsFor(await appLocale(adminPage));

        await control(adminPage).click();

        await expect(preset(adminPage, labels.today)).toBeVisible();

        await adminPage.mouse.click(5, 400);

        await expect(preset(adminPage, labels.today)).toBeHidden();
    });

    /**
     * The reports redraw from the `reporting-filter-updated` event, so a range change has to reach
     * them — a control that only updated its own label would look right and show stale figures.
     */
    test("changing the range reloads the reports", async ({ adminPage }) => {
        const labels = labelsFor(await appLocale(adminPage));

        const requests: string[] = [];

        adminPage.on("request", (request: any) => {
            if (request.url().includes("/admin/dashboard/stats")) {
                requests.push(request.url());
            }
        });

        await control(adminPage).click();

        await preset(adminPage, labels.last30Days).click();

        await expect
            .poll(() => requests.length, {
                message: "the reports should refetch for the new range",
            })
            .toBeGreaterThan(0);
    });

    /**
     * A range picked by hand on the calendar must reach the reports, not just the control's label.
     *
     * The component did not declare `change` as its own event, so Vue also let the calendar input's
     * native DOM `change` fall through to the same listener. That event arrived last and carried no
     * range, overwriting both dates with `undefined`: the request went out with no `start` or `end`
     * and every report silently fell back to its default period, showing current figures for a
     * range the user had picked in the past.
     */
    test("a hand-picked range reaches the reports", async ({ adminPage }) => {
        const requests: string[] = [];

        adminPage.on("request", (request: any) => {
            if (request.url().includes("/admin/dashboard/stats")) {
                requests.push(request.url());
            }
        });

        await control(adminPage).click();

        const days = adminPage.locator(".flatpickr-days").first();
        const selectable = '.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay):not(.flatpickr-disabled)';

        await days.locator(selectable).nth(3).click();
        await days.locator(selectable).nth(8).click();

        await expect
            .poll(() => requests.length, { message: "the reports should refetch" })
            .toBeGreaterThan(0);

        /**
         * The query string carries `Y-m-d` on both ends — `toIsoDate()` in the component — which is
         * a wire format and stays machine-readable whatever the interface language is.
         */
        for (const url of requests) {
            expect(url, "every report must receive the picked range").toMatch(
                /start=\d{4}-\d{2}-\d{2}/
            );
            expect(url).toMatch(/end=\d{4}-\d{2}-\d{2}/);
        }
    });

    /**
     * The dashboard reports on what has already happened, and the server clamps a future end date
     * back to today. Offering future days therefore produced a range whose end preceded its start,
     * which the reports labelled "29 Nov - 14 Oct".
     */
    test("future dates cannot be picked", async ({ adminPage }) => {
        await control(adminPage).click();

        const disabled = adminPage.locator(".flatpickr-day.flatpickr-disabled");

        await expect(
            disabled.first(),
            "days after today should not be selectable"
        ).toBeAttached();
    });

    /**
     * `date_range` on the stats payload is the *server's* rendering, not the control's:
     * `Dashboard::getDateRange()` builds it with Carbon `format('d M')`, and PHP's `date()`
     * abbreviations are English regardless of `APP_LOCALE`. So the payload reads `29 Nov - 30 Nov`
     * on an Arabic screen as well, and splitting on the ASCII separator here is not a
     * locale-dependence — it is the one place this spec still depends on English, and it would need
     * revisiting only if the server moved to `translatedFormat()`.
     */
    test("a range sent entirely in the future is reported in order", async ({ adminPage }) => {
        await adminPage.goto("admin/dashboard");

        const body = await adminPage.evaluate(async () => {
            const response = await fetch(
                "/admin/dashboard/stats?type=total-leads&start=2099-11-29&end=2099-11-30",
                { headers: { "X-Requested-With": "XMLHttpRequest" } }
            );

            return await response.json();
        });

        const [start, end] = body.date_range.split(" - ");

        expect(
            new Date(`${start} 2099`) <= new Date(`${end} 2099`) || start === end,
            `the range should not be inverted, got "${ body.date_range }"`
        ).toBeTruthy();
    });

    test("the export link carries the selected range", async ({ adminPage }) => {
        const locale = await appLocale(adminPage);

        const labels = labelsFor(locale);

        await control(adminPage).click();

        await preset(adminPage, labels.thisMonth).click();

        const href = await adminPage
            .locator(`a:has-text("${ EXPORT_LABEL[(locale in EXPORT_LABEL ? locale : "ar") as UiLocale] }")`)
            .first()
            .getAttribute("href");

        expect(href, "the export should use the range on screen").toMatch(/start=\d{4}-\d{2}-\d{2}/);
        expect(href).toMatch(/end=\d{4}-\d{2}-\d{2}/);
    });
});
