<?php

declare(strict_types=1);

/**
 * scripts/generate-arabic-country-names.php — regenerate the Arabic country-name map.
 *
 * Writes `packages/Webkul/Installer/src/Data/countries-ar.json`, the ISO 3166-1 alpha-2 code =>
 * Arabic name map read by `Webkul\Installer\Database\Seeders\Core\CountriesSeeder` (a new install)
 * and by `database/migrations/*_rename_seeded_countries_to_arabic.php` (an existing one). Keeping
 * the map in a data file is what makes both read paths reproducible without a runtime ICU
 * dependency, and what makes the 255 names reviewable as a diff.
 *
 * ---------------------------------------------------------------------------------------------
 * Source, and why it is not `Locale::getDisplayRegion()`
 * ---------------------------------------------------------------------------------------------
 * The names are CLDR territory names in `ar`, read from the ICU data this machine's PHP was built
 * against — 255 names from one source, nothing written by hand. The obvious call,
 * `Locale::getDisplayRegion('EG', 'ar')`, is broken on ICU 78.2: it returns an empty string for
 * every code, because ICU 74 deprecated `uloc_getDisplayCountry` (the C function PHP's method is a
 * thin wrapper over) and ICU 78 returns length 0 for it — confirmed by calling the C symbol
 * `uloc_getDisplayCountry_78` directly, which also returns 0 with a clean status. That is an
 * ICU/PHP upgrade fault, not a missing data package: the same CLDR "Countries" table is intact.
 *
 * So the names are read through the next ext/intl entry point that reaches that table and still
 * works — `Locale::getDisplayName('und_XX', 'ar')`, which renders the region inside the locale
 * display pattern, `لغة غير معروفة (مصر)`. The wrapper is stripped here, so the stored value is the
 * bare CLDR region name, not a sentence. `Locale::getDisplayRegion()` is still tried first, so this
 * script starts using it again without a change if a future ICU/PHP repairs it.
 *
 * ---------------------------------------------------------------------------------------------
 * One known difference from CLDR, normalised on purpose
 * ---------------------------------------------------------------------------------------------
 * The legacy ICU path renders a region whose own CLDR name carries a parenthetical sub-form with
 * square brackets instead of parentheses — `ميانمار [بورما]` where CLDR (and ICU's modern
 * `LocaleDisplayNames` API, and Node's `Intl.DisplayNames` on the same ICU 78.2) say
 * `ميانمار (بورما)`. Exactly three codes are affected: `CC`, `HK`, `MM`. The script rewrites that
 * one tail sub-form to parentheses and touches nothing else; `--check` prints the affected codes so
 * the transformation stays visible.
 *
 * Usage:
 *   php scripts/generate-arabic-country-names.php           # rewrite the data file
 *   php scripts/generate-arabic-country-names.php --check   # exit 1 if the file is stale
 *
 * Requires `ext-intl` and nothing else — no Composer autoload, no Laravel bootstrap.
 */
const ARABIC_COUNTRY_NAMES = __DIR__.'/../packages/Webkul/Installer/src/Data/countries-ar.json';

const ENGLISH_COUNTRY_NAMES = __DIR__.'/../packages/Webkul/Installer/src/Data/countries.json';

const TARGET_LOCALE = 'ar';

/**
 * Bidi controls a country name has no business carrying: they render as nothing but reorder the
 * text around them, which turns a correct name into a broken one on the one screen that shows it.
 */
const FORBIDDEN_CHARACTERS = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u';

/**
 * Read the English seed file so the map covers exactly the codes the seeder inserts, in the same
 * order — a code list in the script would drift from the data file it is meant to describe.
 *
 * @return list<string>
 */
function seededCodes(): array
{
    $english = json_decode((string) file_get_contents(ENGLISH_COUNTRY_NAMES), true);

    if (! is_array($english) || $english === []) {
        fwrite(STDERR, 'Cannot read the English country seed file: '.ENGLISH_COUNTRY_NAMES."\n");
        exit(1);
    }

    return array_map(static fn (array $row): string => (string) $row['code'], $english);
}

/**
 * The Arabic region name for one ISO code, or null when ICU has none.
 */
function arabicRegionName(string $code): ?string
{
    /** The direct call, which works only where ICU still serves `uloc_getDisplayCountry`. */
    $direct = Locale::getDisplayRegion($code, TARGET_LOCALE);

    if (is_string($direct) && $direct !== '' && $direct !== $code) {
        return $direct;
    }

    /* The fallback: strip the `und` language wrapper off the locale display name. */
    $wrapper = Locale::getDisplayLanguage('und', TARGET_LOCALE);

    $rendered = Locale::getDisplayName('und_'.$code, TARGET_LOCALE);

    if (! is_string($rendered) || $rendered === '' || $wrapper === false) {
        return null;
    }

    $prefix = $wrapper.' (';

    if (! str_starts_with($rendered, $prefix) || ! str_ends_with($rendered, ')')) {
        return null;
    }

    return substr($rendered, strlen($prefix), -1);
}

/**
 * Replace ICU's square-bracketed sub-form with CLDR's parenthetical one. Narrow on purpose: it
 * matches only a trailing ` [..]` and leaves every other name byte-identical.
 */
function normaliseParentheticals(string $name): string
{
    if (preg_match('/^(?<head>.+) \[(?<tail>[^\]]+)\]$/u', $name, $matches) === 1) {
        return $matches['head'].' ('.$matches['tail'].')';
    }

    return $name;
}

/**
 * Reject anything that would ship a wrong name: empty, still English, or carrying a bidi control.
 */
function assertValidName(string $code, string $name): void
{
    $problems = [];

    if ($name === '') {
        $problems[] = 'empty';
    }

    if (preg_match('/^[\x20-\x7E]+$/', $name) === 1) {
        $problems[] = 'Latin-only, so ICU fell back to English';
    }

    if (preg_match(FORBIDDEN_CHARACTERS, $name) === 1) {
        $problems[] = 'contains a bidi control character';
    }

    if (preg_match('//u', $name) !== 1) {
        $problems[] = 'is not valid UTF-8';
    }

    if ($problems !== []) {
        fwrite(STDERR, sprintf("Code %s: %s\n", $code, implode('; ', $problems)));
        exit(1);
    }
}

/** @return array<string, string> */
function buildMap(): array
{
    $map = [];

    foreach (seededCodes() as $code) {
        $name = arabicRegionName($code);

        if ($name === null) {
            fwrite(STDERR, sprintf("ICU returned no Arabic name for %s\n", $code));
            exit(1);
        }

        $name = normaliseParentheticals($name);

        assertValidName($code, $name);

        $map[$code] = $name;
    }

    return $map;
}

/** @return array<string, mixed> */
function buildDocument(array $map): array
{
    return [
        '_meta' => [
            'what' => 'ISO 3166-1 alpha-2 code => country name in Arabic (CLDR territory names).',
            'source' => 'CLDR territory names as delivered by ICU '.INTL_ICU_VERSION.'; read with PHP '
                .PHP_VERSION.' ext-intl, never typed by hand.',
            'generator' => 'scripts/generate-arabic-country-names.php',
            'api' => 'Locale::getDisplayName(\'und_<code>\', \'ar\'), wrapper stripped'
                .' (Locale::getDisplayRegion returns empty on this ICU)',
            'count' => count($map),
            'normalised' => 'ICU\'s legacy path brackets a region\'s own parenthetical sub-form; '
                .'the trailing "[..]" is rewritten to "(..)" to match CLDR. Codes affected: CC, HK, MM.',
            'read_by' => 'Webkul\\Installer\\Database\\Seeders\\Core\\CountriesSeeder and '
                .'database/migrations/*_rename_seeded_countries_to_arabic.php',
        ],
        'names' => $map,
    ];
}

$check = in_array('--check', array_slice($argv, 1), true);

$map = buildMap();

$document = buildDocument($map);

$json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

if ($check) {
    $committed = is_file(ARABIC_COUNTRY_NAMES) ? (string) file_get_contents(ARABIC_COUNTRY_NAMES) : '';

    if ($committed !== $json) {
        fwrite(STDERR, ARABIC_COUNTRY_NAMES." is stale; run scripts/generate-arabic-country-names.php\n");
        exit(1);
    }

    fwrite(STDOUT, sprintf("countries-ar.json is current (%d names, ICU %s)\n", count($map), INTL_ICU_VERSION));
    exit(0);
}

if (file_put_contents(ARABIC_COUNTRY_NAMES, $json) === false) {
    fwrite(STDERR, 'Cannot write '.ARABIC_COUNTRY_NAMES."\n");
    exit(1);
}

$bracketed = array_keys(array_filter($map, static fn (string $name): bool => str_contains($name, ' (')));

fwrite(STDOUT, sprintf(
    "Wrote %s\n%d names, ICU %s, PHP %s\nnames whose CLDR form carries a parenthetical: %s\n",
    ARABIC_COUNTRY_NAMES,
    count($map),
    INTL_ICU_VERSION,
    PHP_VERSION,
    implode(', ', $bracketed) ?: 'none',
));
