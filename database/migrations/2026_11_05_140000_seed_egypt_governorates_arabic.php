<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives an existing installation Egypt's 27 governorates in Arabic, so the governorate picker under
 * the country Egypt is not empty.
 *
 * `country_states` shipped 568 rows covering 15 countries — Austria, Brazil, Canada, Switzerland,
 * Germany, Estonia, Spain, Finland, France, Croatia, India, Lithuania, Latvia, Romania, the United
 * States — and Egypt was not one of them, so an address form that offered مصر showed no governorate
 * list at all. Egypt is an ordinary case of that gap, not a special one: the other thirteen missing
 * countries an Egyptian demo would reach for, Saudi Arabia and the United Arab Emirates among them,
 * are still empty after this migration and need the same treatment.
 *
 * The 27 rows and their Arabic names are the committed data files, not a map copied into this file:
 * `packages/Webkul/Installer/src/Data/states.json` holds the English name and the ISO 3166-2:EG code
 * of each governorate, and `packages/Webkul/Installer/src/Data/states-ar.json` holds the Arabic name.
 * Reading them, and refusing to run on a list that is not exactly 27 entries per side, is what keeps
 * this migration and the seeder on one source of truth — the same arrangement as
 * `2026_11_05_130000_rename_seeded_countries_to_arabic.php`.
 *
 * Three outcomes per governorate, and what makes a second run a no-op:
 *
 * - No row for Egypt carries the code: the governorate is inserted, in Arabic, against Egypt's
 *   `countries.id` — resolved from `countries.code = 'EG'` rather than hardcoded, because the id is
 *   the database's business. It is not inserted if a row for Egypt already carries that Arabic name
 *   under a different code, so a governorate somebody added by hand is never duplicated.
 * - A row carries the code and still holds the English seeded wording: it is renamed to the Arabic
 *   name. A row whose name somebody already changed — "Cairo" to "القاهرة الجديدة" — is skipped and
 *   left exactly as it is.
 * - A row carries the code and a name this migration did not write: nothing happens.
 *
 * After the first run every governorate row exists with its Arabic name, so no insert and no rename
 * matches again. INSERT and UPDATE only: no DDL, no deletes, no raw SQL, no string interpolation.
 * On a fresh install this runs against an empty table before the seeders do, so the rows it writes
 * are replaced wholesale by `StatesSeeder` a moment later — which is where an `en` install gets its
 * English names back.
 */
return new class extends Migration
{
    /**
     * The English and Arabic state data files, relative to the repository root.
     */
    protected const ENGLISH_DATA = 'packages/Webkul/Installer/src/Data/states.json';

    protected const ARABIC_DATA = 'packages/Webkul/Installer/src/Data/states-ar.json';

    /**
     * The country this migration seeds.
     */
    protected const COUNTRY_CODE = 'EG';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $countryId = DB::table('countries')->where('code', self::COUNTRY_CODE)->value('id');

        /*
         * `country_states.country_id` is a foreign key onto `countries`, so with no Egypt row to
         * point at there is nothing this migration can insert. That is the only case it skips.
         */
        if ($countryId === null) {
            return;
        }

        foreach ($this->governorates() as $code => [$english, $arabic]) {
            $this->seed((int) $countryId, $code, $english, $arabic);
        }
    }

    /**
     * Reverse the migrations.
     *
     * What this restores: the English seeded name of every governorate row that still carries the
     * Arabic name `up()` wrote, matched on Egypt plus the ISO 3166-2 code.
     *
     * What it cannot restore, stated plainly:
     *
     * - The 27 rows stay. This deletes nothing, because it cannot tell a row it inserted apart from
     *   a governorate somebody added by hand afterwards — both look like a row for Egypt with one of
     *   these codes — and deleting a governorate a client created is not a risk worth taking to tidy
     *   up an additive change. After `down()` an installation has 27 English-named rows it did not
     *   have before `up()`, and a re-run of `up()` renames them back.
     * - A governorate renamed after `up()` ran is left exactly as it is. The row no longer holds the
     *   Arabic name this matches on, and there is no record of what it held before, so its earlier
     *   wording is gone — which is correct, because whoever renamed it meant to.
     * - It cannot tell an Arabic name `up()` wrote apart from the same Arabic name typed by hand
     *   afterwards. Both are renamed back to English.
     * - It restores the *seeded* English wording only. Addresses already saved against these codes,
     *   and anything that prints a governorate name, are not revisited — the codes are unchanged, so
     *   a saved address keeps resolving; only its label moves.
     */
    public function down(): void
    {
        foreach ($this->governorates() as $code => [$english, $arabic]) {
            DB::table('country_states')
                ->where('country_code', self::COUNTRY_CODE)
                ->where('code', $code)
                ->where('name', $arabic)
                ->update(['name' => $english]);
        }
    }

    /**
     * Write one governorate: rename the English seeded row, or insert the missing one.
     */
    protected function seed(int $countryId, string $code, string $english, string $arabic): void
    {
        $row = DB::table('country_states')
            ->where('country_code', self::COUNTRY_CODE)
            ->where('code', $code)
            ->first();

        if ($row !== null) {
            if ($row->name === $english) {
                DB::table('country_states')
                    ->where('id', $row->id)
                    ->where('name', $english)
                    ->update(['name' => $arabic]);
            }

            return;
        }

        $alreadyNamed = DB::table('country_states')
            ->where('country_code', self::COUNTRY_CODE)
            ->where('name', $arabic)
            ->exists();

        if ($alreadyNamed) {
            return;
        }

        DB::table('country_states')->insert([
            'country_code' => self::COUNTRY_CODE,
            'code' => $code,
            'name' => $arabic,
            'country_id' => $countryId,
        ]);
    }

    /**
     * Egypt's governorates as `code => [english, arabic]`, read from the two data files.
     *
     * A governorate with no Arabic name, or an Arabic name with no governorate, throws: the two
     * files are the same 27 codes, so a gap means one of them is stale, and quietly seeding half a
     * country in English is exactly the defect this migration exists to prevent.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function governorates(): array
    {
        $english = [];
        $arabic = $this->read(self::ARABIC_DATA)['names'][self::COUNTRY_CODE] ?? [];

        foreach ($this->read(self::ENGLISH_DATA) as $state) {
            if (($state['country_code'] ?? null) !== self::COUNTRY_CODE) {
                continue;
            }

            $english[$state['code']] = $state['name'];
        }

        if (count($english) !== 27 || count($arabic) !== 27) {
            throw new RuntimeException(
                'Expected 27 governorates for '.self::COUNTRY_CODE.' in '.self::ENGLISH_DATA
                .' and '.self::ARABIC_DATA.', found '.count($english).' and '.count($arabic).'.'
            );
        }

        $governorates = [];

        foreach ($english as $code => $name) {
            if (! isset($arabic[$code])) {
                throw new RuntimeException(
                    'No Arabic name for governorate '.self::COUNTRY_CODE.':'.$code.' in '.self::ARABIC_DATA
                );
            }

            $governorates[$code] = [$name, $arabic[$code]];
        }

        return $governorates;
    }

    /**
     * Read one JSON data file, failing loudly rather than silently seeding nothing.
     */
    protected function read(string $path): array
    {
        $contents = file_get_contents(base_path($path));

        if ($contents === false) {
            throw new RuntimeException('Cannot read '.$path.'; the Egypt governorate seed needs it.');
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException($path.' is not valid JSON.');
        }

        return $decoded;
    }
};
