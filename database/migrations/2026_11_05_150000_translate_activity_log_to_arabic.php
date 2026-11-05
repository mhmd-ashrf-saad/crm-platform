<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites the English change-log every record's audit trail still shows, so the Arabic interface
 * stops rendering two languages on one screen.
 *
 * What the rows look like, and why they are stale rather than still being produced:
 *
 * `Webkul\Activity\Traits\LogsActivity` writes the activity `title` with `trans()` and the label
 * inside `additional` with `AttributeValueRepository::getAttributeLabel()` — both *at write time*.
 * The 463 `type = 'system'` rows on the reference instance were all written in one two-second window
 * (`2026-11-02 01:57:11` to `01:57:13`, a demo-seeding run) while the instance was still `en`, so
 * they froze `Updated Stage`, `Updated Sales Owner`, `Created`, `New`, `Web Form`, `New Business`,
 * `Default Pipeline` and `Wed Nov 25, 2026` into the database. The interface then moved to `ar`, the
 * seeded reference rows were renamed by `2026_11_04_120000_rename_seeded_arabic_values` and
 * `2026_11_05_120000_rename_seeded_reference_rows_to_arabic`, and the stored log no longer matched
 * anything on screen: the panel above it reads `جديد`, `عمل جديد`, `نموذج ويب`.
 *
 * The writer itself is already correct and is deliberately not touched here: a change made today
 * under `ar` writes `تم تحديث المرحلة` with `{"new": {"label": "عميل محتمل", "value": 3}, ...}`.
 * This migration is data hygiene for rows written before the locale moved, not a fix for the writer.
 *
 * The guards, and what makes a second run a no-op:
 *
 * - Only `type = 'system'` rows are read. Every other row is a title somebody typed — a call, a
 *   meeting, a note — and no locale ever produced it, so it is never touched.
 * - A row is rewritten only when its `title` is *byte-exactly* the wording the writer produced:
 *   `Updated <English attribute name>`, or `Created`. A row whose title somebody edited, or whose
 *   English wording came from a customised `en` locale file, is skipped whole — title and labels
 *   both — because a row that has already been edited is not this migration's to rewrite.
 * - The English attribute name is read from `additional.attribute`, which the writer stored as the
 *   attribute's own name and which this migration does not change, so the map stays findable. That
 *   name resolves to the attribute's stable `code`, and the replacement Arabic name comes from
 *   `attributes.name` — never from a list in this file — so it cannot drift from the seeded Arabic.
 *   (Where the two locales share a name — `SKU` is `SKU` in both — the template is translated and the
 *   name stays as it is, which is exactly what the Arabic interface writes today.)
 * - A label moves only when it can be resolved from the row's own `value`: a select or lookup
 *   attribute is resolved through its `lookup_type` table (`lead_pipeline_stages`, `lead_sources`,
 *   `lead_types`, `lead_pipelines`, `users`, `organizations`, `persons`), and a date through the same
 *   pattern the product formats it with. A label that is already Arabic, that equals the name its
 *   `value` resolves to today, whose `value` no longer exists, or whose attribute is text, price,
 *   email, phone or address, is left exactly as it is. Nothing is guessed.
 * - `value`, `comment`, `type`, `is_done`, `user_id` and every timestamp are untouched. `additional`
 *   is rewritten only through its own decoded array, so its sibling keys survive; `attribute` inside
 *   it keeps the English wording the writer stored, because the activity timeline renders
 *   `old.label` and `new.label` only, and because translating it would leave a second run unable to
 *   find its own rows.
 *
 * UPDATE only: no DDL, no deletes, no inserts, no raw SQL, no string interpolation. An installation
 * that never moved to Arabic is a complete no-op: `lead_pipeline_stage_id` still carries its English
 * name there, so `translate()` returns before reading a single activity.
 */
return new class extends Migration
{
    /**
     * English attribute name as the writer stored it in `additional.attribute` => the attribute's
     * stable code, which is how the current Arabic name is looked up. The names on the left are the
     * `en` wording of the seeded attributes, and they are the exact wording this migration matches
     * on — which is what skips a row somebody has already edited.
     */
    protected const ATTRIBUTE_CODES = [
        'Title' => 'title',
        'Description' => 'description',
        'Estimated Lead Value' => 'lead_value',
        'Source' => 'lead_source_id',
        'Type' => 'lead_type_id',
        'Sales Owner' => 'user_id',
        'Expected Close Date' => 'expected_close_date',
        'Pipeline' => 'lead_pipeline_id',
        'Stage' => 'lead_pipeline_stage_id',
        'Name' => 'name',
        'Emails' => 'emails',
        'Contact Numbers' => 'contact_numbers',
        'Job Title' => 'job_title',
        'Organization' => 'organization_id',
        'Price' => 'price',
        'Quantity' => 'quantity',
        'SKU' => 'sku',
        'Address' => 'address',
        'Contact Name' => 'contact_name',
        'Contact Emails' => 'contact_emails',
        'Contact Address' => 'contact_address',
        'Subject' => 'subject',
        'Person' => 'person_id',
        'Expired At' => 'expired_at',
        'Billing Address' => 'billing_address',
        'Shipping Address' => 'shipping_address',
        'Discount Percent' => 'discount_percent',
        'Discount Amount' => 'discount_amount',
        'Tax Amount' => 'tax_amount',
        'Adjustment Amount' => 'adjustment_amount',
        'Sub Total' => 'sub_total',
        'Grand Total' => 'grand_total',
    ];

    /**
     * The `Created` title in both wordings: [what `en` writes, what `ar` writes].
     */
    protected const CREATED = ['Created', 'تم الإنشاء'];

    /**
     * The `Updated :attribute` template in both wordings, without the attribute name.
     */
    protected const UPDATED = ['Updated ', 'تم تحديث '];

    /**
     * One attribute whose Arabic name proves this instance has been moved to Arabic. While it is
     * still English nothing is rewritten, which is what keeps an `en` installation untouched.
     */
    protected const ARABIC_WITNESS = 'lead_pipeline_stage_id';

    /**
     * The tables a seeded attribute may point at through `lookup_type`. A client can edit that column,
     * and it is interpolated into a table name below, so it is whitelisted rather than trusted.
     */
    protected const LOOKUP_TABLES = [
        'lead_pipelines',
        'lead_pipeline_stages',
        'lead_sources',
        'lead_types',
        'organizations',
        'persons',
        'users',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->translate(false);
    }

    /**
     * Reverse the migrations.
     *
     * What this restores: the English `title` of every row that still carries the Arabic wording
     * `up()` wrote — `تم تحديث المرحلة` back to `Updated Stage`, `تم الإنشاء` back to `Created` — and
     * only where the row's own `additional.attribute` still names its attribute in English.
     *
     * What it cannot restore, stated plainly:
     *
     * - The labels. `up()` replaced `New` with the Arabic name held in `lead_pipeline_stages`, and that
     *   English wording is gone: the earlier rename migrations replaced it in the table itself. There
     *   is no record of it anywhere, so inventing one would be worse than leaving the Arabic in place.
     *   After `down()` a row reads `Updated Stage: جديد -> عميل محتمل` — English title, Arabic labels.
     * - A date label. The rendering is locale-driven and both directions are the same call, so `down()`
     *   cannot tell an Arabic date it wrote from one the writer produced under `ar`. Dates stay Arabic.
     * - A row rewritten, or edited, after `up()` ran. It no longer carries the exact Arabic wording
     *   this matches on, so it is left exactly as it is.
     * - Whether the Arabic title it finds was written by `up()` or typed by hand afterwards. Both read
     *   the same, so both go back to English.
     */
    public function down(): void
    {
        $this->translate(true);
    }

    /**
     * Rewrite every stale row, in the `up()` or the `down()` direction.
     */
    protected function translate(bool $reverse): void
    {
        $attributes = $this->attributes();

        $witness = $attributes[self::ARABIC_WITNESS] ?? null;

        if ($witness === null || ! $this->isArabic($witness['name'])) {
            return;
        }

        DB::table('activities')
            ->where('type', 'system')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($attributes, $reverse) {
                foreach ($rows as $row) {
                    $this->rewrite($row, $attributes, $reverse);
                }
            });
    }

    /**
     * Rewrite one activity row, or leave it exactly as it is.
     */
    protected function rewrite(object $row, array $attributes, bool $reverse): void
    {
        $title = (string) $row->title;

        $additional = $this->payload($row);

        /*
         * A payload that is not a JSON object is not something this migration can reason about, and
         * half-reading it would be a guess. The row is skipped whole.
         */
        if ($additional === false) {
            return;
        }

        $changes = [];

        if ($additional !== null) {
            $wording = (string) ($additional['attribute'] ?? '');

            $code = self::ATTRIBUTE_CODES[$wording] ?? null;

            $attribute = $code === null ? null : ($attributes[$code] ?? null);

            if ($attribute !== null) {
                [$from, $to] = $reverse
                    ? [self::UPDATED[1].$attribute['name'], self::UPDATED[0].$wording]
                    : [self::UPDATED[0].$wording, self::UPDATED[1].$attribute['name']];

                if ($title === $from) {
                    $changes['title'] = $to;

                    if (! $reverse) {
                        $labels = $this->translateLabels($additional, $attribute);

                        if ($labels !== null) {
                            $changes['additional'] = json_encode($labels, JSON_UNESCAPED_UNICODE);
                        }
                    }
                }
            }
        }

        if ($title === ($reverse ? self::CREATED[1] : self::CREATED[0])) {
            $changes['title'] = $reverse ? self::CREATED[0] : self::CREATED[1];
        }

        if ($changes === []) {
            return;
        }

        DB::table('activities')
            ->where('id', $row->id)
            ->update($changes);
    }

    /**
     * The row's `additional` payload: an array, null when the row carries none, or false when it
     * carries something that is not a JSON object.
     *
     * Read through `json_decode()` rather than a JSON SQL function so the migration behaves the same
     * on MySQL's `json` column and on a plain text column.
     */
    protected function payload(object $row): array|false|null
    {
        if ($row->additional === null || $row->additional === '') {
            return null;
        }

        $decoded = json_decode((string) $row->additional, true);

        return is_array($decoded) ? $decoded : false;
    }

    /**
     * The `additional` payload with its two labels in Arabic, or null when neither label can be
     * resolved and the payload would therefore be unchanged.
     *
     * Forward only: `down()` cannot put the English wording back, see its own note.
     */
    protected function translateLabels(array $additional, array $attribute): ?array
    {
        /*
         * Only two kinds of label are locale-driven. A select or lookup label is a name held in
         * another table; a date label is a formatted date. A text label is the value itself, a price
         * label is a currency rendering that is the same in both locales, and an email or phone label
         * is the stored value with an option key appended — none of those is English because of the
         * locale, so none of them is rewritten.
         */
        if ($attribute['lookup'] !== '') {
            $resolve = fn (mixed $value): ?string => is_numeric($value)
                ? $this->lookupName($attribute['lookup'], (int) $value)
                : null;
        } elseif (in_array($attribute['type'], ['date', 'datetime'], true)) {
            $resolve = fn (mixed $value): ?string => $this->formatDate($value, $attribute['type']);
        } else {
            return null;
        }

        $changed = false;

        foreach (['new', 'old'] as $side) {
            if (! isset($additional[$side]) || ! is_array($additional[$side])) {
                continue;
            }

            $label = $additional[$side]['label'] ?? null;

            if (! is_string($label) || $label === '') {
                continue;
            }

            if ($this->isArabic($label)) {
                continue;
            }

            $expected = $resolve($additional[$side]['value'] ?? null);

            if ($expected === null || $expected === '' || $expected === $label) {
                continue;
            }

            $additional[$side]['label'] = $expected;

            $changed = true;
        }

        return $changed ? $additional : null;
    }

    /**
     * The current name of one lookup row, or null when the row is gone or holds no name.
     */
    protected function lookupName(string $table, int $id): ?string
    {
        if (! in_array($table, self::LOOKUP_TABLES, true)) {
            return null;
        }

        $name = DB::table($table)->where('id', $id)->value('name');

        return is_string($name) ? $name : null;
    }

    /**
     * One date or datetime value formatted the way the product formats it.
     *
     * This repeats the pattern and the locale from
     * `Webkul\Attribute\Repositories\AttributeValueRepository::getAttributeLabel()` instead of calling
     * it: a migration has to keep working after that method moves, changes signature or needs a
     * container it may no longer be given. The Arabic locale is written out rather than read from
     * `app()->getLocale()` because the caller has already established that this instance's attribute
     * names are Arabic, so Arabic is the only rendering that can match what the interface is showing.
     */
    protected function formatDate(mixed $value, string $type): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $date->locale('ar')->translatedFormat(
            $type === 'datetime' ? 'D M d, Y H:i A' : 'D M d, Y'
        );
    }

    /**
     * Every attribute, keyed by its stable code: `code => ['name' => …, 'type' => …, 'lookup' => …]`.
     *
     * A code is not unique across entity types — `user_id`, `name` and `description` each belong to
     * several — so the first row by id is taken. That is safe for the columns involved: every row
     * sharing one of those codes carries the same Arabic name and the same lookup table, so the
     * choice cannot change the outcome.
     */
    protected function attributes(): array
    {
        $attributes = [];

        foreach (DB::table('attributes')->orderBy('id')->get(['code', 'name', 'type', 'lookup_type']) as $attribute) {
            $code = (string) $attribute->code;

            if ($code === '' || isset($attributes[$code]) || (string) $attribute->name === '') {
                continue;
            }

            $attributes[$code] = [
                'name' => (string) $attribute->name,
                'type' => (string) $attribute->type,
                'lookup' => (string) ($attribute->lookup_type ?? ''),
            ];
        }

        return $attributes;
    }

    /**
     * Does this string hold Arabic letters? The test that separates a stale label from one that is
     * already Arabic, and that keeps this migration off an install that never moved to Arabic.
     */
    protected function isArabic(?string $value): bool
    {
        if ($value === null || ! mb_check_encoding($value, 'UTF-8')) {
            return false;
        }

        return preg_match('/\p{Arabic}/u', $value) === 1;
    }
};
