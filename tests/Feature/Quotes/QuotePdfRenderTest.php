<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;
use Webkul\Quote\Models\Quote;
use Webkul\Quote\Models\QuoteItem;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * The quote document is branded, and its line items are laid out for Arabic names.
 *
 * The quote PDF is the document a buyer receives and forwards internally, and it carried no
 * wordmark at all while the admin header, the login screen and the branded error pages did. Its
 * line-item table also crushed the product-name column: mPDF lays an over-constrained table out by
 * shrinking each column towards its minimum width, and a multi-word cell is the only elastic one in
 * that row, so the demo dataset's longest Arabic name printed as six stacked single words.
 *
 * These tests pin what the fix rests on — the mark comes from the brand package and follows the
 * document's script, every line-item column declares its width and the name is the widest of them,
 * and the totals and the right-to-left grand-total rule are untouched — plus one render through the
 * real print route in each locale, which is what would fail if the template stopped compiling.
 *
 * The numbers are the demo quote's: 87,500.00 − 2,500.00 + 12,250.00 = 97,250.00.
 */

/**
 * An administrator with global access, so `preventUnauthorizedAccess()` accepts the quote's owner.
 */
function quotePdfActor(): User
{
    $role = Role::create([
        'name' => 'quote-pdf-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'all',
    ]);

    return User::create([
        'name' => 'Quote PDF Actor',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

/**
 * The demo dataset's quote: the longest Arabic product name it holds, and totals that must not move.
 */
function quotePdfQuote(): Quote
{
    $user = quotePdfActor();

    $person = Person::create([
        'name' => 'منى سعيد',
        'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
        'user_id' => $user->id,
    ]);

    $quote = Quote::create([
        'subject' => 'عرض سعر — تجديد الرخصة و5 مستخدمين',
        'description' => 'العرض ساري 30 يوماً من تاريخ الإصدار، ويشمل التطبيق والترحيل.',
        'billing_address' => [
            'address' => '44 شارع جامعة الدول العربية، المهندسين',
            'postcode' => '12411',
            'city' => 'الجيزة',
            'state' => 'C',
            'country' => 'EG',
        ],
        'shipping_address' => [
            'address' => '44 شارع جامعة الدول العربية، المهندسين',
            'postcode' => '12411',
            'city' => 'الجيزة',
            'state' => 'C',
            'country' => 'EG',
        ],
        'sub_total' => 87500,
        'discount_amount' => 2500,
        'tax_amount' => 12250,
        'adjustment_amount' => 0,
        'grand_total' => 97250,
        'expired_at' => now()->addDays(30),
        'user_id' => $user->id,
        'person_id' => $person->id,
    ]);

    $product = Product::create([
        'sku' => 'SLP-CRM-'.uniqid(),
        'name' => 'رخصة نظام إدارة العملاء — أساسية',
        'price' => 45000,
        'quantity' => 1,
    ]);

    $items = [
        ['SLP-CRM-CORE', 'رخصة نظام إدارة العملاء — أساسية', 1, 45000, 45000, 0, 6300],
        ['SLP-USER-01', 'ترخيص مستخدم إضافي', 5, 3500, 17500, 0, 2450],
        ['SLP-IMPL-BAS', 'حزمة التطبيق والتهيئة', 1, 25000, 25000, 2500, 3500],
    ];

    foreach ($items as [$sku, $name, $quantity, $price, $total, $discount, $tax]) {
        QuoteItem::create([
            'sku' => $sku,
            'name' => $name,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $total,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'product_id' => $product->id,
            'quote_id' => $quote->id,
        ]);
    }

    return $quote->fresh();
}

/**
 * Render the document exactly as `QuoteController::print()` renders it.
 */
function quotePdfHtml(Quote $quote, string $locale): string
{
    app()->setLocale($locale);

    return view('admin::quotes.pdf', compact('quote'))->render();
}

it('carries the brand wordmark in the document header, in the script of the document', function (string $locale, string $wordmark) {
    $html = quotePdfHtml(quotePdfQuote(), $locale);

    $wordmarkPath = base_path('packages/Sijilna/Brand/resources/assets/'.$wordmark);

    // The mark is the brand package's own asset, resolved from the checkout rather than hardcoded.
    expect(is_file($wordmarkPath))->toBeTrue()
        ->and($html)->toContain($wordmarkPath);

    // The other script's cut must not appear: an Arabic document carries the Arabic lockup.
    $otherPath = base_path('packages/Sijilna/Brand/resources/assets/'.($locale === 'ar' ? 'sijilna-wordmark.svg' : 'sijilna-wordmark-ar.svg'));

    expect($html)->not->toContain($otherPath);

    /**
     * It has to be in the header block itself, not merely somewhere in the document, and the title
     * has to be there with it: the header is the block above the quote fields, so a mark in it
     * cannot collide with the date, the quote number or the customer block.
     */
    preg_match('/<div class="page-header">(.*?)<div class="page-content">/s', $html, $header);

    expect($header)->not->toBeEmpty()
        ->and($header[1])->toContain($wordmarkPath)
        ->and($header[1])->toContain(__('admin::app.quotes.index.pdf.title'));
})->with([
    'arabic (right-to-left)' => ['ar', 'sijilna-wordmark-ar.svg'],
    'english (left-to-right)' => ['en', 'sijilna-wordmark.svg'],
]);

it('declares a width for every line-item column and gives the widest one to the product name', function (string $locale) {
    $html = quotePdfHtml(quotePdfQuote(), $locale);

    preg_match('/<div class="items">\s*<table[^>]*>\s*<thead>(.*?)<\/thead>/s', $html, $itemsHead);

    expect($itemsHead)->not->toBeEmpty();

    preg_match_all('/width:\s*([\d.]+)%/', $itemsHead[1], $widths);

    $declared = array_map('floatval', $widths[1]);

    /**
     * Eight columns, and the declared widths must add up to the table: the spare width is the point
     * of the fix, so a column that loses its width silently hands the shortfall back to the
     * product-name column, which is exactly how the name ended up one word per line.
     */
    expect($declared)->toHaveCount(8)
        ->and(array_sum($declared))->toBe(100.0)
        ->and($declared[1])->toBe(max($declared));

    // The name cell is the wide one, and the longest name is in it unwrapped.
    expect($html)->toContain('class="col-product-name"')
        ->and($html)->toContain('رخصة نظام إدارة العملاء — أساسية');
})->with(['ar', 'en']);

it('keeps the quote totals and the grand-total label', function (string $locale) {
    $html = quotePdfHtml(quotePdfQuote(), $locale);

    expect($html)->toContain('87,500.00')
        ->and($html)->toContain('12,250.00')
        ->and($html)->toContain('2,500.00')
        ->and($html)->toContain('97,250.00')
        ->and($html)->toContain(__('admin::app.quotes.index.pdf.grand-total'));
})->with(['ar', 'en']);

/**
 * The `المجموع الكلي` nowrap rule is scoped to `.rtl` so the English document, whose summary is
 * sized for `Grand Total`, is untouched. Scoping it is the whole of that fix: applied to both
 * documents it would leave the English table sized for an Arabic label, and the English document is
 * checked on its own here rather than inferred from the Arabic one.
 */
it('keeps the grand-total nowrap rule scoped to the right-to-left summary', function () {
    $html = quotePdfHtml(quotePdfQuote(), 'ar');

    expect($html)->toMatch('/\.summary table\.rtl td:nth-child\(1\)\s*\{[^}]*white-space:\s*nowrap/')
        ->and($html)->not->toMatch('/\.summary table td:nth-child\(1\)\s*\{[^}]*white-space:\s*nowrap/');
});

/**
 * The two summary separators are the English document's regression gate: `:` for a right-to-left
 * label/amount pair, the shipped `-` for the left-to-right one.
 */
it('keeps each document on its own totals separator', function () {
    $quote = quotePdfQuote();

    expect(quotePdfHtml($quote, 'ar'))->toContain('<td>:</td>')
        ->and(quotePdfHtml($quote, 'ar'))->not->toContain('<td>-</td>')
        ->and(quotePdfHtml($quote, 'en'))->toContain('<td>-</td>')
        ->and(quotePdfHtml($quote, 'en'))->not->toContain('<td>:</td>');
});

it('serves the branded quote as a PDF from the print route', function (string $locale) {
    $quote = quotePdfQuote();

    app()->setLocale($locale);

    $response = test()->actingAs($quote->user, 'user')->get(route('admin.quotes.print', $quote->id));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toStartWith('application/pdf');

    $body = $response->baseResponse instanceof StreamedResponse
        ? $response->streamedContent()
        : $response->getContent();

    expect($body)->toStartWith('%PDF-')
        ->and(strlen($body))->toBeGreaterThan(1000);
})->with(['ar', 'en']);
