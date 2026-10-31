<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\DB;

/**
 * Removes the demo dataset, and only the demo dataset, before it is rebuilt.
 *
 * Two rules drive the order below:
 *
 * 1. **Dependency order.** Every row is deleted after the rows that point at it, so no foreign
 *    key is ever violated and the wipe never relies on `ON DELETE CASCADE` behaviour that the
 *    migrations do not actually declare.
 * 2. **Installed reference data is untouchable.** Pipelines, stages, sources, types, roles,
 *    attributes, attribute options and the installer's email templates survive every run. So does
 *    any user whose address is not on the reserved demo domain — the operator logs in with one of
 *    those, and DemoSeeder asserts afterwards that not a byte of it changed.
 */
class DemoWiper
{
    /**
     * Entity types whose attribute values belong to this dataset.
     */
    public const MANAGED_ATTRIBUTE_ENTITY_TYPES = [
        'leads',
        'persons',
        'organizations',
        'products',
        'quotes',
    ];

    /**
     * Delete every row owned by the demo dataset.
     */
    public function wipe(): void
    {
        $this->wipeActivities();

        $this->wipeEmails();

        $this->wipeQuotes();

        $this->wipeLeads();

        $this->wipePersons();

        $this->wipeOrganizations();

        $this->wipeProducts();

        $this->wipeTags();

        $this->wipeMarketing();

        $this->wipeWebForms();

        $this->wipeAttributeValues();

        $this->wipeDemoUsers();
    }

    /**
     * Activities and every pivot that references one.
     */
    protected function wipeActivities(): void
    {
        DB::table('lead_activities')->delete();
        DB::table('person_activities')->delete();
        DB::table('product_activities')->delete();
        DB::table('warehouse_activities')->delete();

        DB::table('activity_files')->delete();
        DB::table('activity_participants')->delete();

        DB::table('activities')->delete();
    }

    /**
     * Email threads, their children, attachments and tags.
     */
    protected function wipeEmails(): void
    {
        DB::table('email_tags')->delete();
        DB::table('email_attachments')->delete();

        /**
         * `parent_id` is a self-referencing foreign key, so replies go before their roots.
         */
        DB::table('emails')->whereNotNull('parent_id')->delete();
        DB::table('emails')->delete();
    }

    /**
     * Quotes, their items and the lead links.
     */
    protected function wipeQuotes(): void
    {
        DB::table('lead_quotes')->delete();
        DB::table('quote_items')->delete();
        DB::table('quotes')->delete();
    }

    /**
     * Leads and their product lines and tags.
     */
    protected function wipeLeads(): void
    {
        DB::table('lead_products')->delete();
        DB::table('lead_tags')->delete();

        DB::table('leads')->delete();
    }

    /**
     * Contacts and everything that points at one.
     */
    protected function wipePersons(): void
    {
        DB::table('person_tags')->delete();
        DB::table('contact_export_batch_items')->delete();

        DB::table('persons')->delete();
    }

    /**
     * Organizations.
     */
    protected function wipeOrganizations(): void
    {
        DB::table('organizations')->delete();
    }

    /**
     * Products, their tag pivots and their warehouse stock rows.
     */
    protected function wipeProducts(): void
    {
        DB::table('product_tags')->delete();

        /**
         * A stock row cannot outlive its product, and the demo seeds no warehouses of its own;
         * only rows that would block the product delete are removed.
         */
        DB::table('product_inventories')
            ->whereIn('product_id', DB::table('products')->select('id'))
            ->delete();

        DB::table('products')->delete();
    }

    /**
     * Tags and every pivot that references one, including the warehouse pivot the demo does not
     * use itself — its tag is going away, so the pivot must go first.
     */
    protected function wipeTags(): void
    {
        DB::table('warehouse_tags')->delete();

        DB::table('tags')->delete();
    }

    /**
     * Campaigns, the demo's marketing events and the demo's email templates.
     *
     * The two email templates the installer seeds ("Activity created" and "Activity modified") are
     * installed reference data and are matched by name, never deleted wholesale.
     */
    protected function wipeMarketing(): void
    {
        DB::table('marketing_campaigns')->delete();

        DB::table('marketing_events')
            ->whereIn('name', array_column(DemoFixtures::marketingEvents(), 'name'))
            ->delete();

        DB::table('email_templates')
            ->whereIn('name', array_column(DemoFixtures::emailTemplates(), 'name'))
            ->delete();
    }

    /**
     * Web forms and their field definitions.
     */
    protected function wipeWebForms(): void
    {
        DB::table('web_form_attributes')->delete();
        DB::table('web_forms')->delete();
    }

    /**
     * Attribute values for the entities this dataset owns.
     *
     * Scoped by entity type so a warehouse's attribute values — which this seeder never writes —
     * are left alone.
     */
    protected function wipeAttributeValues(): void
    {
        DB::table('attribute_values')
            ->whereIn('entity_type', self::MANAGED_ATTRIBUTE_ENTITY_TYPES)
            ->delete();
    }

    /**
     * The demo users, and only them.
     *
     * The domain is the boundary: `where('email', 'like', '%@sijilna.demo')` matches an address
     * ending in that domain and nothing else. Every dependent row is cleared first so the delete
     * cannot fail on a foreign key, and any row elsewhere that happens to reference a demo user
     * (a role's `created_by`, another user's `created_by`) is nulled rather than cascaded.
     */
    protected function wipeDemoUsers(): void
    {
        $demoUserIds = DB::table('users')
            ->where('email', 'like', DemoIdentity::RESERVED_EMAIL_PATTERN)
            ->pluck('id')
            ->all();

        if ($demoUserIds === []) {
            return;
        }

        DB::table('user_groups')->whereIn('user_id', $demoUserIds)->delete();

        /**
         * `user_password_resets` keys on the email address, not on the user id.
         */
        DB::table('user_password_resets')
            ->whereIn('email', DB::table('users')->whereIn('id', $demoUserIds)->pluck('email'))
            ->delete();

        DB::table('notifications')->whereIn('user_id', $demoUserIds)->delete();
        DB::table('google_contact_accounts')->whereIn('user_id', $demoUserIds)->delete();
        DB::table('contact_export_batches')->whereIn('user_id', $demoUserIds)->delete();

        DB::table('users')->whereIn('created_by', $demoUserIds)->update(['created_by' => null]);
        DB::table('roles')->whereIn('created_by', $demoUserIds)->update(['created_by' => null]);

        DB::table('users')->whereIn('id', $demoUserIds)->delete();
    }
}
