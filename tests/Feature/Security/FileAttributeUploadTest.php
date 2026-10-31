<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Attribute\Repositories\AttributeValueRepository;

uses(DatabaseTransactions::class);

/**
 * A `file` attribute accepted any upload at all.
 *
 * `AttributeForm` appended an extension allowlist for `image` attributes only; a `file` attribute
 * fell through to the bare `nullable` branch, so a document reached the public disk — the disk the
 * web server hands back verbatim — with no rule applied to it. A stored `.html` is then same-origin
 * content and a stored `.php` is offered to the PHP handler.
 *
 * This is the admin-side counterpart of `WebFormFileUploadTest`, which covers the same defect on the
 * public form. The list is an allowlist (`AttributeForm::FILE_EXTENSIONS`) rather than a blocklist
 * of dangerous names: a document field has no business accepting a format nobody listed, and only
 * an allowlist keeps closing the gap as new formats appear.
 */
beforeEach(function () {
    /**
     * A directory of this test's own. `Storage::fake()` hands every caller the same directory and
     * empties it on the way in, so tests sharing a process — and the suite's parallel workers —
     * clear each other's uploads.
     */
    config([
        'filesystems.disks.public.root' => sys_get_temp_dir().'/krayin-file-attribute-'.bin2hex(random_bytes(8)),
        'filesystems.disks.public.driver' => 'local',
    ]);

    Storage::forgetDisk('public');

    $this->document = Attribute::create([
        'code' => 'document_'.uniqid(),
        'name' => 'Document',
        'type' => 'file',
        'entity_type' => 'persons',
        'is_required' => 0,
        'is_unique' => 0,
        'quick_add' => 0,
        'sort_order' => 99,
    ]);
});

/**
 * Submit the person create form, which is the surface `AttributeForm` validates.
 *
 * `entity_type` travels with the payload because that is the field the form request scopes its
 * attribute rules by — the header's quick-create modal posts it. Without it no attribute rule is
 * built at all, which is why the repository repeats the check where the file is written.
 */
function storeDocumentThroughPersonForm(Attribute $attribute, UploadedFile $file)
{
    return test()
        ->actingAs(getDefaultAdmin(), 'user')
        ->post('/admin/contacts/persons/create', [
            'entity_type' => 'persons',
            'name' => 'Document Owner '.uniqid(),
            $attribute->code => $file,
        ]);
}

/**
 * The path the attribute value holds, or null when nothing was kept.
 */
function storedDocumentPath(Attribute $attribute): ?string
{
    return AttributeValue::query()
        ->where('attribute_id', $attribute->id)
        ->value('text_value');
}

it('stores a document a client actually sends', function (string $name) {
    $response = storeDocumentThroughPersonForm($this->document, UploadedFile::fake()->create($name, 64));

    $response->assertRedirect(route('admin.contacts.persons.index'));

    $path = storedDocumentPath($this->document);

    expect($path, $name.' should have been stored')->not->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
})->with([
    'contract.pdf',
    'offer.docx',
    'ledger.xlsx',
    'handover.zip',
    'notes.txt',
    'export.csv',
]);

it('refuses active content submitted for a file attribute', function (string $name) {
    $response = storeDocumentThroughPersonForm($this->document, UploadedFile::fake()->create($name, 8));

    $response->assertSessionHasErrors($this->document->code);

    expect(storedDocumentPath($this->document))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'payload.html',
    'payload.htm',
    'payload.xhtml',
    'payload.svg',
    'payload.js',
    'payload.php',
]);

it('refuses an html payload published to the world as an html page', function () {
    /**
     * The rule is checked against the extension Laravel resolves for the upload, so the file is not
     * stored under a name of the client's choosing either. This case is the one the defect was
     * reported on: a file meant to be served back by the web server.
     */
    $response = storeDocumentThroughPersonForm($this->document, UploadedFile::fake()->createWithContent(
        'statement.html',
        '<html><body><script>alert(document.cookie)</script></body></html>'
    ));

    $response->assertSessionHasErrors($this->document->code);

    expect(storedDocumentPath($this->document))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

/**
 * The form rule is not the only way in.
 *
 * A lead's file attribute is validated by `LeadForm`, which carries no rule for `file` or `image`
 * at all, and imports write attribute values without a form request in front of them. The check is
 * therefore repeated in the repository, at the point the file is written.
 */
it('deletes an executable upload that reaches the repository without a form rule', function () {
    $entityId = random_int(1_000_000, 9_999_999);

    app(AttributeValueRepository::class)->save([
        'entity_type' => 'persons',
        'entity_id' => $entityId,
        $this->document->code => UploadedFile::fake()->create('invoice.html', 8),
    ], [$this->document]);

    expect(storedDocumentPath($this->document))->toBeNull()
        ->and(Storage::disk('public')->allFiles('persons/'.$entityId))->toBe([]);
});

it('keeps a permitted upload that reaches the repository without a form rule', function () {
    $entityId = random_int(1_000_000, 9_999_999);

    app(AttributeValueRepository::class)->save([
        'entity_type' => 'persons',
        'entity_id' => $entityId,
        $this->document->code => UploadedFile::fake()->create('invoice.pdf', 8),
    ], [$this->document]);

    $path = storedDocumentPath($this->document);

    expect($path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});
