<?php

namespace Webkul\Installer\Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * The `core_config` key the admin UI reads its language from.
     */
    public const LOCALE_SETTING = 'general.general.locale_settings.locale';

    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        $this->call(CountriesSeeder::class, false, ['parameters' => $parameters]);
        $this->call(StatesSeeder::class, false, ['parameters' => $parameters]);

        $this->seedLocaleSetting($parameters);
    }

    /**
     * Record the installation's admin language in `core_config`.
     *
     * The `admin_locale` middleware sets the request locale from
     * `general.general.locale_settings.locale`, and `SystemConfig::getConfigData()` answers the
     * field's `default` — `config('app.locale')` — while no row exists. Until now nothing wrote the
     * row, so an install ran on that fallback alone: the settings screen showed the language as
     * something nobody had saved, and any later change to `APP_LOCALE` silently moved the admin UI
     * of a live install. Writing the row at install time makes the language of an installation its
     * own recorded fact, chosen once, changeable afterwards in Configuration → General, and
     * unaffected by a later edit to the environment.
     *
     * Both installer paths reach here — the console command (`krayin-crm:install`) and the web
     * installer, through `DatabaseManager::seeder()` — so this is the one place per install that
     * both cover. The console command passes the locale it also wrote into `.env`; the unattended
     * `--skip-env-check` run, which is how `scripts/provision-dev.sh` installs, passes nothing, and
     * then this falls back to the running application locale for the same reason the seeders beside
     * it do.
     *
     * An existing row is left untouched: `updateOrInsert` would rewrite an operator's choice if the
     * seeders were ever re-run over a live database, and the value here must only ever describe an
     * install that has not made one yet.
     *
     * @param  array  $parameters
     */
    protected function seedLocaleSetting($parameters = []): void
    {
        if (DB::table('core_config')->where('code', self::LOCALE_SETTING)->exists()) {
            return;
        }

        DB::table('core_config')->insert([
            'code' => self::LOCALE_SETTING,
            'value' => $parameters['locale'] ?? config('app.locale'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
