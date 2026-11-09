<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Admin\Helpers\Dashboard;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Product as LeadProduct;
use Webkul\Product\Models\Product;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * Every amount the «أفضل المنتجات مبيعاً» panel prints carries its thousands separator, and the
 * dashboard PDF prints the same strings.
 *
 * Neither figure is formatted in the template. Both keys come out of
 * `Webkul\Admin\Helpers\Reporting\Product::getTopSellingProductsByRevenue()`, which runs the raw
 * `products.price` and the `SUM(lead_products.amount)` aggregate through `core()->formatBasePrice()`
 * — the same ICU `NumberFormatter` every other money surface uses — and the dashboard PDF reads
 * those two keys unchanged. The screen and the export therefore cannot disagree unless one of them
 * stops reading them, which is what these tests are here to catch.
 *
 * A screenshot once recorded the panel printing `17,500.00` beside `17500.00`. The figure is not
 * reproducible: `formatBasePrice()` groups at every magnitude, and the payload, the rendered page
 * and the exported PDF all carry the grouped string. What the report did expose is that nothing
 * pinned the invariant, so each surface is asserted against the formatter's own output here rather
 * than against a literal that this one dataset happens to produce.
 */
function topProductsFormattingUser(): User
{
    $role = Role::create([
        'name' => 'top-products-formatting-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'all',
    ]);

    return User::create([
        'name' => 'Top Products Formatting User',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

/**
 * A product sold on a lead closed inside the reporting window, which is what makes it rank: the
 * helper sums `lead_products.amount` over leads whose `closed_at` falls in the range, and drops any
 * product whose sum is not positive.
 */
function topProductsFormattingSale(User $owner, string $price, string $amount): Product
{
    $pipeline = securityTestPipeline();

    $product = Product::create([
        'name' => 'Formatting Product '.uniqid(),
        'sku' => uniqid('fmt-'),
        'price' => $price,
    ]);

    $person = app(PersonRepository::class)->create([
        'name' => 'Formatting Person '.uniqid(),
        'entity_type' => 'persons',
        'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
        'user_id' => $owner->id,
    ]);

    $lead = Lead::create([
        'title' => 'Formatting Lead '.uniqid(),
        'user_id' => $owner->id,
        'person_id' => $person->id,
        'lead_value' => $amount,
        'closed_at' => now()->subWeek(),
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);

    LeadProduct::create([
        'quantity' => 1,
        'price' => $price,
        'amount' => $amount,
        'product_id' => $product->id,
        'lead_id' => $lead->id,
    ]);

    return $product;
}

/**
 * The panel's own request — `/admin/dashboard/stats?type=top-selling-products` — widened to three
 * months, because a row only appears once its lead falls inside the range the screen is showing.
 *
 * @return array<int, array<string, mixed>>
 */
function topProductsFormattingPanel(User $actor): array
{
    $response = test()->actingAs($actor, 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get(route('admin.dashboard.stats').'?type=top-selling-products'
            .'&start='.now()->subMonths(3)->format('Y-m-d')
            .'&end='.now()->format('Y-m-d'));

    $response->assertOk();

    return $response->json('statistics');
}

/**
 * The grouped form, written so it does not assume which character the locale groups with: three
 * digits, then at least one non-digit, then the rest of the integer part. Four-digit amounts and up
 * are the ones a reader needs separated, and they are the ones this asserts.
 */
function topProductsFormattingIsGrouped(string $formatted, int $amount): bool
{
    $digits = strlen((string) abs($amount));

    if ($digits < 4) {
        return true;
    }

    return (bool) preg_match('/\d\D\d{3}/u', $formatted);
}

it('groups both amounts the top-selling panel prints', function () {
    $admin = topProductsFormattingUser();

    $product = topProductsFormattingSale($admin, '17500.0000', '50000.0000');

    $row = collect(topProductsFormattingPanel($admin))->firstWhere('id', $product->id);

    expect($row)->not->toBeNull()
        ->and($row['formatted_price'])->toBe(core()->formatBasePrice('17500.0000'))
        ->and($row['formatted_revenue'])->toBe(core()->formatBasePrice('50000.0000'))
        ->and(topProductsFormattingIsGrouped($row['formatted_price'], 17500))->toBeTrue()
        ->and(topProductsFormattingIsGrouped($row['formatted_revenue'], 50000))->toBeTrue();
});

it('groups the panel\'s amounts at every magnitude the demo data reaches', function () {
    $admin = topProductsFormattingUser();

    $sales = [
        17500 => '17500.0000',
        3500 => '9500.0000',
        120000 => '175000.0000',
    ];

    $products = [];

    foreach ($sales as $amount => $price) {
        $product = topProductsFormattingSale($admin, $price, (string) $amount);

        $products[$product->id] = (int) $amount;
    }

    $rows = collect(topProductsFormattingPanel($admin))->keyBy('id');

    foreach ($products as $id => $amount) {
        expect($rows->has($id))->toBeTrue();

        $row = $rows[$id];

        expect($row['formatted_revenue'])->toBe(core()->formatBasePrice((string) $amount))
            ->and(topProductsFormattingIsGrouped($row['formatted_price'], (int) $row['price']))->toBeTrue()
            ->and(topProductsFormattingIsGrouped($row['formatted_revenue'], $amount))->toBeTrue();
    }
});

it('renders the panel from the formatted keys rather than the raw ones', function () {
    $admin = topProductsFormattingUser();

    $html = test()->actingAs($admin, 'user')
        ->get(route('admin.dashboard.index'))
        ->assertOk()
        ->getContent();

    /**
     * The panel's rows are drawn in the browser from the JSON payload, so the template is the one
     * place a raw aggregate could reach the screen: binding `item.price` or `item.revenue` would
     * print the column as the database stores it (`17500.0000`). The template is inline in the
     * page, so the rendered dashboard is where that binding is visible to a server-side test.
     */
    expect($html)->toContain('item.formatted_price')
        ->and($html)->toContain('item.formatted_revenue')
        ->and($html)->not->toContain('item.price')
        ->and($html)->not->toContain('item.revenue');
});

it('prints the same amounts in the dashboard PDF as in the panel', function () {
    $admin = topProductsFormattingUser();

    $product = topProductsFormattingSale($admin, '17500.0000', '50000.0000');

    $dashboard = app(Dashboard::class);

    $panelRow = collect(topProductsFormattingPanel($admin))->firstWhere('id', $product->id);

    /**
     * The controller hands the helper's collection straight to the PDF view, so the export's
     * figures are exactly these rows.
     */
    $pdfRows = $dashboard->getTopSellingProducts();

    $pdfRow = $pdfRows->firstWhere('id', $product->id);

    expect($pdfRow)->not->toBeNull()
        ->and($pdfRow['formatted_price'])->toBe($panelRow['formatted_price'])
        ->and($pdfRow['formatted_revenue'])->toBe($panelRow['formatted_revenue']);

    $html = view('admin::dashboard.pdf', [
        'logo' => null,
        'startDate' => $dashboard->getStartDate(),
        'endDate' => $dashboard->getEndDate(),
        'revenueStats' => $dashboard->getRevenueStats(),
        'overAllStats' => $dashboard->getOverAllStats(),
        'totalLeadsStats' => $dashboard->getTotalLeadsStats(),
        'revenueBySources' => $dashboard->getLeadsStatsBySources(),
        'revenueByTypes' => $dashboard->getLeadsStatsByTypes(),
        'openLeadsByStates' => $dashboard->getOpenLeadsByStates(),
        'topSellingProducts' => $pdfRows,
        'topPersons' => $dashboard->getTopPersons(),
    ])->render();

    expect($html)->toContain($panelRow['formatted_price'])
        ->and($html)->toContain($panelRow['formatted_revenue'])
        ->and($html)->not->toContain('17500.00')
        ->and($html)->not->toContain('50000.00');
});
