<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the 255 seeded `countries` rows from the English names an `en` install wrote to the Arabic
 * names an `ar` install now gets, so an existing instance reads Arabic in the quote, lead and
 * warehouse address pickers without a reinstall.
 *
 * The two name lists are the committed data files, not a map copied into this file:
 * `packages/Webkul/Installer/src/Data/countries.json` is the English wording the seeder had inserted,
 * and `packages/Webkul/Installer/src/Data/countries-ar.json` is the Arabic map generated from CLDR
 * (`scripts/generate-arabic-country-names.php`). Reading them, rather than duplicating 255 pairs
 * here, is what keeps this migration, the seeder and the generator on one source of truth — the same
 * list the seeder writes is the list this renames, so they cannot drift. Neither file is trusted
 * blindly: an unreadable file throws instead of quietly renaming nothing.
 *
 * Matching, and what makes a second run a no-op:
 *
 * - Every rename is keyed on the stable ISO 3166-1 alpha-2 `code`, so it cannot pick up a country
 *   row somebody inserted by hand, and it cannot match the wrong country if the rows were reordered.
 * - The `name` must still equal the English seeded wording. A country a client or an administrator
 *   renamed — "Egypt" to "مصر الجديدة", or "United States" to "USA" — is therefore skipped, not
 *   overwritten. Nothing a client typed is guessed at.
 * - The Arabic and English names differ for all 255 codes (the generator refuses any name that is
 *   still Latin-only), so after the first run no row matches and a second run changes nothing.
 *
 * UPDATE-only by design: no DDL, no deletes, no raw SQL and no string interpolation. The table has no
 * timestamps, so nothing but `name` is written.
 */
return new class extends Migration
{
    /**
     * The English and Arabic country data files, relative to the repository root.
     */
    protected const ENGLISH_DATA = 'packages/Webkul/Installer/src/Data/countries.json';

    protected const ARABIC_DATA = 'packages/Webkul/Installer/src/Data/countries-ar.json';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->names() as $code => [$english, $arabic]) {
            $this->rename($code, $english, $arabic);
        }
    }

    /**
     * Reverse the migrations.
     *
     * What this restores: the English seeded name for every country row that still carries the Arabic
     * name `up()` wrote, matched on the same ISO code.
     *
     * What it cannot restore, stated plainly:
     *
     * - A country renamed *after* `up()` ran is left exactly as it is. The row no longer holds the
     *   Arabic name this matches on, and there is no record of what it held before, so its earlier
     *   wording is gone — which is correct, because whoever renamed it meant to.
     * - It cannot tell an Arabic name `up()` wrote apart from the same Arabic name typed by hand
     *   afterwards. Both are renamed back to English; a hand-typed Arabic name that happens to equal
     *   the CLDR one is lost.
     * - A country that was English, was renamed by a client, and was therefore skipped by `up()` is
     *   untouched in both directions — `down()` has nothing to undo for it and does not invent one.
     * - It restores the *seeded* English wording only. Anything else — states, addresses, saved
     *   quotes that already print the Arabic name in a PDF — is not revisited.
     */
    public function down(): void
    {
        foreach ($this->names() as $code => [$english, $arabic]) {
            $this->rename($code, $arabic, $english);
        }
    }

    /**
     * Rename one country, matched on its ISO code and on the name it must still hold.
     */
    protected function rename(string $code, string $from, string $to): void
    {
        DB::table('countries')
            ->where('code', $code)
            ->where('name', $from)
            ->update(['name' => $to]);
    }

    /**
     * The seeded name pairs: `code => [english, arabic]`.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function names(): array
    {
        $english = $this->read(self::ENGLISH_DATA);
        $arabic = $this->read(self::ARABIC_DATA)['names'] ?? [];

        $names = [];

        foreach ($english as $country) {
            $code = $country['code'];

            if (! isset($arabic[$code])) {
                throw new RuntimeException(
                    'No Arabic name for the seeded country '.$code.' in '.self::ARABIC_DATA
                );
            }

            $names[$code] = [$country['name'], $arabic[$code]];
        }

        return $names;
    }

    /**
     * Read one JSON data file, failing loudly rather than silently renaming nothing.
     */
    protected function read(string $path): array
    {
        $contents = file_get_contents(base_path($path));

        if ($contents === false) {
            throw new RuntimeException('Cannot read '.$path.'; the country rename needs it.');
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException($path.' is not valid JSON.');
        }

        return $decoded;
    }
};
