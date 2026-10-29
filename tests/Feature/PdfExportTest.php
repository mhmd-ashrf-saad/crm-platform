<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Core\Models\CoreConfig;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * A PDF export must announce itself as a PDF, in every locale.
 *
 * `Webkul\Core\Traits\PDFHandler::downloadPDF()` renders the document correctly, but the mPDF branch
 * -- the one taken for Arabic and Hebrew, where the directionality handling lives -- returns its
 * bytes through `response()->streamDownload()` without a MIME type, so the response keeps the
 * framework default of `text/html; charset=utf-8`. The download still works because of the
 * `Content-Disposition` attachment header; API clients, PDF viewers that sniff `Content-Type` and
 * corporate proxies do not.
 *
 * Both files that would be the natural place to fix it are upstream Krayin code, which this fork may
 * not edit because every `2.2` merge would collide on them. The correction therefore lives in
 * `Webkul\Admin\Http\Middleware\ForcePdfContentType`, registered on the `web` middleware group, and
 * these tests pin it across both rendering branches: mPDF (`ar`, streamed) and dompdf (`en`).
 */
function pdfExportAdmin(): User
{
    $role = Role::create([
        'name' => 'pdf-export-'.uniqid(),
        'description' => 'test',
        'permission_type' => 'all',
    ]);

    return User::create([
        'name' => 'PDF Export User',
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);
}

/**
 * The admin locale is a platform-wide configuration value read on each request by the
 * `admin_locale` middleware, which is what decides the mPDF/dompdf branch.
 */
function pdfExportLocale(string $locale): void
{
    CoreConfig::query()->updateOrCreate(
        ['code' => 'general.general.locale_settings.locale'],
        ['value' => $locale]
    );
}

it('sends the dashboard export as application/pdf', function (string $locale, bool $streamed) {
    pdfExportLocale($locale);

    $response = test()->actingAs(pdfExportAdmin(), 'user')->get(route('admin.dashboard.export_pdf'));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toStartWith('application/pdf');

    /*
     * Pin the branch as well as the header: `ar` is the mPDF branch that returned text/html, and
     * asserting the response shape proves the locale really reached `PDFHandler` instead of the
     * test passing through the dompdf path twice.
     */
    expect($response->baseResponse instanceof StreamedResponse)->toBe($streamed);

    $body = $streamed
        ? $response->streamedContent()
        : $response->getContent();

    // The body is still the document the renderer produced; the middleware only sets a header.
    expect($body)->toStartWith('%PDF-')
        ->and($body)->toContain('%%EOF')
        ->and(strlen($body))->toBeGreaterThan(1000);
})->with([
    'arabic (mPDF, streamed)' => ['ar', true],
    'english (dompdf, regular)' => ['en', false],
]);
