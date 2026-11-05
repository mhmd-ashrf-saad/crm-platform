<?php

namespace Webkul\Core;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Core\Repositories\CountryRepository;
use Webkul\Core\Repositories\CountryStateRepository;
use Webkul\Core\Traits\Sanitizer;

class Core
{
    use Sanitizer;

    /**
     * The Krayin version.
     *
     * @var string
     */
    const KRAYIN_VERSION = '2.2.7';

    /**
     * The bidirectional isolate pair (`LRI`, `PDI`), documented on `isolateCurrencySymbol()`.
     *
     * @var string
     */
    const LTR_ISOLATE_START = "\u{2066}";

    /**
     * @var string
     */
    const LTR_ISOLATE_END = "\u{2069}";

    /**
     * Create a new instance.
     *
     * @return void
     */
    public function __construct(
        protected CountryRepository $countryRepository,
        protected CoreConfigRepository $coreConfigRepository,
        protected CountryStateRepository $countryStateRepository
    ) {}

    /**
     * Get the version number of the Krayin.
     *
     * @return string
     */
    public function version()
    {
        return static::KRAYIN_VERSION;
    }

    /**
     * Retrieve all timezones.
     */
    public function timezones(): array
    {
        $timezones = [];

        foreach (timezone_identifiers_list() as $timezone) {
            $timezones[$timezone] = $timezone;
        }

        return $timezones;
    }

    /**
     * Retrieve all locales.
     */
    public function locales(): array
    {
        $options = [];

        foreach (config('app.available_locales') as $key => $title) {
            $options[] = [
                'title' => $title,
                'value' => $key,
            ];
        }

        return $options;
    }

    /**
     * Retrieve all countries, ordered by name in the active locale.
     *
     * The rows are seeded in English alphabetical order, which is not an order an Arabic reader can
     * scan: `مصر` would sit where `Egypt` sits, and the list would read `أفغانستان`, `جزر آلاند`,
     * `ألبانيا`, `الجزائر`, `ساموا الأمريكية`. The collection is therefore sorted here, on the
     * rendered name, and with ICU's `Collator` rather than a byte comparison — a byte sort orders the
     * Arabic letters by codepoint and puts `آ`/`أ`/`إ`/`ا` in an order no reader expects, while the
     * collator orders them as the language does.
     *
     * The comparison runs on `countrySortKey()` rather than on the raw name, so a leading Arabic
     * definite article is ignored while sorting: the collator orders `ال` as the letters alif and
     * lam, which put every article-carrying name in one run inside the alif block (`البحرين`,
     * `الكويت`, ... before `ب`) instead of under the letter a reader looks it up by. Dictionary
     * order is what the Arabic picker is scanned with, and the displayed name keeps its article.
     *
     * `sort()` compares through `uasort()`, so the collection keys are preserved: a caller that keys
     * the list by id, and anything that expects a `Collection` back, keep working unchanged.
     *
     * The sort sits here and not in `CountryRepository::all()` for two reasons. This order depends on
     * the *request* locale while the repository result is cacheable under a locale-independent key,
     * and every repository consumer that is not this method wants the rows as seeded.
     *
     * @return Collection
     */
    public function countries()
    {
        $countries = $this->countryRepository->all();

        $collator = $this->countryNameCollator();

        return $countries->sort(fn ($first, $second) => $collator
            ? (int) $collator->compare(
                $this->countrySortKey((string) $first->name),
                $this->countrySortKey((string) $second->name)
            )
            : strcmp((string) $first->name, (string) $second->name));
    }

    /**
     * The key a country name is compared by: the name with a leading Arabic definite article
     * removed.
     *
     * `المملكة العربية السعودية` is looked up under م, the way an Arabic index or phone book files
     * it, so the article is dropped for the comparison and only for the comparison — `countries()`
     * still returns the name as seeded, article included.
     *
     * The guard is deliberately narrow. `ال` is stripped only when an Arabic letter follows it, so
     * a word whose alif-lam is part of the root rather than the article (`ألبانيا`, `ألمانيا` —
     * hamza above, not a bare alif) and a `الـ` prefix with a tatweel both fall through untouched.
     * `\p{Lo}` and not `\p{L}` draws that line: Arabic letters are `Lo`, while tatweel is `Lm` and
     * a combining mark is `Mn`.
     *
     * Every one of the 255 CLDR names this list is seeded from was measured against this rule; each
     * moved row lands under the stem a reader would search for, so there is no exception list.
     */
    protected function countrySortKey(string $name): string
    {
        /**
         * `preg_replace` answers null on a subject that is not valid UTF-8, and the name is then
         * compared as it is rather than dropped.
         */
        return preg_replace('/^ال(?=\p{Arabic})(?=\p{Lo})/u', '', $name, 1) ?? $name;
    }

    /**
     * A collator for the active locale, or null when `intl` cannot build one.
     *
     * Every failure path answers null rather than throwing: this list is rendered on the quote, lead
     * and warehouse address pickers, so an unexpected or malformed locale must degrade to the byte
     * sort in `countries()` instead of breaking the form.
     */
    protected function countryNameCollator(): ?\Collator
    {
        if (! class_exists(\Collator::class)) {
            return null;
        }

        try {
            return \Collator::create((string) app()->getLocale()) ?: null;
        } catch (\ValueError) {
            /**
             * PHP 8.5 rejects a syntactically invalid locale here instead of returning null.
             */
            return null;
        }
    }

    /**
     * Returns country name by code.
     */
    public function country_name(string $code): string
    {
        $country = $this->countryRepository->findOneByField('code', $code);

        return $country ? $country->name : '';
    }

    /**
     * Returns state name by code, optionally within one country.
     *
     * A bare code is not unique in `country_states`: 46 codes are shared by more than one country,
     * and Egypt's governorates are what turn six of them into the wrong country's name on a saved
     * address — `BA`, `BH`, `IS` and `KN` did not collide at all until Egypt was seeded, and `MN`
     * (Minnesota, Manipur) and `MT` (Montana, Mato Grosso) became three-way. `MN` is Minnesota and
     * المنيا, `BA` is Bahia and البحر الأحمر. There is no unique index on `(country_code, code)` to
     * lean on, so a lookup by code alone cannot say which row a saved address meant, and it
     * rendered the wrong country's state.
     *
     * `$countryCode` is what resolves that. It is the second and optional argument, so every
     * existing one-argument call keeps compiling and keeps its meaning. The payloads this method is
     * called for carry both fields — `state` and `country` — on the organization view and in
     * `AttributeValueRepository` and `AbstractEntity`.
     *
     * Four resolutions, in order:
     *
     * - No row carries this code at all: the code itself, unchanged.
     * - The supplied country carries a row for this code: that row's name. This is the only branch
     *   that knows which state was meant, and it is what the three call sites pass.
     * - A country is supplied and carries no row for this code: the code itself, never the code-only
     *   answer. When a country is named and does not have this code, the code belongs to some other
     *   country, and the code-only answer is that country's name printed on this one's address:
     *   `state_name('C', 'US')` answered `القاهرة` for a United States address because `C` is
     *   Egypt's Cairo and the United States has no `C`.
     * - No country (`null` or `''`): the code-only answer. An empty country is not evidence of a
     *   mismatch, only of an older or partial payload, so this branch keeps the answer such a
     *   payload always rendered instead of turning it into a bare code.
     *
     * The third branch is a deliberate trade: a state that is stale, or free text typed for a
     * country with no list, now prints as it was stored instead of as another country's name. That
     * is the right way round. 239 of the 255 countries in the address picker have no row in
     * `country_states`, so the form asks those addresses for free text — and the stored string is
     * exactly what the form showed the user when it was saved, while the name it replaced came from
     * a country the address is not in. Nothing stored on this instance changes either: all eight
     * address values in the live database carry an empty `state`, so not one of them reaches this
     * branch.
     *
     * A mis-cased country code (`us`) does not take the third branch: the comparison is folded to
     * ASCII case first. The code argument is already case-insensitive without anyone asking — the
     * `code` column is `utf8mb4_unicode_ci`, so `state_name('mn', 'US')` answers `Minnesota`. The
     * country argument was compared in PHP against the loaded rows instead, so `state_name('MN',
     * 'us')` used to miss every row while `country_name('us')` answered `الولايات المتحدة` from the
     * same collation one line below it on the screen. Folding keeps the third branch meaning "this
     * country has no such code" instead of "this string differs in case", which is the only reading
     * under which answering the code is the honest answer. `strcasecmp()` and not a Unicode fold is
     * deliberate: country codes are ASCII, so a value it cannot fold is a value no row holds.
     *
     * The code-only answer is the row with the lowest `id` — not the row `findOneByField()` happens
     * to return first, and not the row of the alphabetically first country. Both of those are
     * defensible and only one of them is free: ordering by `country_code` would have moved the answer
     * for 28 of the 46 codes more than one row carries — `PA` from Pennsylvania to Pará, `IL` from
     * Illinois to Ialomiţa, `MT` from Montana to Mato Grosso, `AL` from Alabama to Alagoas — and an
     * existing answer should not change without a reason. Ordering by `id` reproduces every one of
     * them, because `country_states` carries the id each row was seeded with and the bare `code`
     * lookup was an unindexed scan that answered that row, while still being deterministic: the
     * answer no longer depends on what a future row or a rebuilt table returns first. Measured on the
     * shipped seed, the one-argument answer for all 46 duplicated codes and all 493 single-row codes
     * is byte-identical to the answer `findOneByField()` gave. No caller in this repository reaches
     * this branch any more; it exists for one-argument calls and is stable for them.
     */
    public function state_name(string $code, ?string $countryCode = null): string
    {
        /**
         * Every row carrying the code, in one query, decided here rather than through
         * `findOneByField()`. That method answers the first row the database happens to return, and
         * at most three rows share a code, so the extra rows cost nothing and the answer is stable.
         */
        $states = $this->countryStateRepository->findByField('code', $code);

        if ($states->isEmpty()) {
            return $code;
        }

        if ($countryCode !== null && $countryCode !== '') {
            $state = $states->first(
                fn ($row) => strcasecmp((string) $row->country_code, $countryCode) === 0
            );

            /**
             * A named country that carries no row for this code means the code belongs to another
             * country. Answering the code-only name here would print that country's state on this
             * one's address, which is the wrong answer rather than a missing one.
             */
            return $state ? $state->name : $code;
        }

        return $states
            ->sortBy('id')
            ->first()
            ->name;
    }

    /**
     * Retrieve all country states.
     *
     * @return Collection
     */
    public function states(string $countryCode)
    {
        return $this->countryStateRepository->findByField('country_code', $countryCode);
    }

    /**
     * Retrieve all grouped states by country code.
     *
     * @return Collection
     */
    public function groupedStatesByCountries()
    {
        $collection = [];

        foreach ($this->countryStateRepository->all() as $state) {
            $collection[$state->country_code][] = $state->toArray();
        }

        return $collection;
    }

    /**
     * Retrieve all grouped states by country code.
     *
     * @return Collection
     */
    public function findStateByCountryCode($countryCode = null, $stateCode = null)
    {
        $collection = [];

        $collection = $this->countryStateRepository->findByField([
            'country_code' => $countryCode,
            'code' => $stateCode,
        ]);

        if (count($collection)) {
            return $collection->first();
        } else {
            return false;
        }
    }

    /**
     * Create singleton object through single facade.
     *
     * @param  string  $className
     * @return mixed
     */
    public function getSingletonInstance($className)
    {
        static $instances = [];

        if (array_key_exists($className, $instances)) {
            return $instances[$className];
        }

        return $instances[$className] = app($className);
    }

    /**
     * Format date
     *
     * The format string is Carbon's, so `M`/`MMM`/`MMMM` and `A` are month names and the
     * meridiem. `format()` prints those in English whatever the active locale is, which put
     * "26 Aug 2026 12:00AM" inside an Arabic interface; `translatedFormat()` renders the same
     * format with the active locale's month and meridiem names, and leaves every locale that has
     * no translation of its own (including `en`) byte-identical to what `format()` produced.
     *
     * @return string
     */
    public function formatDate($date, $format = 'd M Y h:i A')
    {
        return Carbon::parse($date)->locale(app()->getLocale())->translatedFormat($format);
    }

    /**
     * Week range.
     *
     * @param  string  $date
     * @param  int  $day
     * @return string
     */
    public function xWeekRange($date, $day)
    {
        $ts = strtotime($date);

        if (! $day) {
            $start = (date('D', $ts) == 'Sun') ? $ts : strtotime('last sunday', $ts);

            return date('Y-m-d', $start);
        } else {
            $end = (date('D', $ts) == 'Sat') ? $ts : strtotime('next saturday', $ts);

            return date('Y-m-d', $end);
        }
    }

    /**
     * Return currency symbol from currency code.
     *
     * @param  float  $price
     * @return string
     */
    public function currencySymbol($code)
    {
        return $this->isolateCurrencySymbol($this->plainCurrencySymbol((string) $code));
    }

    /**
     * The currency symbol as the locale writes it, without any bidirectional control.
     */
    protected function plainCurrencySymbol(string $code): string
    {
        $formatter = $this->numberFormatter(\NumberFormatter::CURRENCY, $code);

        return $formatter
            ? (string) $formatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL)
            : $code;
    }

    /**
     * Keep a Latin currency token in the order it is written when the interface is right-to-left.
     *
     * `US$` is a Latin run that ends in a currency sign, and Unicode classifies that sign as a
     * neutral: in a right-to-left paragraph it resolves to the paragraph direction and lands on the
     * far side of the run. Measured on this build, `0.00 US$` is displayed as `$US 0.00`, and the
     * unit a price field prints, `(US$)`, as `($US)` -- the characters in the document are correct,
     * only the order the browser picks for them is wrong.
     *
     * The remedy is the isolate pair from Unicode 6.3 -- `LRI` (U+2066) and `PDI` (U+2069) -- around
     * the token. An isolate gives the token its own left-to-right paragraph, so its characters keep
     * their order, while the token as a whole is still positioned by the surrounding Arabic text and
     * still takes whatever spacing and side the locale asks for. It is used in preference to the
     * older `LRE`/`PDF` embedding because it cannot leak: every isolate is closed by its own `PDI`
     * regardless of what the surrounding text does.
     *
     * Two conditions gate it, and both are required, which is what keeps every other rendering
     * byte-for-byte unchanged:
     *
     * - the interface must be right-to-left. The list below is the same one the admin layout uses
     *   to decide `<html dir>`, so a surface and this helper cannot disagree about direction, and an
     *   English screen is never touched.
     * - the first letter of the token must be Latin. An Arabic symbol such as `ج.م.` or `ر.س.` --
     *   CLDR ends each of those with U+200F -- is already ordered correctly by the script it is
     *   written in, and forcing a left-to-right isolate on it would be wrong.
     */
    protected function isolateCurrencySymbol(string $symbol): string
    {
        if (! in_array(app()->getLocale(), ['ar', 'fa'], true)) {
            return $symbol;
        }

        /**
         * Punctuation and currency signs a symbol may begin with are skipped, so `S$` counts as
         * Latin and a bare `$` -- a single neutral that needs no isolation -- does not.
         */
        if (! preg_match('/^[^\p{L}]*\p{Latin}/u', $symbol)) {
            return $symbol;
        }

        return self::LTR_ISOLATE_START.$symbol.self::LTR_ISOLATE_END;
    }

    /**
     * Format price with base currency symbol. This method also give ability to encode
     * the base currency symbol and its optional.
     *
     * @param  float  $price
     * @return string
     */
    public function formatBasePrice($price)
    {
        if (is_null($price)) {
            $price = 0;
        }

        $formatter = $this->numberFormatter(\NumberFormatter::CURRENCY);

        if (! $formatter) {
            return number_format((float) $price, 2);
        }

        $formatted = $formatter->formatCurrency($price, config('app.currency'));

        $symbol = $this->plainCurrencySymbol((string) config('app.currency'));

        $isolated = $this->isolateCurrencySymbol($symbol);

        /**
         * The token is replaced in the finished string rather than through
         * `NumberFormatter::setSymbol()`, because overriding a symbol means naming the currency on
         * the formatter (`@currency=...`) and that keyword makes ICU drop the `-u-nu-latn` pin for
         * `fa` -- measured, `fa` went from `22,800.00` to `۲۲٬۸۰۰٫۰۰`. Replacing keeps ICU's own
         * placement and spacing, and when no isolation applies -- an English interface, an Arabic
         * symbol -- the string is returned exactly as ICU produced it.
         */
        if ($isolated !== $symbol && $symbol !== '') {
            $formatted = str_replace($symbol, $isolated, $formatted);
        }

        return $formatted;
    }

    /**
     * Build a number formatter for the active locale with the numbering system pinned to Latin.
     *
     * This product does not use Arabic-Indic digits anywhere
     * (`.agents/context/guides/arabic-terminology.md` §2), which is why the Arabic interface ships
     * `آخر 6 أشهر` and `آخر 30 يومًا`. ICU resolves the numbering system from CLDR rather than from
     * us, so the price formatter used to disagree with the strings beside it: measured on this
     * build (ICU 78.2), the locale `ar` formats 20000 as `20,000.00` but `ar_EG` formats it as
     * `٢٠٬٠٠٠٫٠٠`, and the shipped `fa` locale as `۲۰٬۰۰۰٫۰۰` — the same quote, in two numeral
     * systems, on one screen.
     *
     * `-u-nu-latn` pins the numbering system and leaves the language alone, so the currency name,
     * symbol, grouping and placement stay Arabic-correct and only the digit shapes change. Two ICU
     * behaviours this has to work around, both measured rather than assumed:
     *
     * - ICU honours the *first* numbering system in a tag, so appending `-u-nu-latn` to
     *   `ar-u-nu-arab` still formats `arab`. The tag is therefore rebuilt from the parsed subtags.
     * - The older `@numbers=latn` keyword is not used: ICU reads a single `@` block, so
     *   `ar@numbers=latn@currency=EGP` silently loses the currency and `getSymbol()` answers `¤`.
     *   The `-u-` extension composes with the `@currency=` keyword instead.
     *
     * @param  int  $style  A `NumberFormatter` style, e.g. `NumberFormatter::CURRENCY`.
     * @param  string|null  $currencyCode  Currency for the `@currency=` keyword, if the style needs one.
     */
    protected function numberFormatter(int $style, ?string $currencyCode = null): ?\NumberFormatter
    {
        $suffix = $currencyCode === null ? '' : '@currency='.$currencyCode;

        foreach ($this->numberFormatterLocales() as $locale) {
            try {
                $formatter = \NumberFormatter::create($locale.$suffix, $style);
            } catch (\ValueError) {
                /*
                 * PHP 8.5 rejects a syntactically invalid locale here instead of returning null,
                 * so a junk locale would otherwise fail the request instead of falling back.
                 */
                continue;
            }

            if ($formatter) {
                return $formatter;
            }
        }

        return null;
    }

    /**
     * The candidate ICU locales for `numberFormatter()`, most specific first.
     *
     * The active locale with Latin digits, then the active locale as given, then the fallback
     * locale — so an unexpected or malformed locale still formats something sensible.
     *
     * @return array<int, string>
     */
    protected function numberFormatterLocales(): array
    {
        $locale = (string) app()->getLocale();

        /**
         * Parsed rather than concatenated: the locale may already carry a `-u-` extension (or any
         * other subtag `@numbers=latn` cannot override), and ICU would honour that extension's
         * numbering system over one appended to the end.
         */
        $parts = \Locale::parseLocale($locale) ?: [];

        $latin = implode('-', array_filter([
            $parts['language'] ?? null,
            $parts['script'] ?? null,
            $parts['region'] ?? null,
        ]));

        return array_values(array_unique(array_filter(
            [$latin === '' ? null : $latin.'-u-nu-latn', $locale, config('app.fallback_locale', 'en')],
            fn ($candidate) => is_string($candidate) && $candidate !== ''
        )));
    }

    /**
     * Get the config field.
     */
    public function getConfigField(string $fieldName): ?array
    {
        return system_config()->getConfigField($fieldName);
    }

    /**
     * Retrieve information for configuration.
     */
    public function getConfigData(string $field): mixed
    {
        return system_config()->getConfigData($field);
    }
}
