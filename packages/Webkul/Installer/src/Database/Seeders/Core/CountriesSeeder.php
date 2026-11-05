<?php

namespace Webkul\Installer\Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CountriesSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The names come from two files instead of the installer's `installer::app.seeders.*` translation
     * namespace, because they are data and not labels: 255 proper nouns, generated from CLDR rather
     * than written by hand (see `scripts/generate-arabic-country-names.php`), and the same map is read
     * by the migration that renames the rows an older `en` install already wrote. A locale-keyed data
     * file keeps the seeder, that migration and the generator on one source of truth; putting 255
     * country names into each installer locale file would mean two more hand-maintained copies of
     * generated data in files the Arabic glossary reviews string by string.
     *
     * An `en` install is unchanged: `countries.json` is inserted exactly as before. An `ar` install
     * gets `countries-ar.json` by ISO code, so the quote/lead/warehouse address picker reads Arabic
     * under its Arabic label.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('countries')->delete();

        $countries = json_decode(file_get_contents(__DIR__.'/../../../Data/countries.json'), true);

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        if ($defaultLocale === 'ar' || str_starts_with((string) $defaultLocale, 'ar_')) {
            $countries = $this->withArabicNames($countries);
        }

        DB::table('countries')->insert($countries);
    }

    /**
     * Replace the English names in the seeded country list with the generated Arabic ones.
     *
     * A code with no Arabic entry throws rather than falling back to English: the two data files are
     * generated from the same code list, so a gap means the map is stale, and silently seeding an
     * English name into an Arabic install is exactly the defect this file exists to prevent.
     *
     * @param  array  $countries
     * @return array
     */
    protected function withArabicNames($countries)
    {
        $arabic = json_decode(file_get_contents(__DIR__.'/../../../Data/countries-ar.json'), true)['names'] ?? [];

        foreach ($countries as $index => $country) {
            if (! isset($arabic[$country['code']])) {
                throw new RuntimeException(
                    'No Arabic name for the seeded country '.$country['code']
                    .'; regenerate packages/Webkul/Installer/src/Data/countries-ar.json'
                    .' with scripts/generate-arabic-country-names.php'
                );
            }

            $countries[$index]['name'] = $arabic[$country['code']];
        }

        return $countries;
    }
}
