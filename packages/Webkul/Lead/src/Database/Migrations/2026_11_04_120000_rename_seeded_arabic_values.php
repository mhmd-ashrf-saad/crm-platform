<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the English seed rows an `en` install wrote to the Arabic terms approved in
 * `.agents/context/guides/arabic-terminology.md` §5, so an existing instance reads in Arabic
 * without a reinstall. A fresh `ar` install never reaches these values: the installer seeds from
 * the `ar` locale directly (`installer:seeders.lead.*`), and this migration runs before the
 * seeders do, so it finds empty tables.
 *
 * Only the four seed tables are touched — `leads`, `quotes` and everything else reference these
 * rows by id, so no other table needs a change.
 *
 * `lead_pipeline_stages` is the only one of the four with a `code` column, so the stages are
 * matched on `code`: that survives a client who edited a stage name. Stages a client created
 * through the pipeline editor carry a null `code`, so they cannot match. The other three tables
 * have no stable code, so they are matched on the English seed name — a client rename there is
 * left alone on purpose rather than guessed at.
 */
return new class extends Migration
{
    /**
     * Seeded stage code => [English seed name as the installer writes it, approved Arabic].
     */
    protected const STAGE_NAMES = [
        'new' => ['New', 'جديد'],
        'follow-up' => ['Follow Up', 'متابعة'],
        'prospect' => ['Prospect', 'عميل محتمل'],
        'negotiation' => ['Negotiation', 'مفاوضات'],
        'won' => ['Won', 'مكتسب'],
        'lost' => ['Lost', 'مفقود'],
    ];

    /**
     * Seeded English name => approved Arabic.
     */
    protected const SOURCE_NAMES = [
        'Email' => 'البريد الإلكتروني',
        'Web' => 'ويب',
        'Web Form' => 'نموذج ويب',
        'Phone' => 'الهاتف',
        'Direct' => 'مباشر',
    ];

    /**
     * Seeded English name => approved Arabic.
     */
    protected const TYPE_NAMES = [
        'New Business' => 'عمل جديد',
        'Existing Business' => 'عمل قائم',
    ];

    /**
     * Seeded English name => approved Arabic.
     */
    protected const PIPELINE_NAMES = [
        'Default Pipeline' => 'مسار المبيعات الافتراضي',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::STAGE_NAMES as $code => [, $arabic]) {
            $this->renameStage($code, $arabic);
        }

        foreach ([
            'lead_sources' => self::SOURCE_NAMES,
            'lead_types' => self::TYPE_NAMES,
            'lead_pipelines' => self::PIPELINE_NAMES,
        ] as $table => $names) {
            foreach ($names as $english => $arabic) {
                $this->rename($table, $english, $arabic);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * This restores the English seed names, and it is lossy for a client who had renamed a
     * pipeline stage themselves: `up()` matched the stages on `code`, so it replaced that custom
     * name and nothing here knows what it was. Stages are therefore reset to the seed wording,
     * not to the client's. Sources, types and the pipeline were matched on the English seed name,
     * so a client's own wording there was never overwritten and is still in place.
     */
    public function down(): void
    {
        foreach (self::STAGE_NAMES as $code => [$english]) {
            $this->renameStage($code, $english);
        }

        foreach ([
            'lead_sources' => self::SOURCE_NAMES,
            'lead_types' => self::TYPE_NAMES,
            'lead_pipelines' => self::PIPELINE_NAMES,
        ] as $table => $names) {
            foreach ($names as $english => $arabic) {
                $this->rename($table, $arabic, $english);
            }
        }
    }

    /**
     * Rename one seeded stage, matched on its stable code.
     *
     * The target name is skipped when it already sits on that row, which is what makes a second
     * run change nothing. A stage whose code has no entry in the map is never touched.
     */
    protected function renameStage(string $code, string $target): void
    {
        DB::table('lead_pipeline_stages')
            ->where('code', $code)
            ->where(fn ($query) => $query->whereNull('name')->orWhere('name', '<>', $target))
            ->update(['name' => $target]);
    }

    /**
     * Rename rows in a table that has no code column, matched on the current name.
     *
     * Skipped when the target name is already taken: `lead_pipelines.name` is unique, and a source,
     * type or pipeline someone already created under the Arabic name must not collide with it or
     * leave two rows reading the same. The same guard makes a second run a no-op — after the first
     * run the English name matches nothing.
     */
    protected function rename(string $table, string $from, string $to): void
    {
        if (DB::table($table)->where('name', $to)->exists()) {
            return;
        }

        DB::table($table)->where('name', $from)->update(['name' => $to]);
    }
};
