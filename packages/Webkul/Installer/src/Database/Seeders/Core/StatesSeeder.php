<?php

namespace Webkul\Installer\Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StatesSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * `states.json` is the English list and is still the only source of which states exist, so a
     * fresh `en` install is byte-identical to before. Where an Arabic name exists for a row,
     * `states-ar.json` supplies it — the same split, and the same reason, as `CountriesSeeder`:
     * proper nouns are data, not labels, and the migration that renames the rows an older install
     * already has reads the same map, so the seeder and that migration cannot drift.
     *
     * The map currently covers Egypt's 27 governorates and nothing else. A country with no entry
     * keeps its English name rather than failing the install, which is what makes it possible to
     * translate one country at a time; a country that *is* covered must be covered completely, and
     * a stale map throws instead of seeding a mix of Arabic and English for it.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('country_states')->delete();

        $states = json_decode(file_get_contents(__DIR__.'/../../../Data/states.json'), true);

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        if ($defaultLocale === 'ar' || str_starts_with((string) $defaultLocale, 'ar_')) {
            $states = $this->withArabicNames($states);
        }

        DB::table('country_states')->insert($states);
    }

    /**
     * Replace the English names of the covered countries with their Arabic ones.
     *
     * The match is on `country_code` plus the state `code`, which is what identifies a row: the
     * code is what an address stores and what the picker submits, so the same pair the map is keyed
     * on is the pair the rename follows.
     *
     * @param  array  $states
     * @return array
     */
    protected function withArabicNames($states)
    {
        $arabic = $this->arabicNames();

        $translated = 0;

        foreach ($states as $index => $state) {
            $name = $arabic[$state['country_code']][$state['code']] ?? null;

            if ($name === null) {
                continue;
            }

            $states[$index]['name'] = $name;

            $translated++;
        }

        $expected = array_sum(array_map('count', $arabic));

        if ($translated !== $expected) {
            throw new RuntimeException(
                'states-ar.json maps '.$expected.' state(s) but only '.$translated
                .' matched a row in states.json; regenerate packages/Webkul/Installer/src/Data/states-ar.json'
            );
        }

        return $states;
    }

    /**
     * The Arabic state names, keyed by country code then state code.
     *
     * An unreadable file throws: a malformed or missing map would otherwise seed every covered
     * country in English on an Arabic install, which is the defect this file exists to prevent.
     *
     * @return array<string, array<string, string>>
     */
    protected function arabicNames()
    {
        $contents = file_get_contents(__DIR__.'/../../../Data/states-ar.json');

        if ($contents === false) {
            throw new RuntimeException('Cannot read packages/Webkul/Installer/src/Data/states-ar.json.');
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded) || ! isset($decoded['names']) || ! is_array($decoded['names'])) {
            throw new RuntimeException('packages/Webkul/Installer/src/Data/states-ar.json is not valid JSON.');
        }

        return $decoded['names'];
    }
}
