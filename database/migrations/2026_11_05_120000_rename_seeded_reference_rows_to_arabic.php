<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the rest of the English reference rows an `en` install wrote to the Arabic wording the
 * installer's own `ar` payload carries, so an existing instance reads in Arabic without a reinstall.
 *
 * `2026_11_04_120000_rename_seeded_arabic_values` covered the four lead seed tables. This one
 * covers every other seeded reference row: the 42 seeded attributes, the two seeded email
 * templates, the two seeded automation workflows and the seeded Administrator role. A fresh `ar`
 * install never reaches these values — the seeders write the `ar` locale directly
 * (`installer::app.seeders.*`) and this migration runs before they do, so it finds empty tables.
 *
 * No other table is touched. `leads`, `quotes`, `persons`, `products`, `tags` and `warehouses` hold
 * records someone typed (a demo or a client wrote them), not seeded reference rows: no seeder in
 * this tree writes a warehouse, a tag or a lead, and `database/seeders/Demo` deletes rather than
 * creates them. `attribute_options` is empty on every instance seen so far, so there are no seeded
 * option labels to rename.
 *
 * Matching, and what makes a second run a no-op:
 *
 * - `attributes` is keyed on `(entity_type, code)` with `is_user_defined = 0`. The code is stable,
 *   it cannot collide with an attribute a client created, and the `name` must still be the English
 *   seed wording — so a row whose name somebody already changed is skipped, not guessed at.
 * - `email_templates`, `workflows` and `roles` have no code column, so they are matched on their
 *   seeded English wording: `name` *and* `subject` together for a template, `name` *and*
 *   `description` together for a workflow and a role, because more than one column is seeded and
 *   any of them can be edited by hand.
 * - Every update requires the English wording to still be in place. After the first run nothing
 *   matches, so running it twice changes nothing.
 *
 * `products.sku` is absent on purpose: its seed name is `SKU` in both locales, so there is nothing
 * to rename and listing it would leave one row matching on every run.
 */
return new class extends Migration
{
    /**
     * Seeded attribute rows: entity type => code => [English seed name, Arabic].
     */
    protected const ATTRIBUTE_NAMES = [
        'leads' => [
            'title' => ['Title', 'العنوان'],
            'description' => ['Description', 'الوصف'],
            'lead_value' => ['Estimated Lead Value', 'القيمة التقديرية للفرصة'],
            'lead_source_id' => ['Source', 'المصدر'],
            'lead_type_id' => ['Type', 'النوع'],
            'user_id' => ['Sales Owner', 'مندوب المبيعات'],
            'expected_close_date' => ['Expected Close Date', 'تاريخ الإغلاق المتوقع'],
            'lead_pipeline_id' => ['Pipeline', 'مسار المبيعات'],
            'lead_pipeline_stage_id' => ['Stage', 'المرحلة'],
        ],
        'persons' => [
            'name' => ['Name', 'الاسم'],
            'emails' => ['Emails', 'البريد الإلكتروني'],
            'contact_numbers' => ['Contact Numbers', 'أرقام الاتصال'],
            'job_title' => ['Job Title', 'المسمى الوظيفي'],
            'user_id' => ['Sales Owner', 'مندوب المبيعات'],
            'organization_id' => ['Organization', 'المؤسسة'],
        ],
        'organizations' => [
            'name' => ['Name', 'الاسم'],
            'address' => ['Address', 'العنوان'],
            'user_id' => ['Sales Owner', 'مندوب المبيعات'],
        ],
        'products' => [
            'name' => ['Name', 'الاسم'],
            'description' => ['Description', 'الوصف'],
            'quantity' => ['Quantity', 'الكمية'],
            'price' => ['Price', 'السعر'],
        ],
        'quotes' => [
            'user_id' => ['Sales Owner', 'مندوب المبيعات'],
            'subject' => ['Subject', 'الموضوع'],
            'description' => ['Description', 'الوصف'],
            'billing_address' => ['Billing Address', 'عنوان الفوترة'],
            'shipping_address' => ['Shipping Address', 'عنوان الشحن'],
            'discount_percent' => ['Discount Percent', 'نسبة الخصم'],
            'discount_amount' => ['Discount Amount', 'مبلغ الخصم'],
            'tax_amount' => ['Tax Amount', 'مبلغ الضريبة'],
            'adjustment_amount' => ['Adjustment Amount', 'مبلغ التعديل'],
            'sub_total' => ['Sub Total', 'المجموع الفرعي'],
            'grand_total' => ['Grand Total', 'المجموع الكلي'],
            'expired_at' => ['Expired At', 'تاريخ الانتهاء'],
            'person_id' => ['Person', 'جهة الاتصال'],
        ],
        'warehouses' => [
            'name' => ['Name', 'الاسم'],
            'description' => ['Description', 'الوصف'],
            'contact_name' => ['Contact Name', 'اسم الاتصال'],
            'contact_emails' => ['Contact Emails', 'البريد الإلكتروني للتواصل'],
            'contact_numbers' => ['Contact Numbers', 'أرقام الاتصال'],
            'contact_address' => ['Contact Address', 'عنوان الاتصال'],
        ],
    ];

    /**
     * Seeded email templates: English seed name => Arabic. The subject is the name followed by the
     * activity-title placeholder, and it is renamed by the same suffix from the same source.
     */
    protected const EMAIL_TEMPLATES = [
        'Activity created' => 'تم إنشاء النشاط',
        'Activity modified' => 'تم تعديل النشاط',
    ];

    /**
     * Seeded automation workflows: English seed wording (name and description) => Arabic.
     */
    protected const WORKFLOWS = [
        'Emails to participants after activity creation' => 'رسائل بريد إلكتروني للمشاركين بعد إنشاء النشاط',
        'Emails to participants after activity updation' => 'رسائل بريد إلكتروني للمشاركين بعد تحديث النشاط',
    ];

    /**
     * Seeded Administrator role: [English name, English description] => [Arabic name, Arabic description].
     */
    protected const ROLE = [
        ['Administrator', 'Administrator Role'],
        ['مسؤول', 'دور المسؤول'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::ATTRIBUTE_NAMES as $entityType => $codes) {
            foreach ($codes as $code => [$english, $arabic]) {
                $this->renameAttribute($entityType, $code, $english, $arabic);
            }
        }

        foreach (self::EMAIL_TEMPLATES as $english => $arabic) {
            $this->renameEmailTemplate($english, $arabic);
        }

        foreach (self::WORKFLOWS as $english => $arabic) {
            $this->renameWorkflow($english, $arabic);
        }

        $this->renameRole(self::ROLE[0], self::ROLE[1]);
    }

    /**
     * Reverse the migrations.
     *
     * Every update in `up()` required the untouched English seed wording to still be in place, so
     * nothing a client wrote was ever replaced and there is no client wording for this to guess at.
     * What it cannot restore is a row that was renamed *after* `up()` ran: those rows no longer
     * carry the Arabic this matches on, so they are left exactly as they are. `products.sku` is not
     * in the map, so it is untouched in both directions.
     */
    public function down(): void
    {
        foreach (self::ATTRIBUTE_NAMES as $entityType => $codes) {
            foreach ($codes as $code => [$english, $arabic]) {
                $this->renameAttribute($entityType, $code, $arabic, $english);
            }
        }

        foreach (self::EMAIL_TEMPLATES as $english => $arabic) {
            $this->renameEmailTemplate($arabic, $english);
        }

        foreach (self::WORKFLOWS as $english => $arabic) {
            $this->renameWorkflow($arabic, $english);
        }

        $this->renameRole(self::ROLE[1], self::ROLE[0]);
    }

    /**
     * Rename one seeded attribute, matched on its entity type and stable code.
     *
     * `is_user_defined = 0` is what makes the row the seeder's rather than a client's, and the
     * `name` predicate is what skips a row somebody already renamed. Neither a second run nor a
     * renamed row matches, so both are left alone.
     */
    protected function renameAttribute(string $entityType, string $code, string $from, string $to): void
    {
        DB::table('attributes')
            ->where('entity_type', $entityType)
            ->where('code', $code)
            ->where('is_user_defined', 0)
            ->where('name', $from)
            ->update(['name' => $to]);
    }

    /**
     * Rename one seeded email template's name and, on that same row only, its matching subject.
     *
     * The subject is rewritten under `name = $from` as well, so it can only ever move on the row
     * whose name is moving: a template whose subject a client rewrote keeps that subject and only
     * its name changes, and a template whose name a client changed keeps its subject even when
     * that subject still reads as seeded.
     *
     * Skipped entirely when a template already carries the target name, so the rename can never
     * leave two rows reading the same. A row that already carries the Arabic name and still the
     * English subject is left as it is: neither run of this migration can produce that state, since
     * both columns move together in the same run, and this migration does not guess at a row whose
     * name somebody has already changed.
     */
    protected function renameEmailTemplate(string $from, string $to): void
    {
        if (DB::table('email_templates')->where('name', $to)->exists()) {
            return;
        }

        DB::table('email_templates')
            ->where('name', $from)
            ->where('subject', $this->templateSubject($from))
            ->update(['subject' => $this->templateSubject($to)]);

        DB::table('email_templates')->where('name', $from)->update(['name' => $to]);
    }

    /**
     * Rename one seeded workflow, matched on the English wording held in both columns.
     */
    protected function renameWorkflow(string $from, string $to): void
    {
        if (DB::table('workflows')->where('name', $to)->exists()) {
            return;
        }

        DB::table('workflows')
            ->where('name', $from)
            ->where('description', $from)
            ->update(['name' => $to, 'description' => $to]);
    }

    /**
     * Rename the seeded Administrator role, matched on the English wording held in both columns.
     *
     * @param  array{0: string, 1: string}  $from
     * @param  array{0: string, 1: string}  $to
     */
    protected function renameRole(array $from, array $to): void
    {
        if (DB::table('roles')->where('name', $to[0])->exists()) {
            return;
        }

        DB::table('roles')
            ->where('name', $from[0])
            ->where('description', $from[1])
            ->update(['name' => $to[0], 'description' => $to[1]]);
    }

    /**
     * The seeded subject for an email template name.
     */
    protected function templateSubject(string $name): string
    {
        return $name.': {%activities.title%}';
    }
};
