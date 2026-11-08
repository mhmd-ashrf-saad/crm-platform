<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductInventory;
use Webkul\Warehouse\Models\Warehouse;

uses(DatabaseTransactions::class);

/**
 * Regression guard for bead crm-m5z.
 *
 * `GET /admin/settings/warehouses/{id}/products` answered 500 on every request because
 * `WarehouseController::products()` returned `admin::settings.warehouses.products`, a Blade view
 * that did not exist. Only the HTML branch was broken: the Ajax branch of the same action already
 * served the datagrid payload, and `routes/breadcrumbs.php` already registered a
 * `settings.warehouses.view.products` trail pointing at the route, so the page was intended and the
 * view was the only missing piece.
 *
 * Two properties are pinned here, because shipping a view alone is not enough:
 *
 *  1. the page renders (the 500 becomes a 200), and it is labelled with the warehouse it belongs
 *     to; and
 *  2. the grid it wraps is scoped to that warehouse. `ProductDataGrid` already filters on
 *     `request()->route('id')`, so an unscoped page under a warehouse heading — every product in
 *     the installation — must never come back.
 */

/**
 * A warehouse carrying the columns the table makes mandatory.
 */
function warehouseProductsPageWarehouse(string $name): Warehouse
{
    return Warehouse::create([
        'name' => $name,
        'contact_name' => 'Warehouse Contact',
        'contact_emails' => ['warehouse@example.test'],
        'contact_numbers' => ['+966500000000'],
        'contact_address' => [],
    ]);
}

/**
 * A product holding stock in exactly the given warehouse.
 */
function warehouseProductsPageProduct(string $sku, int $warehouseId): Product
{
    $product = Product::create([
        'sku' => $sku,
        'name' => 'Product '.$sku,
        'price' => 100,
        'quantity' => 5,
    ]);

    ProductInventory::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouseId,
        'in_stock' => 5,
        'allocated' => 1,
    ]);

    return $product;
}

it('renders the products page of a warehouse', function () {
    $warehouse = warehouseProductsPageWarehouse('Riyadh Warehouse');

    $response = $this->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.settings.warehouses.products.index', $warehouse->id));

    $response->assertOk();

    expect($response->getContent())
        ->toContain('Riyadh Warehouse')
        ->toContain(trans('admin::app.settings.warehouses.products'))
        ->toContain(route('admin.settings.warehouses.products.index', $warehouse->id));
});

it('lists only the products stocked in the warehouse the page belongs to', function () {
    $riyadh = warehouseProductsPageWarehouse('Riyadh Warehouse');
    $jeddah = warehouseProductsPageWarehouse('Jeddah Warehouse');

    warehouseProductsPageProduct('M5Z-RIYADH-ONLY', $riyadh->id);
    warehouseProductsPageProduct('M5Z-JEDDAH-ONLY', $jeddah->id);

    $response = $this->actingAs(getDefaultAdmin(), 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get(route('admin.settings.warehouses.products.index', $riyadh->id));

    $response->assertOk();

    $skus = collect($response->json('records'))->pluck('sku');

    expect($skus)
        ->toContain('M5Z-RIYADH-ONLY')
        ->not->toContain('M5Z-JEDDAH-ONLY');
});

it('answers 404 for a warehouse that does not exist', function () {
    $this->actingAs(getDefaultAdmin(), 'user')
        ->get(route('admin.settings.warehouses.products.index', 999999))
        ->assertNotFound();
});
