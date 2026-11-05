/**
 * The Unicode extension every formatter here pins.
 *
 * A locale's default digits are not the interface's digits. `ar` happens to render Latin digits,
 * but add a region and `ar-EG` renders 1234 as ١٬٢٣٤ -- Arabic-Indic digits on a screen whose
 * prices and dates are Latin everywhere else. The server pins the same `-u-nu-latn` tag in
 * `Core::formatBasePrice()` and `Core::formatDate()`, so both sides render the same numerals.
 */
const LATIN_DIGITS = "-u-nu-latn";

/**
 * Turn a value of `<html lang>` into a locale tag `Intl` accepts.
 *
 * Two things break a raw tag before `Intl` sees it: the shipped `pt_BR` and `zh_CN` locales use an
 * underscore, which is not a BCP-47 subtag separator and makes the constructor throw, and a page
 * whose `lang` is empty or malformed would leave `-u-nu-latn` dangling on nothing. Falling back to
 * the language subtag alone -- then to `en` -- keeps a formatter from throwing on such a page.
 *
 * @param {string} lang - The raw `<html lang>` value.
 * @returns {string} - A BCP-47 tag carrying the Latin numbering system.
 */
const resolveLocale = (lang) => {
    const tag = String(lang ?? "")
        .trim()
        .replace(/_/g, "-");

    const candidates = [tag, tag.split("-")[0], "en"];

    for (const candidate of candidates) {
        if (! candidate) {
            continue;
        }

        const locale = candidate + LATIN_DIGITS;

        try {
            new Intl.DateTimeFormat(locale);

            return locale;
        } catch (error) {
            /**
             * Not a language tag at all (a stray punctuation character, a number). The next
             * candidate is a shorter prefix of it, and the last one always works.
             */
        }
    }

    return "en" + LATIN_DIGITS;
};

let cachedLang;

let cachedLocale;

/**
 * The bidirectional isolate pair, `LRI` (U+2066) and `PDI` (U+2069).
 *
 * `US$` is a Latin run that ends in a currency sign, and Unicode classifies that sign as a neutral:
 * in a right-to-left paragraph it resolves to the paragraph direction and lands on the far side of
 * the run, so `0.00 US$` is drawn as `$US 0.00`. The characters are right in the document; only the
 * order the browser picks is wrong. An isolate gives the token its own left-to-right paragraph, so
 * its characters keep the order they are written in while the token as a whole is still placed by
 * the surrounding Arabic text -- the same mechanism the server pins in
 * `Core::isolateCurrencySymbol()`, so both sides of a screen agree.
 */
const LTR_ISOLATE_START = "\u2066";

const LTR_ISOLATE_END = "\u2069";

/**
 * Whether the document itself is laid out right-to-left.
 *
 * Read from `<html dir>` rather than from the locale: the direction the page was actually rendered
 * with is the one the browser will use for the paragraph these characters land in.
 *
 * @returns {boolean}
 */
const isRightToLeft = () => (document.documentElement.dir ?? "") === "rtl";

/**
 * Keeps a Latin currency token in the order it is written on a right-to-left page.
 *
 * Only a token whose first letter is Latin is touched, and only on a right-to-left page, so an
 * English screen and an Arabic screen using an Arabic symbol such as `ج.م.` -- CLDR ends it with
 * U+200F -- come out exactly as they did: the token is returned unchanged, not re-escaped. A token
 * that already carries an isolate is also returned unchanged, so wrapping one twice cannot happen.
 *
 * @param {string} token - The currency part as `Intl` produced it, e.g. `US$`.
 * @returns {string} - The same token, isolated when it needs to be.
 */
const isolateCurrencyToken = (token) => {
    if (! isRightToLeft()) {
        return token;
    }

    if (! /^[^\p{L}]*\p{Script=Latin}/u.test(token)) {
        return token;
    }

    if (token.includes(LTR_ISOLATE_START)) {
        return token;
    }

    return LTR_ISOLATE_START + token + LTR_ISOLATE_END;
};

/**
 * The locale every client-side formatter formats in.
 *
 * The interface locale is what the server rendered the page with, so a date or a price formatted
 * here matches the one rendered beside it in Blade, instead of showing an English month name in
 * the middle of an Arabic row. `Intl` construction is the expensive part of formatting, so the
 * resolution is cached per `lang` value.
 *
 * @returns {string} - A BCP-47 tag carrying the Latin numbering system.
 */
export const formattingLocale = () => {
    const lang = document.documentElement.lang ?? "";

    if (lang !== cachedLang) {
        cachedLang = lang;

        cachedLocale = resolveLocale(lang);
    }

    return cachedLocale;
};

export default {
    install(app) {
        app.config.globalProperties.$admin = {
            /**
             * The locale this plugin formats in, exposed so components defined in Blade
             * templates -- which cannot import this module -- format in the same one.
             *
             * @returns {string} - A BCP-47 tag carrying the Latin numbering system.
             */
            formattingLocale,

            /**
             * Generates a formatted price.
             *
             * @param {number} price - The price value to be formatted.
             * @returns {string} - The formatted price string.
             */
            formatPrice: (price) => {
                const locale = formattingLocale();

                const currency = JSON.parse(
                    document.querySelector('meta[name="currency"]').content
                );

                const symbol =
                    currency.symbol !== "" ? currency.symbol : currency.code;

                if (!currency.currency_position) {
                    const formatter = new Intl.NumberFormat(locale, {
                        style: "currency",
                        currency: currency.code,
                    });

                    /**
                     * Built from the parts rather than from `format()` so the currency token alone
                     * can be isolated. Joining the parts back reproduces the formatted string
                     * exactly, so nothing but that token can change.
                     */
                    return formatter
                        .formatToParts(price)
                        .map((part) =>
                            part.type === "currency"
                                ? isolateCurrencyToken(part.value)
                                : part.value
                        )
                        .join("");
                }

                const formatter = new Intl.NumberFormat(locale, {
                    style: "currency",
                    currency: currency.code,
                    minimumFractionDigits: currency.decimal ?? 2,
                });

                const formattedCurrency = formatter
                    .formatToParts(price)
                    .map((part) => {
                        switch (part.type) {
                            case "currency":
                                return "";

                            case "group":
                                return currency.group_separator === ""
                                    ? part.value
                                    : currency.group_separator;

                            case "decimal":
                                return currency.decimal_separator === ""
                                    ? part.value
                                    : currency.decimal_separator;

                            default:
                                return part.value;
                        }
                    })
                    .join("");

                switch (currency.currency_position) {
                    case "left":
                        return isolateCurrencyToken(symbol) + formattedCurrency;

                    case "left_with_space":
                        return isolateCurrencyToken(symbol) + " " + formattedCurrency;

                    case "right":
                        return formattedCurrency + symbol;

                    case "right_with_space":
                        return formattedCurrency + " " + isolateCurrencyToken(symbol);

                    default:
                        return formattedCurrency;
                }
            },

            /**
             * Generates a formatted date based on specified timezone.
             *
             * @param {string} dateString - The date value to be formatted.
             * @param {string} format - The format to be used for formatting the date.
             * @param {string} timezone - The timezone to use (e.g., 'America/New_York').
             * @returns {string} - The formatted date string.
             */
            formatDate: (dateString, format, timezone) => {
                const locale = formattingLocale();

                const date = new Date(dateString);

                const options = { timeZone: timezone };

                const formatter = new Intl.DateTimeFormat(locale, {
                    ...options,
                    hour12: false,
                    year: "numeric",
                    month: "numeric",
                    day: "numeric",
                    hour: "numeric",
                    minute: "numeric",
                    second: "numeric",
                });

                const parts = formatter.formatToParts(date);
                const dateParts = {};

                parts.forEach((part) => {
                    if (part.type !== "literal") {
                        dateParts[part.type] = part.value;
                    }
                });

                const tzDay = parseInt(dateParts.day, 10);
                const tzMonth = parseInt(dateParts.month, 10);
                const tzYear = parseInt(dateParts.year, 10);
                const tzHour = parseInt(dateParts.hour, 10);
                const tzMinute = parseInt(dateParts.minute, 10);

                const formatters = {
                    d: tzDay,
                    DD: tzDay.toString().padStart(2, "0"),
                    M: tzMonth,
                    MM: tzMonth.toString().padStart(2, "0"),
                    MMM: new Date(tzYear, tzMonth - 1, 1).toLocaleString(locale, {
                        month: "short",
                    }),
                    MMMM: new Date(tzYear, tzMonth - 1, 1).toLocaleString(locale, {
                        month: "long",
                    }),
                    yy: tzYear.toString().slice(-2),
                    yyyy: tzYear,
                    H: tzHour,
                    HH: tzHour.toString().padStart(2, "0"),
                    h: tzHour % 12 || 12,
                    hh: (tzHour % 12 || 12).toString().padStart(2, "0"),
                    m: tzMinute,
                    mm: tzMinute.toString().padStart(2, "0"),
                    A: tzHour < 12 ? "AM" : "PM",
                };

                return format.replace(
                    /\b(?:d|DD|M|MM|MMM|MMMM|yy|yyyy|H|HH|h|hh|m|mm|A)\b/g,
                    (match) => formatters[match]
                );
            },
        };
    },
};
