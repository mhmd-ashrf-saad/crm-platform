<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\ProductRepository as LeadProductRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Quote\Repositories\QuoteRepository;
use Webkul\Tag\Repositories\TagRepository;
use Webkul\WebForm\Repositories\WebFormAttributeRepository;
use Webkul\WebForm\Repositories\WebFormRepository;

/**
 * Seeds the sales half of the demo: organizations, contacts, products, tags, leads, quotes,
 * activities and web forms.
 *
 * Everything goes through the repositories the admin screens use, so the model events, attribute
 * values and pivots the product expects are created exactly as a real save would create them.
 * The only direct table writes are the timestamp backdates at the end of each method: `created_at`
 * is not fillable on any of these models, and the demo needs a spread of dates for the dashboard
 * and the reports to have something to show.
 */
class DemoSalesSeeder
{
    /**
     * How many days back the oldest lead is dated, so every report range has data.
     */
    public const HISTORY_DAYS = 75;

    /**
     * Source names the installer seeds, in the order DemoFixtures::leads() refers to them.
     *
     * Resolved by name rather than by id: a re-install or an operator edit must not silently
     * re-point a lead at a different source.
     */
    public const LEAD_SOURCE_NAMES = ['Email', 'Web', 'Web Form', 'Phone', 'Direct'];

    /**
     * Type names the installer seeds, in fixture order.
     */
    public const LEAD_TYPE_NAMES = ['New Business', 'Existing Business'];

    public function __construct(
        protected OrganizationRepository $organizationRepository,
        protected PersonRepository $personRepository,
        protected ProductRepository $productRepository,
        protected TagRepository $tagRepository,
        protected LeadRepository $leadRepository,
        protected QuoteRepository $quoteRepository,
        protected ActivityRepository $activityRepository,
        protected WebFormRepository $webFormRepository,
    ) {}

    /**
     * Seed the sales half and return the extended context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function seed(array $context): array
    {
        $context = array_merge($context, $this->referenceData());

        $context['organizations'] = $this->seedOrganizations($context);
        $context['persons'] = $this->seedPersons($context);
        $context['products'] = $this->seedProducts($context);
        $context['tags'] = $this->seedTags($context);
        $context['leads'] = $this->seedLeads($context);

        $this->seedQuotes($context);
        $this->seedLeadProductLines($context);
        $this->seedActivities($context);
        $this->seedWebForms($context);

        return $context;
    }

    /**
     * Read the installed pipeline, stages, sources, types and attribute codes.
     *
     * Nothing here is created: the installer owns all of it, and a lead pointing at a stage id that
     * does not exist breaks the kanban and the datagrids.
     *
     * @return array<string, mixed>
     */
    protected function referenceData(): array
    {
        $pipeline = app(PipelineRepository::class)->getDefaultPipeline();

        if (! $pipeline) {
            throw new \RuntimeException('No lead pipeline exists in this installation; run the installer first.');
        }

        $stages = [];

        foreach ($pipeline->stages as $stage) {
            $stages[$stage->code] = $stage->id;
        }

        foreach (['new', 'follow-up', 'prospect', 'negotiation', 'won', 'lost'] as $required) {
            if (! isset($stages[$required])) {
                throw new \RuntimeException(
                    "The default pipeline [{$pipeline->name}] has no [{$required}] stage. "
                    .'The demo dataset needs all six installed stages; restore them from a fresh installation.'
                );
            }
        }

        $sources = [];

        foreach (self::LEAD_SOURCE_NAMES as $index => $name) {
            $source = app(SourceRepository::class)->findOneByField('name', $name);

            if (! $source) {
                throw new \RuntimeException("The installed lead source [{$name}] is missing.");
            }

            $sources[$index + 1] = $source->id;
        }

        $types = [];

        foreach (self::LEAD_TYPE_NAMES as $index => $name) {
            $type = app(TypeRepository::class)->findOneByField('name', $name);

            if (! $type) {
                throw new \RuntimeException("The installed lead type [{$name}] is missing.");
            }

            $types[$index + 1] = $type->id;
        }

        return [
            'pipeline_id' => $pipeline->id,
            'stages' => $stages,
            'sources' => $sources,
            'types' => $types,
        ];
    }

    /**
     * Organizations (شركات).
     *
     * @param  array<string, mixed>  $context
     * @return array<int, int>
     */
    protected function seedOrganizations(array $context): array
    {
        $owner = $context['users']['manager']->id;

        $ids = [];

        foreach (DemoFixtures::organizations() as $index => $fixture) {
            $organization = $this->organizationRepository->create([
                'entity_type' => 'organizations',
                'name' => $fixture['name'],
                'address' => [
                    'address' => $fixture['address'],
                    'city' => $fixture['city'],
                    'state' => '',
                    'postcode' => $fixture['postcode'],
                    'country' => 'EG',
                ],
                'user_id' => $owner,
            ]);

            $ids[$index] = $organization->id;

            $this->backdate('organizations', $organization->id, $this->daysAgo(self::HISTORY_DAYS - $index));
        }

        return $ids;
    }

    /**
     * Contacts (جهات اتصال).
     *
     * @param  array<string, mixed>  $context
     * @return array<int, int>
     */
    protected function seedPersons(array $context): array
    {
        $owners = [
            $context['users']['manager']->id,
            $context['users']['agent']->id,
            $context['users']['support']->id,
        ];

        $ids = [];

        foreach (DemoFixtures::persons() as $index => $fixture) {
            $person = $this->personRepository->create([
                'entity_type' => 'persons',
                'name' => $fixture['name'],
                'emails' => [
                    ['label' => 'work', 'value' => $fixture['email']],
                ],
                'contact_numbers' => [
                    ['label' => 'work', 'value' => $fixture['phone']],
                ],
                'job_title' => $fixture['job_title'],
                'organization_id' => $fixture['organization'] === null ? null : $context['organizations'][$fixture['organization']],
                'user_id' => $owners[$index % count($owners)],
            ]);

            $ids[$index] = $person->id;

            $this->backdate('persons', $person->id, $this->daysAgo(self::HISTORY_DAYS - $index * 2));
        }

        return $ids;
    }

    /**
     * Products (منتجات).
     *
     * Creating through the repository also writes the products attribute set, which is what the
     * product screen and its attribute values read.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, int>
     */
    protected function seedProducts(array $context): array
    {
        $ids = [];

        foreach (DemoFixtures::products() as $index => $fixture) {
            $product = $this->productRepository->create([
                'entity_type' => 'products',
                'name' => $fixture['name'],
                'sku' => $fixture['sku'],
                'description' => $fixture['description'],
                'price' => $fixture['price'],
                'quantity' => $fixture['quantity'],
            ]);

            $ids[$fixture['sku']] = $product->id;

            $this->backdate('products', $product->id, $this->daysAgo(self::HISTORY_DAYS - $index * 3));
        }

        return $ids;
    }

    /**
     * Tags (وسوم).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, int>
     */
    protected function seedTags(array $context): array
    {
        $ids = [];

        foreach (DemoFixtures::tags() as $fixture) {
            $tag = $this->tagRepository->create([
                'name' => $fixture['name'],
                'color' => $fixture['color'],
                'user_id' => $context['users']['manager']->id,
            ]);

            $ids[$fixture['name']] = $tag->id;
        }

        return $ids;
    }

    /**
     * Leads (فرص), spread across every installed stage.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, int>
     */
    protected function seedLeads(array $context): array
    {
        $owners = [
            $context['users']['agent']->id,
            $context['users']['manager']->id,
            $context['users']['agent']->id,
            $context['users']['support']->id,
        ];

        $ids = [];

        foreach (DemoFixtures::leads() as $index => $fixture) {
            $createdAt = $this->daysAgo($fixture['created_days_ago']);

            $stageCode = $fixture['stage'];

            $closedAt = isset($fixture['closed_days_ago'])
                ? $this->daysAgo($fixture['closed_days_ago'])
                : null;

            $expectedCloseDate = in_array($stageCode, ['won', 'lost'], true)
                ? $closedAt->copy()->format('Y-m-d')
                : $this->inDays($fixture['close_in_days'])->format('Y-m-d');

            $lead = $this->leadRepository->create([
                'entity_type' => 'leads',
                'title' => $fixture['title'],
                'description' => $fixture['description'],
                'lead_value' => $fixture['value'],
                'status' => 1,
                'lost_reason' => $fixture['lost_reason'] ?? null,
                'closed_at' => $closedAt,
                'expected_close_date' => $expectedCloseDate,
                'user_id' => $owners[$index % count($owners)],
                'person_id' => $context['persons'][$fixture['person']],
                'lead_source_id' => $context['sources'][$fixture['source']],
                'lead_type_id' => $context['types'][$fixture['type']],
                'lead_pipeline_id' => $context['pipeline_id'],
                'lead_pipeline_stage_id' => $context['stages'][$stageCode],
            ]);

            $ids[$index] = $lead->id;

            foreach ($fixture['tags'] as $tagName) {
                DB::table('lead_tags')->insert([
                    'tag_id' => $context['tags'][$tagName],
                    'lead_id' => $lead->id,
                ]);
            }

            $this->backdate('leads', $lead->id, $createdAt);
        }

        /**
         * A few contacts carry tags as well, so the tag filter has something to match on more than
         * one grid.
         */
        DB::table('person_tags')->insert([
            ['tag_id' => $context['tags']['عميل مميز'], 'person_id' => $context['persons'][1]],
            ['tag_id' => $context['tags']['متابعة عاجلة'], 'person_id' => $context['persons'][3]],
            ['tag_id' => $context['tags']['إحالة'], 'person_id' => $context['persons'][12]],
        ]);

        DB::table('product_tags')->insert([
            ['tag_id' => $context['tags']['عميل مميز'], 'product_id' => $context['products']['SLP-CRM-CORE']],
            ['tag_id' => $context['tags']['عرض سعر مرسل'], 'product_id' => $context['products']['SLP-SUP-ANN']],
        ]);

        return $ids;
    }

    /**
     * Quotes (عروض أسعار) with real line items and totals that add up the way the quote screen
     * computes them: grand total = Σ(price × quantity + tax − discount) + adjustment.
     *
     * @param  array<string, mixed>  $context
     */
    protected function seedQuotes(array $context): void
    {
        foreach (DemoFixtures::quotes() as $fixture) {
            $items = [];

            $subTotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;

            foreach ($fixture['items'] as $item) {
                $product = $this->productBySku($context, $item['sku']);

                $base = round($item['price'] ?? $product->price, 2) * $item['quantity'];

                $discount = (float) ($item['discount_amount'] ?? 0);

                $tax = round($base * ((float) ($item['tax_percent'] ?? 0) / 100), 2);

                $items[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'discount_percent' => 0,
                    'discount_amount' => $discount,
                    'tax_percent' => $item['tax_percent'] ?? 0,
                    'tax_amount' => $tax,
                    'total' => $base,
                ];

                $subTotal += $base;
                $discountTotal += $discount;
                $taxTotal += $tax;
            }

            $adjustment = (float) ($fixture['adjustment_amount'] ?? 0);

            $quote = $this->quoteRepository->create([
                'entity_type' => 'quotes',
                'subject' => $fixture['subject'],
                'description' => $fixture['description'],
                'billing_address' => $this->addressFor($fixture['person']),
                'shipping_address' => $this->addressFor($fixture['person']),
                'discount_percent' => 0,
                'discount_amount' => round($discountTotal, 2),
                'tax_amount' => round($taxTotal, 2),
                'adjustment_amount' => round($adjustment, 2),
                'sub_total' => round($subTotal, 2),
                'grand_total' => round($subTotal + $taxTotal - $discountTotal + $adjustment, 2),
                'expired_at' => $this->inDays($fixture['expires_in_days'])->endOfDay(),
                'person_id' => $context['persons'][$fixture['person']],
                'user_id' => $context['users']['manager']->id,
                'items' => $items,
                'lead_id' => $context['leads'][$fixture['lead']],
            ]);

            /**
             * The quote-to-lead relation is a pivot, and the quote screen reads it from there.
             */
            DB::table('lead_quotes')->insert([
                'quote_id' => $quote->id,
                'lead_id' => $context['leads'][$fixture['lead']],
            ]);

            $this->backdate('quotes', $quote->id, $this->daysAgo($fixture['created_days_ago']));
        }
    }

    /**
     * Product lines on a handful of open leads that carry no quote, so the lead's product tab is
     * not empty for them either.
     *
     * @param  array<string, mixed>  $context
     */
    protected function seedLeadProductLines(array $context): void
    {
        $lines = [
            1 => [['SLP-POS-01', 4], ['SLP-USER-01', 6]],
            4 => [['SLP-TRN-05', 2]],
            7 => [['SLP-SUP-ANN', 1]],
            10 => [['SLP-REP-01', 1]],
            12 => [['SLP-CRM-CORE', 1], ['SLP-POS-01', 4]],
        ];

        $repository = app(LeadProductRepository::class);

        foreach ($lines as $leadIndex => $products) {
            foreach ($products as [$sku, $quantity]) {
                $product = $this->productBySku($context, $sku);

                $repository->create([
                    'lead_id' => $context['leads'][$leadIndex],
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $product->price,
                    'amount' => round($product->price * $quantity, 2),
                ]);
            }
        }
    }

    /**
     * Activities (أنشطة) of every type this product writes, spread across past, today and future.
     *
     * @param  array<string, mixed>  $context
     */
    protected function seedActivities(array $context): void
    {
        foreach (DemoFixtures::activities() as $fixture) {
            $startsAt = $this->inDays($fixture['schedule_in_days'])->setTimeFromTimeString($fixture['at']);

            $endsAt = $fixture['duration'] > 0
                ? $startsAt->copy()->addMinutes($fixture['duration'])
                : null;

            $activity = $this->activityRepository->create([
                'title' => $fixture['title'],
                'type' => $fixture['type'],
                'location' => $fixture['location'],
                'comment' => $fixture['comment'],
                'schedule_from' => $startsAt,
                'schedule_to' => $endsAt,
                'is_done' => $fixture['is_done'],
                'user_id' => $context['users'][$fixture['user']]->id,
            ]);

            if (isset($fixture['lead'])) {
                DB::table('lead_activities')->insert([
                    'activity_id' => $activity->id,
                    'lead_id' => $context['leads'][$fixture['lead']],
                ]);
            }

            if (isset($fixture['person'])) {
                DB::table('person_activities')->insert([
                    'activity_id' => $activity->id,
                    'person_id' => $context['persons'][$fixture['person']],
                ]);
            }

            /**
             * Participants make the calendar entry visible to the whole demo team, which is what
             * the overlap check and the "activities of my colleagues" view read.
             */
            DB::table('activity_participants')->insert([
                'activity_id' => $activity->id,
                'user_id' => $context['users']['agent']->id,
            ]);

            $this->backdate('activities', $activity->id, $startsAt->copy()->subHour());
        }
    }

    /**
     * Web forms (نماذج الويب) and the leads/contacts their submissions produce.
     *
     * There is no submissions table in this product: a submission creates a lead (when the form is
     * a lead form) and a person, linked to the "Web Form" source. Those rows are seeded here so the
     * leads gained from a form are visible in the pipeline with the right source.
     *
     * @param  array<string, mixed>  $context
     */
    protected function seedWebForms(array $context): void
    {
        $attributeRepository = app(AttributeRepository::class);
        $webFormAttributeRepository = app(WebFormAttributeRepository::class);

        foreach (DemoFixtures::webForms() as $index => $fixture) {
            $form = $this->webFormRepository->create([
                'title' => $fixture['title'],
                'description' => $fixture['description'],
                'submit_button_label' => $fixture['submit_button_label'],
                'submit_success_action' => $fixture['submit_success_action'],
                'submit_success_content' => $fixture['submit_success_content'],
                'create_lead' => $fixture['create_lead'],
                'lead_pipeline_id' => $context['pipeline_id'],
                'background_color' => $fixture['background_color'],
                'form_background_color' => $fixture['form_background_color'],
                'form_title_color' => $fixture['form_title_color'],
                'form_submit_button_color' => $fixture['form_submit_button_color'],
                'attribute_label_color' => $fixture['attribute_label_color'],
                'attributes' => [],
            ]);

            foreach ($fixture['attributes'] as $field) {
                $attribute = $attributeRepository->findOneWhere([
                    'entity_type' => $field['entity_type'],
                    'code' => $field['code'],
                ]);

                if (! $attribute) {
                    throw new \RuntimeException(
                        "The attribute [{$field['entity_type']}.{$field['code']}] required by the web form "
                        ."[{$fixture['title']}] does not exist in this installation."
                    );
                }

                $webFormAttributeRepository->create([
                    'web_form_id' => $form->id,
                    'attribute_id' => $attribute->id,
                    'name' => $attribute->name,
                    'placeholder' => $field['placeholder'],
                    'is_required' => $field['is_required'],
                    'is_hidden' => 0,
                    'sort_order' => $field['sort_order'],
                ]);
            }

            $this->seedWebFormSubmissions($context, $fixture, $index);
        }
    }

    /**
     * The leads and contacts a web form submission produces.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $form
     */
    protected function seedWebFormSubmissions(array $context, array $form, int $formIndex): void
    {
        foreach ($form['submissions'] as $offset => $submission) {
            $person = $this->personRepository->create([
                'entity_type' => 'persons',
                'name' => $submission['name'],
                'emails' => [
                    ['label' => 'work', 'value' => $submission['email']],
                ],
                'contact_numbers' => isset($submission['phone'])
                    ? [['label' => 'work', 'value' => $submission['phone']]]
                    : [],
                'job_title' => $submission['job_title'] ?? null,
                'user_id' => $context['users']['agent']->id,
            ]);

            $submittedAt = $this->daysAgo(4 + $offset * 3 + $formIndex);

            $this->backdate('persons', $person->id, $submittedAt);

            if (! $form['create_lead']) {
                continue;
            }

            $lead = $this->leadRepository->create([
                'entity_type' => 'leads',
                'title' => $submission['title'],
                'description' => $submission['description'],
                'lead_value' => 0,
                'status' => 1,
                'user_id' => $context['users']['agent']->id,
                'person_id' => $person->id,
                'lead_source_id' => $context['sources'][3],
                'lead_type_id' => $context['types'][1],
                'lead_pipeline_id' => $context['pipeline_id'],
                'lead_pipeline_stage_id' => $context['stages']['new'],
                'expected_close_date' => $this->inDays(21 + $offset)->format('Y-m-d'),
            ]);

            $this->backdate('leads', $lead->id, $submittedAt);
        }
    }

    /**
     * Resolve a product by SKU from the seeded map.
     *
     * @param  array<string, mixed>  $context
     */
    protected function productBySku(array $context, string $sku): object
    {
        if (! isset($context['products'][$sku])) {
            throw new \RuntimeException("The demo fixture refers to the unknown product SKU [{$sku}].");
        }

        return $this->productRepository->find($context['products'][$sku]);
    }

    /**
     * Billing/shipping address for a quote, taken from the contact's organization.
     *
     * @return array<string, mixed>
     */
    protected function addressFor(int $personIndex): array
    {
        $person = DemoFixtures::persons()[$personIndex];

        if ($person['organization'] === null) {
            return [
                'address' => 'القاهرة',
                'city' => 'القاهرة',
                'state' => '',
                'postcode' => '11511',
                'country' => 'EG',
            ];
        }

        $organization = DemoFixtures::organizations()[$person['organization']];

        return [
            'address' => $organization['address'],
            'city' => $organization['city'],
            'state' => '',
            'postcode' => $organization['postcode'],
            'country' => 'EG',
        ];
    }

    /**
     * Today, going back the given number of days.
     */
    protected function daysAgo(int $days): Carbon
    {
        return Carbon::today()->subDays($days);
    }

    /**
     * Today, going forward the given number of days.
     */
    protected function inDays(int $days): Carbon
    {
        return Carbon::today()->addDays($days);
    }

    /**
     * Move a row's timestamps without touching the model, so no extra activity entry is logged.
     */
    protected function backdate(string $table, int $id, Carbon $createdAt, ?Carbon $updatedAt = null): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt ?? $createdAt,
        ]);
    }
}
