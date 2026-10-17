<?php

namespace Sijilna\Brand\Console\Commands;

use Illuminate\Console\Command;
use Sijilna\Brand\Brand;
use Webkul\Core\Repositories\CoreConfigRepository;

require_once __DIR__.'/../../Brand.php';

/**
 * Seeds the Sijilna branding values into `core_config`.
 *
 * The provider's config defaults are what make a fresh clone brand-correct with
 * no command at all. This command exists for the second half of that job: the
 * Settings screen renders each field from its `core_config` ROW when one exists
 * and from the config file's own `default` when it does not — so with no row, an
 * operator opening General → Settings sees the vendor's `#0E90D9` in the colour
 * field and an empty logo field, and saving writes those back over the brand.
 * Seeding the rows makes the approved values visible and editable.
 *
 * The write goes through CoreConfigRepository::create() with the same nested
 * form-data shape the admin's Settings screen posts, so the stored row is
 * indistinguishable from a save made in the UI — including the HTML
 * sanitisation the repository applies to rich-text fields.
 */
class BrandCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sijilna:brand
                            {--dry-run : Report what would be written without writing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the Sijilna branding values (footer credit) into the configuration.';

    /**
     * Execute the console command.
     */
    public function handle(CoreConfigRepository $configurationRepository): int
    {
        /**
         * The footer credit is always rewritten: it is copy the operator
         * approved, and a stale vendor credit in the row would win over the
         * provider default. Every other value is created ONLY when its row is
         * absent, so a client instance that chose its own colour, logo or
         * favicon is never overwritten by a later run of this command, and
         * `general.settings.footer.show` keeps its existing semantics (`show`
         * defaults to 1 in code; a row that turned it off stays off).
         */
        $fields = [
            'general.settings.footer.label' => Brand::FOOTER_CREDIT,
        ];

        foreach ([
            'general.settings.footer.show' => 1,
            'general.settings.menu_color.brand_color' => Brand::PRIMARY_COLOR,
            'general.general.admin_logo.logo_image' => Brand::PUBLISHED_LOGO_PATH,
            'general.general.admin_logo.favicon_image' => Brand::PUBLISHED_FAVICON_PATH,
        ] as $code => $value) {
            if (! $this->hasRow($configurationRepository, $code)) {
                $fields[$code] = $value;
            }
        }

        foreach ($fields as $code => $value) {
            $this->line(sprintf(
                '%s `%s`',
                $this->hasRow($configurationRepository, $code) ? 'update' : 'create',
                $code
            ));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        /**
         * The nested shape is what ConfigurationController::store() receives
         * from the Settings form; CoreConfigRepository::create() flattens it to
         * the dotted codes before persisting, and upserts per code.
         */
        $configurationRepository->create($this->nested($fields));

        $this->info('Sijilna branding seeded.');

        return self::SUCCESS;
    }

    /**
     * Build the nested Settings-form shape from flat dotted field codes, so the
     * list above stays a single readable `code => value` map.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function nested(array $fields): array
    {
        $tree = [];

        foreach ($fields as $code => $value) {
            $ref = &$tree;

            foreach (explode('.', $code) as $segment) {
                $ref = &$ref[$segment];
            }

            $ref = $value;

            unset($ref);
        }

        return $tree;
    }

    /**
     * Does a core_config row exist for the given code?
     */
    protected function hasRow(CoreConfigRepository $configurationRepository, string $code): bool
    {
        return $configurationRepository->findOneWhere(['code' => $code]) !== null;
    }
}
