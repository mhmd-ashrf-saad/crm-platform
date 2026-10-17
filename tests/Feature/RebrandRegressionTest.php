<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Sijilna\Brand\Brand;
use Webkul\Activity\Models\Activity as ActivityModel;
use Webkul\Automation\Helpers\Entity\Activity as ActivityHelper;
use Webkul\User\Models\User;
use Webkul\WebForm\Models\WebForm;

/**
 * Rebrand regression guard for crm-08w.
 *
 * The product is Sijilna; upstream Krayin/Webkul marks are allowed to remain
 * only in internal identifiers (PHP namespaces, `packages/Webkul/**` paths,
 * composer package names) and in the licence/attribution files. This test makes
 * that acceptance criterion permanent, in two halves:
 *
 *  1. rendered-output assertions — the admin login page, the PUBLIC web form a
 *     respondent opens, and the iCal invitation a workflow mails out. These
 *     assert what a visitor actually receives, independent of how the rebrand
 *     is implemented; and
 *  2. a source scan over an explicit list of user-facing surfaces.
 *
 * Why the explicit list, and why this test matters here: the vendor-branded
 * translation values live in core Krayin files
 * (`packages/Webkul/{Admin,Installer}/src/Resources/lang/**`). Laravel's
 * FileLoader can only *replace* a value through an override file —
 * `array_replace_recursive($coreLines, $override)` can never remove a key — so
 * those values were edited in place. A future merge of upstream's `2.2` branch
 * restores the vendor keys, and this test is the guard that turns that merge
 * into a visible failure instead of a silent regression.
 *
 * The scan resolves the EFFECTIVE surface, not the file on disk. Two mechanisms
 * do that, and they must stay in step with how the app actually renders:
 *
 *  - Views: `BrandingServiceProvider::prependBrandViewNamespaces()` puts
 *    `packages/Sijilna/Brand/resources/views/<namespace>` first for every
 *    namespace in `Brand::OVERRIDDEN_NAMESPACES`, so a file shipped there
 *    shadows the core view. The scan therefore reads the override when it
 *    exists and FALLS THROUGH to the core file when it does not — deleting an
 *    override puts the core view back in front of the user, and the scan must
 *    fail on it.
 *  - The Help & Resources surface: `BrandingServiceProvider::overrideHelpSupportConfig()`
 *    replaces the whole `support` config key at boot, so the admin Help page
 *    renders `config('support')` and never the core config file. The scan walks
 *    that config key's values instead of reading the file (see
 *    REBRAND_SUPPORT_CONFIG_ENTRY below).
 *
 * One namespace list, not two. The provider prepended a hardcoded
 * `['admin', 'installer']` while its own loop elsewhere read a three-entry list,
 * the two drifted, and the public WebForm page kept serving the vendor logo and
 * favicon while this guard stayed green. Both sides now read
 * `Brand::OVERRIDDEN_NAMESPACES`, and the test asserts the provider really
 * registered every namespace it declares.
 *
 * Vendor marks come in two shapes, and a name-only scan sees only the first:
 *
 *  - a vendor NAME (`krayin`, `webkul`) in a string a user reads; and
 *  - a built-asset reference that resolves to vendor ARTWORK — the WebForm
 *    blades named no vendor at all, they fell back to
 *    `vite()->asset('images/logo.svg')` and `('images/favicon.ico')`, which are
 *    the Krayin wordmark and "K" icon inside `public/admin/build/assets/**`.
 *    `rebrandVendorAssetPatterns()` is what makes the fallback visible.
 *
 * Deliberate internal exceptions (documented, not accidental):
 *  - translation KEY NAMES such as `'krayin-info' =>` are internal identifiers
 *    referenced by `@lang('installer::app.installer.index.…')`; only the
 *    translated VALUES are user-facing. `installation-processing.krayin-info`
 *    is kept on purpose (brand/copy-deck.md §9.8) because its value carries no
 *    vendor mark.
 *  - `Webkul\…` namespaces, `packages/Webkul/…` paths, `webkul/<package>`
 *    composer names, `admin::`/`installer::`/`web_form::` key references, the
 *    `images/krayin-logo.svg` asset filename and `krayin-crm:*` command
 *    signatures are out of scope by
 *    `.agents/context/guides/rebrand-boundary.md`. The filename may stay in the
 *    repository; rendering it is still a leak, which is why
 *    `rebrandVendorAssetPatterns()` checks the raw line, not the stripped one.
 */
uses(DatabaseTransactions::class);

/**
 * The one entry in the surface list that is a config surface rather than a view.
 *
 * It is never read from disk. The Admin package's `mergeConfigFrom()` folds
 * this file into the `support` config key, and
 * `BrandingServiceProvider::overrideHelpSupportConfig()` then replaces that key
 * at boot, so `config('support')` is what the Help page renders. Scanning the
 * live config keeps the guard honest in both directions: the vendor strings
 * fail if the override is removed (the core file flows straight back into
 * config) and a vendor string reintroduced through the override fails too.
 */
const REBRAND_SUPPORT_CONFIG_ENTRY = 'packages/Webkul/Admin/src/Config/support.php';

/**
 * The core iCal helper, whose `PRODID` is the calendar name a recipient's
 * client displays.
 */
const REBRAND_ICS_PRODID_ENTRY = 'packages/Webkul/Automation/src/Helpers/Entity/Activity.php';

/**
 * The namespace => core-view-directory map the brand package declares, read
 * from its one source of truth.
 *
 * `Brand` has no composer PSR-4 entry (see packages/Sijilna/Brand/README.md),
 * so it is loaded the same way `BrandingServiceProvider::loadBrandConstants()`
 * loads it. The app boots the provider before any test body runs, but requiring
 * the file here keeps the helper correct when it is evaluated on its own.
 *
 * @return array<string, string>
 */
function rebrandOverriddenNamespaces(): array
{
    if (! class_exists(Brand::class)) {
        require_once base_path('packages/Sijilna/Brand/src/Brand.php');
    }

    return Brand::OVERRIDDEN_NAMESPACES;
}

/**
 * Core view path prefix => the fork's override directory for it, DERIVED from
 * `Brand::OVERRIDDEN_NAMESPACES` so it cannot drift from the provider.
 *
 * @return array<string, string>
 */
function rebrandViewOverrideMap(): array
{
    $map = [];

    foreach (rebrandOverriddenNamespaces() as $namespace => $coreViews) {
        $map[rtrim($coreViews, '/').'/'] = 'packages/Sijilna/Brand/resources/views/'.$namespace.'/';
    }

    return $map;
}

/**
 * Vendor marks that must not reach a user-facing surface, lowercased.
 *
 * @return list<string>
 */
function rebrandVendorTokens(): array
{
    return ['krayin', 'webkul'];
}

/**
 * Built-asset references that resolve to vendor artwork, as regexes.
 *
 * This is the half of the scan a name search cannot do, and the reason the
 * WebForm leak survived review: neither core blade contained the vendor's name,
 * they only fell back to files inside the built bundles. Those files are the
 * vendor's marks —
 * `public/admin/build/assets/logo-Bjh7YAuF.svg` is the Krayin wordmark and
 * `assets/favicon-BtbgZBji.ico` is the Krayin "K" — so serving one from a
 * user-facing blade is the same leak as printing the name.
 *
 * The bundles are npm output and cannot be replaced by editing them, so the
 * only correct reference on a surface is `route('sijilna.asset', …)` or a
 * config-driven `Storage::url()`. `images/error.svg`,
 * `images/empty-placeholders/**` and `images/spinner.svg` are deliberately NOT
 * listed: they are generic UI illustrations and carry no vendor mark.
 *
 * @return list<string>
 */
function rebrandVendorAssetPatterns(): array
{
    return [
        // The admin build's wordmark and its dark / mobile variants.
        '/vite\(\)->asset\(\'images\/(?:dark-|mobile-dark-|mobile-light-)?logo\.svg\'/i',
        // The admin and installer builds' favicon, which is the "K" icon.
        '/vite\(\)->asset\(\'images\/favicon\.ico\'/i',
        // The installer build's vendor-named wordmark.
        '/vite\(\)->asset\(\'images\/krayin-logo\.svg\'/i',

        /*
         * A built-bundle path in ANY spelling — `url('admin/build/assets/logo-Bjh7YAuF.svg')`,
         * a CSS `url(/admin/build/assets/favicon-BtbgZBji.ico)`, or a bare
         * path. This is the shape a LOST OVERRIDE leaves behind, and it is the
         * one the three `vite()->asset()` patterns above cannot see: the files
         * are npm output whose hashed names carry no vendor word at all, so an
         * admin blade that falls back to the core view renders the Krayin
         * wordmark while a name-only scan stays green. Anchored on
         * `build/assets` so the fork's own `sijilna/asset/<file>.svg` URLs and
         * its `cache/logo/sijilna.png` preload can never match.
         */
        '/build\/assets\/[^\s\'"\)]*?(?:logo|favicon)[^\s\'"\)]*/i',

        /*
         * The same vendor artwork reached through an asset helper or the Vite
         * facade instead of `vite()->asset()`: `asset('images/logo.svg')`,
         * `Vite::asset("images/favicon.ico")`,
         * `@vite(['assets/images/logo.svg'])`. The `images/` bundle directory is
         * required, so the fork's `Storage::url($logo)` references and its
         * `url('cache/logo/sijilna.png')` preload stay clean; and the segment
         * before the mark name has to be a real path segment, so a prose
         * mention of `images/*logo.svg` cannot be mistaken for a reference.
         */
        '/(?:asset\(|Vite::asset\(|@vite\(\[?|url\()\s*[\'"][^\'"]*images\/(?:[-a-z0-9]+\/)*(?:dark-|mobile-dark-|mobile-light-)?(?:logo|favicon)[-a-z0-9]*\.(?:svg|ico)/i',
    ];
}

/**
 * Strip internal identifiers before a source line is scanned, so that a line
 * carrying both an internal token and a genuine vendor mark still fails.
 *
 * The iCal `PRODID` line is deliberately NOT covered here. `Krayincrm` contains
 * no `krayin-crm:` signature, no namespace, no path and no package name, so the
 * vendor token survives this strip and the scan fails on it — which is what
 * makes `REBRAND_ICS_PRODID_ENTRY` worth listing.
 */
function rebrandStripInternalTokens(string $line): string
{
    $internal = [
        '/Webkul\\\\[A-Za-z0-9_\\\\]*/',                        // PHP namespace
        '/packages\/Webkul\/[A-Za-z0-9_\.\-\/]*/',              // package path
        '/\bwebkul\/[a-z0-9\-]+/i',                             // composer package name
        '/(?:admin|installer|web_form)::[A-Za-z0-9_\.\-]*/',    // @lang() key reference
        '/images\/krayin-logo\.svg/',                           // internal asset filename
        '/krayin-crm:[a-z\-]+/',                                // artisan command signature
    ];

    return preg_replace($internal, '', $line) ?? $line;
}

/**
 * The explicit user-facing surface list. Never a repository-wide glob: internal
 * namespaces and licence files legitimately contain the vendor name.
 *
 * Every path here is a CORE path; `rebrandEffectiveSurfaceFile()` below resolves
 * it to the fork's override when one exists. Keeping the core paths in the list
 * (rather than listing the overrides) is what makes the fallback work: delete an
 * override and the core file becomes the scanned surface.
 *
 * @return list<string>
 */
function rebrandSurfaceFiles(): array
{
    $files = [
        // Admin session blades — the login / forgot-password / reset-password footer credit.
        'packages/Webkul/Admin/src/Resources/views/sessions/login.blade.php',
        'packages/Webkul/Admin/src/Resources/views/sessions/forgot-password.blade.php',
        'packages/Webkul/Admin/src/Resources/views/sessions/reset-password.blade.php',

        // Help & Resources link surface — resolved through `config('support')`.
        REBRAND_SUPPORT_CONFIG_ENTRY,

        // Transactional mail chrome.
        'packages/Webkul/Admin/src/Resources/views/emails/layout.blade.php',

        // Installer wizard.
        'packages/Webkul/Installer/src/Resources/views/installer/index.blade.php',

        // The public WebForm page a respondent opens, and the markup the embed
        // script hands to third-party sites: the layout's favicon and the logo
        // inside the compiled template. Their path prefix is `web_form`, the
        // namespace that was missing from the override list.
        'packages/Webkul/WebForm/src/Resources/views/components/layouts/index.blade.php',
        'packages/Webkul/WebForm/src/Resources/views/settings/web-forms/preview.blade.php',

        // The admin layout family. `<x-admin::layouts.anonymous>` is what the
        // admin login renders, and the header, the mobile sidebar and the error
        // page each name their own logo — so a lost or renamed override here
        // puts the core blade, and its built vendor artwork, back in front of
        // the user while the rendered assertion above stays green: a hashed
        // bundle name such as `favicon-BtbgZBji.ico` contains neither `krayin`
        // nor `webkul`. That is exactly what the asset pass below is for, and
        // exactly why these five have to be listed.
        'packages/Webkul/Admin/src/Resources/views/components/layouts/index.blade.php',
        'packages/Webkul/Admin/src/Resources/views/components/layouts/anonymous.blade.php',
        'packages/Webkul/Admin/src/Resources/views/components/layouts/header/index.blade.php',
        'packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/mobile/index.blade.php',
        'packages/Webkul/Admin/src/Resources/views/errors/index.blade.php',

        // iCal invitation — `PRODID` is the calendar's name in the client.
        REBRAND_ICS_PRODID_ENTRY,
    ];

    foreach (['Admin', 'Installer'] as $module) {
        foreach (['en', 'ar', 'es', 'fa', 'ja', 'ko', 'pt_BR', 'tr', 'vi', 'zh_CN'] as $locale) {
            $files[] = "packages/Webkul/{$module}/src/Resources/lang/{$locale}/app.php";
        }
    }

    return $files;
}

/**
 * Resolve a config-backed brand image slot to a path that is really usable, and
 * fail on the two states that are silently broken rather than absent.
 *
 * This is the shared half of the brand-mark assertions: the rendered-surface
 * helper below and the crm-08w guard for the dashboard PDF both need exactly
 * this, so it lives once here rather than being re-spelled at each call site.
 *
 * The two failures it pins are the ones a name-based scan cannot see:
 *
 *  - **unset or empty** — `SystemConfig::getConfigData()` falls through to the
 *    core field default, and for `logo_image` there is none, so
 *    `DashboardController::getLogoDataUri()` returns null and the PDF header's
 *    `@if ($logo) <img> @endif` emits nothing at all; and
 *  - **set but not published** — the value looks configured on every surface
 *    that reads config, but `Storage::disk('public')->exists($path)` is false, so
 *    the page renders a broken image and the PDF base64-encodes nothing.
 *
 * An unset key is a FAILURE here, never a skip: "no logo configured" is the
 * exact regression this guard exists to catch.
 */
function rebrandPublishedBrandMark(string $configField): string
{
    $path = core()->getConfigData($configField);

    expect($path)->not->toBeNull("{$configField} resolves to nothing");

    expect((string) $path)->not->toBeEmpty("{$configField} is set to an empty value");

    expect(Storage::disk('public')->exists($path))->toBeTrue(
        "{$configField} is '{$path}' but nothing is published at that public-disk path, so the surface renders a broken image"
    );

    return (string) $path;
}

/**
 * The brand mark a fork-owned blade renders for a config-backed image slot.
 *
 * The brand package's own blades prefer the configured
 * `general.general.admin_logo.*` path — the published raster the dashboard PDF
 * also embeds — and fall back to `route('sijilna.asset')` when no path is set.
 * Both branches resolve inside the fork, so pinning whichever one is live is as
 * strict as pinning the literal fallback URL used while the key was unset, and
 * it additionally fails when a configured path was never published, which is
 * the state that renders a broken image.
 */
function rebrandRenderedBrandMark(string $configField, string $fallbackAsset): string
{
    if (! core()->getConfigData($configField)) {
        return route('sijilna.asset', ['file' => $fallbackAsset]);
    }

    return Storage::url(rebrandPublishedBrandMark($configField));
}

/**
 * How a fork-owned blade REACHES the theme-toggle logo element.
 *
 * Matches the call forms that actually resolve an element —
 * `document.getElementById('logo-image')`, `querySelector('#logo-image')`,
 * `$('#logo-image')` — and deliberately NOT a bare `#logo-image` mention, so the
 * header blade's own prose about the toggle does not by itself make a file a
 * writer.
 */
function rebrandLogoImageWritePattern(): string
{
    return '/getElementById\(\s*[\'"]logo-image[\'"]\s*\)'
        .'|querySelector(?:All)?\(\s*[\'"]#logo-image[\'"]\s*\)'
        .'|\$\(\s*[\'"]#logo-image[\'"]\s*\)/';
}

/**
 * The config-backed logo branch every admin layout ships.
 *
 * This is the branch that W3's config default sent every logo `<img>` into, and
 * the one that carried no `id` while the `@else` fallbacks did.
 */
function rebrandLogoConfigBranchPattern(): string
{
    $field = 'general.general.admin_logo.logo_image';

    return '/@if\s*\(\s*\$logo\s*=\s*core\(\)->getConfigData\(\''.preg_quote($field, '/').'\'\)\s*\)(.*?)@else/s';
}

/**
 * Every fork-owned blade in the admin layout family that either REACHES the
 * `logo-image` element or RENDERS a config-backed logo image.
 *
 * Discovered from disk rather than listed, so a blade added to the family later
 * is covered without anyone remembering to add it here.
 *
 * Scoped to `admin/components/layouts/**` on purpose. The theme toggle lives in
 * that layout and can only swap an element the layout renders, so that subtree is
 * the whole surface the invariant is about. The session blades and the public
 * web-form preview render the same config-backed `<img>` with no toggle anywhere
 * near them, and requiring `id="logo-image"` there would be a false positive.
 *
 * A blade qualifies for either half deliberately: the mobile sidebar RENDERS the
 * swapped image but contains no lookup itself (the two lookups live in the
 * header's toggle script), so a writer-only rule would leave the very branch this
 * pass had to fix outside the guard.
 *
 * @return list<string>
 */
function rebrandLogoImageToggleFiles(): array
{
    $prefix = 'packages/Sijilna/Brand/resources/views/admin/components/layouts/';
    $files = [];

    foreach (File::allFiles(base_path(rtrim($prefix, '/'))) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        $reaches = preg_match(rebrandLogoImageWritePattern(), $source) === 1;

        $renders = preg_match(rebrandLogoConfigBranchPattern(), $source) === 1;

        if ($reaches || $renders) {
            $files[] = $prefix.str_replace('\\', '/', $file->getRelativePathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * The `#logo-image` invariant, at two depths.
 *
 * THE BUG THIS PINS. The admin header's theme toggle is two
 * `document.getElementById('logo-image').src = …` assignments, and it runs them
 * AFTER `classList.toggle('dark', …)`. Upstream carries `id="logo-image"` on
 * every logo `<img>`, so the lookup always resolved. The fork's config-backed
 * branch shipped WITHOUT the id: once W3's config default sent every header,
 * mobile-sidebar and dropdown logo into that branch, an authenticated dashboard
 * rendered three config-branch images and ZERO elements with `id="logo-image"`.
 * The toggle then flipped the theme and threw an uncaught TypeError on every
 * click — a console error on a fresh install, on the header's only interactive
 * control.
 *
 * FILE LEVEL — a blade that reaches `#logo-image` must render one at all. This is
 * the cheap check the brief asks for; on its own it would NOT have caught the
 * real bug, because the `@else` fallbacks in the same file did carry the id.
 *
 * BRANCH LEVEL — and this is the one with teeth: every `<img>` inside a
 * config-backed logo branch must carry the id, counted rather than merely
 * present, so an image added to that branch later cannot slip through. This is
 * what makes the regression unrepeatable.
 *
 * @return list<string>
 */
function rebrandLogoImageToggleViolations(): array
{
    $violations = [];
    $idAttribute = '/id\s*=\s*[\'"]logo-image[\'"]/';

    foreach (rebrandLogoImageToggleFiles() as $relative) {
        $source = (string) file_get_contents(base_path($relative));

        if (preg_match($idAttribute, $source) !== 1) {
            $violations[] = "{$relative} reaches #logo-image but renders no element with id=\"logo-image\"";
        }

        preg_match_all(rebrandLogoConfigBranchPattern(), $source, $branches);

        foreach ($branches[1] as $index => $branch) {
            $images = substr_count($branch, '<img');
            $carrying = (int) preg_match_all($idAttribute, $branch);

            if ($images === $carrying && $images > 0) {
                continue;
            }

            $violations[] = sprintf(
                '%s: configured-logo branch %d renders %d <img> element(s) but %d carry id="logo-image", so the theme toggle dereferences a missing element',
                $relative,
                $index + 1,
                $images,
                $carrying
            );
        }
    }

    return $violations;
}

/**
 * Resolve a core view path to the file that actually renders.
 *
 * Returns the fork's override when it exists, otherwise the core path so the
 * core file is scanned and a deleted override cannot hide a vendor mark.
 */
function rebrandEffectiveSurfaceFile(string $corePath): string
{
    foreach (rebrandViewOverrideMap() as $corePrefix => $overridePrefix) {
        if (! str_starts_with($corePath, $corePrefix)) {
            continue;
        }

        $override = $overridePrefix.substr($corePath, strlen($corePrefix));

        return is_file(base_path($override)) ? $override : $corePath;
    }

    return $corePath;
}

/**
 * Scan a file line by line for vendor names and built vendor artwork. Pass the
 * EFFECTIVE path — the override when one exists — so the failure message names
 * the file that really renders. Returns one human-readable violation per hit.
 *
 * @return list<string>
 */
function rebrandScanSourceFile(string $relative): array
{
    $path = base_path($relative);

    if (! is_file($path)) {
        /**
         * A listed surface with no file at all is a failure, not a pass. When
         * this returned "no violations", an upstream restructure that moved a
         * scanned blade turned the guard green while the un-overridden core view
         * rendered the vendor mark. `rebrandScanLocaleFile()` already fails this
         * way; this mirrors it. Every listed path exists today, so it cannot
         * become a false positive.
         */
        return ["{$relative} is missing"];
    }

    $violations = [];
    $lines = preg_split('/\R/', (string) file_get_contents($path)) ?: [];

    foreach ($lines as $index => $rawLine) {
        $line = rebrandStripInternalTokens($rawLine);

        foreach (rebrandVendorTokens() as $token) {
            if (stripos($line, $token) !== false) {
                $violations[] = sprintf(
                    '%s:%d contains "%s": %s',
                    $relative,
                    $index + 1,
                    $token,
                    trim($rawLine)
                );
            }
        }

        /**
         * Matched against the RAW line: `rebrandStripInternalTokens()` removes
         * the `images/krayin-logo.svg` filename as an internal identifier, but a
         * blade that RENDERS that file still ships vendor artwork to the user,
         * so this pass must see it.
         */
        foreach (rebrandVendorAssetPatterns() as $pattern) {
            if (preg_match($pattern, $rawLine) === 1) {
                $violations[] = sprintf(
                    '%s:%d serves built vendor artwork: %s',
                    $relative,
                    $index + 1,
                    trim($rawLine)
                );
            }
        }
    }

    return $violations;
}

/**
 * Walk the VALUES of a nested array, reporting any vendor mark. Keys are
 * internal identifiers — a translation key, a config key — and are never
 * scanned; only the strings a user reads are.
 *
 * @param  array<array-key, mixed>  $tree
 * @return list<string>
 */
function rebrandScanValues(array $tree, string $label): array
{
    $violations = [];

    $walk = function ($node, string $prefix) use (&$walk, &$violations, $label): void {
        foreach ($node as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $walk($value, $dotted);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            foreach (rebrandVendorTokens() as $token) {
                if (stripos($value, $token) !== false) {
                    $violations[] = "{$label} value '{$dotted}' contains '{$token}': {$value}";
                }
            }
        }
    };

    $walk($tree, '');

    return $violations;
}

/**
 * Scan only the VALUES of a locale file: the keys are internal identifiers, the
 * translated strings are what a user reads.
 *
 * These core files are the recorded exception to the never-modify-core rule —
 * the rebrand edits them in place because `array_replace_recursive()` can only
 * replace a key, never delete one — so they must keep being scanned as core
 * files, on disk, and values-only.
 *
 * @return list<string>
 */
function rebrandScanLocaleFile(string $relative): array
{
    $path = base_path($relative);

    if (! is_file($path)) {
        return ["{$relative} is missing"];
    }

    return rebrandScanValues(require $path, $relative);
}

/**
 * Scan the live Help & Resources surface, `config('support')`.
 *
 * @return list<string>
 */
function rebrandScanSupportConfig(): array
{
    return rebrandScanValues((array) config('support'), REBRAND_SUPPORT_CONFIG_ENTRY);
}

it('renders the admin login page under the new brand with no vendor mark', function () {
    /**
     * Route: `admin.session.create` — GET /admin/login, registered in
     * packages/Webkul/Admin/src/Routes/Admin/auth-routes.php.
     */
    $response = test()->get(route('admin.session.create'));

    $response->assertOk();

    $body = (string) $response->getContent();

    expect($body)->toContain('Sijilna');

    foreach (rebrandVendorTokens() as $token) {
        $position = stripos($body, $token);

        expect($position)->toBeFalse(
            "the rendered admin login page still contains the vendor mark '{$token}'"
        );
    }
});

it('renders the public web form page and its embed script under the new brand', function () {
    $webForm = WebForm::create([
        'form_id' => bin2hex(random_bytes(8)),
        'title' => 'Request a demo',
        'description' => 'Tell us about your project.',
        'submit_button_label' => 'Send',
        'submit_success_action' => 'message',
        'submit_success_content' => 'Thanks.',
        'create_lead' => 1,
    ]);

    /**
     * Route `admin.settings.web_forms.preview` — GET
     * web-forms/forms/{form_id}/form.html. `WebFormServiceProvider` registers it
     * behind `web` and `admin_locale` only, so this is the page a respondent
     * opens without an account. `web_form::components.layouts.index` supplies
     * the document head, and the logo sits inside the compiled
     * `#v-web-form-template` block.
     */
    $body = (string) test()->get('/web-forms/forms/'.$webForm->form_id.'/form.html')
        ->assertOk()
        ->getContent();

    expect($body)->toContain(
        rebrandRenderedBrandMark('general.general.admin_logo.favicon_image', 'sijilna-mark-favicon.svg')
    );

    /**
     * The lockup is config-driven: set `general.general.admin_logo.logo_image`
     * (the published raster the PDF embeds) and the blade renders that; clear it
     * and the blade renders the package's own SVG. Either way the reference must
     * be brand-owned, so the assertion resolves the form this instance is
     * actually configured for instead of pinning a URL that only exists in the
     * unconfigured case.
     */
    $wordmark = rebrandRenderedBrandMark('general.general.admin_logo.logo_image', 'sijilna-wordmark.svg');

    expect($body)->toContain($wordmark);

    foreach (rebrandVendorTokens() as $token) {
        expect(stripos($body, $token))->toBeFalse(
            "the rendered public web form still contains the vendor mark '{$token}'"
        );
    }

    /**
     * The embed script ships the same view to third-party sites through
     * `document.write`, so it carries the same artwork and is asserted the same
     * way. Route `admin.settings.web_forms.form_js` — GET
     * web-forms/forms/{form_id}/form.js, also unauthenticated.
     */
    $embed = (string) test()->get('/web-forms/forms/'.$webForm->form_id.'/form.js')
        ->assertOk()
        ->getContent();

    expect($embed)->toContain(basename($wordmark));

    foreach (rebrandVendorTokens() as $token) {
        expect(stripos($embed, $token))->toBeFalse(
            "the embed script still contains the vendor mark '{$token}'"
        );
    }
});

it('names Sijilna as the ical calendar product, not the vendor', function () {
    /**
     * Built in memory: `getICSContent()` reads attributes and loaded relations
     * only, so nothing is written and no fixture is needed.
     */
    $activity = new ActivityModel([
        'title' => 'Quarterly review',
        'location' => 'Cairo',
        'comment' => 'Bring the numbers.',
        'schedule_from' => now(),
        'schedule_to' => now()->addHour(),
    ]);

    $activity->created_at = now();

    $activity->setRelation('user', new User([
        'name' => 'Amina Hassan',
        'email' => 'amina@example.test',
    ]));

    $activity->setRelation('participants', collect());

    $ics = app(ActivityHelper::class)->getICSContent($activity);

    /**
     * `PRODID` is the calendar's name in the recipient's client, and this
     * artifact is mailed as an attachment. The approved value is
     * brand/copy-deck.md §2.8; the vendor one was removed from one line of a
     * core file, which is merge debt an upstream `2.2` merge would restore.
     */
    expect($ics)->toContain('BEGIN:VCALENDAR');
    expect($ics)->toContain('SUMMARY:Quarterly review');
    expect($ics)->toContain('PRODID:-//Sijilna//Sijilna//EN');
    expect($ics)->not->toContain('Krayincrm');
});

it('serves every approved brand mark, including the two dark-mode wordmarks', function () {
    /**
     * This is W1's regression guard. The admin header, the mobile sidebar and
     * the error page each request a `-reversed` wordmark when the `dark_mode`
     * cookie is set, and the asset route's allowlist did not admit either file:
     * both 404'd, so all three dark-mode logos were broken images. Naming both
     * reversed files explicitly means a shortened allowlist fails here, and the
     * loop covers every mark the overrides request.
     */
    expect(Brand::SERVED_ASSETS)->toContain('sijilna-wordmark-reversed.svg');
    expect(Brand::SERVED_ASSETS)->toContain('sijilna-wordmark-ar-reversed.svg');

    foreach (Brand::SERVED_ASSETS as $file) {
        $response = test()->get('/sijilna/asset/'.$file);

        $response->assertOk();

        /**
         * The route answers with `response()->file()`, i.e. a
         * BinaryFileResponse, whose body is streamed from disk rather than held
         * in `getContent()`. Assert the served file's own size, so an empty or
         * truncated asset still fails.
         */
        expect($response->baseResponse->getFile()->getSize())
            ->toBeGreaterThan(0, "/sijilna/asset/{$file} served an empty file");
    }

    /**
     * The allowlist is explicit, not a glob: an unapproved file in the same
     * directory is still refused.
     */
    test()->get('/sijilna/asset/sijilna-not-approved.svg')->assertNotFound();
});

it('renders the approved register-blue as the brand colour', function () {
    /**
     * `general.settings.menu_color.brand_color` is unset upstream, so
     * `SystemConfig::getDefaultConfig()` fell through to the core field default
     * `#0E90D9` — Krayin's blue — and every admin page, login included, set
     * `--brand-color` to it. The provider now sets the config key the segment
     * shift actually reads, so the custom property carries the approved palette
     * value.
     */
    $body = (string) test()->get(route('admin.session.create'))->assertOk()->getContent();

    expect($body)->toContain('--brand-color: '.Brand::PRIMARY_COLOR);

    /**
     * And the fork's own blades no longer carry the vendor fallback in source.
     *
     * Scope note, stated rather than hidden: the rendered login page still
     * contains three `#0E90D9` literals, all from
     * `packages/Webkul/Admin/src/Resources/views/components/flash-group/item.blade.php`
     * — the `info` alert severity's container border, icon colour and icon
     * stroke. That is a core blade with a hardcoded colour array and no config
     * surface, so removing it would mean either a core edit or a full view copy
     * of an unrelated component; it is a generic alert tone, not the brand
     * identity colour, and it is deliberately not asserted against here.
     */
    foreach ([
        'packages/Sijilna/Brand/resources/views/admin/components/layouts/index.blade.php',
        'packages/Sijilna/Brand/resources/views/admin/components/layouts/anonymous.blade.php',
    ] as $blade) {
        expect(stripos((string) file_get_contents(base_path($blade)), '#0E90D9'))
            ->toBeFalse("{$blade} still carries the vendor's blue fallback");
    }
});

it('keeps the dashboard PDF logo configured, published and a raster', function () {
    /**
     * The cheap guard for crm-08w's "a generated PDF … carr[ies] the new brand"
     * requirement — deliberately a CONFIG assertion, with no seeded admin and no
     * dompdf call, so it runs in the plain suite.
     *
     * The regression it pins is the one that already happened here: with
     * `general.general.admin_logo.logo_image` unset, `getLogoDataUri()` returns
     * null and the PDF header is `@if ($logo) <img> @endif` with no product name
     * anywhere in the blade, so the report carried no logo at all — for weeks —
     * while every vendor-name grep stayed green. `rebrandPublishedBrandMark()`
     * fails on unset, on empty, and on a path that was never published, which is
     * the same blank page as no path at all.
     */
    $logo = rebrandPublishedBrandMark('general.general.admin_logo.logo_image');

    /**
     * And it must be a RASTER. The Settings field that writes this key validates
     * `mimes:bmp,jpeg,jpg,png,webp`, and dompdf embeds a raster reliably while its
     * SVG path (via php-svg-lib) is the fragile one — so pin the raster choice
     * here, or someone will "simplify" it back to an SVG lockup and the PDF will
     * quietly lose the brand again.
     */
    expect(pathinfo($logo, PATHINFO_EXTENSION))->toBeIn(['png', 'jpg', 'jpeg', 'webp', 'bmp']);

    /**
     * The favicon slot is configured the same way and breaks the same way — but
     * visibly, as a broken image on every admin and public web-form surface.
     */
    rebrandPublishedBrandMark('general.general.admin_logo.favicon_image');
});

it('keeps the logo-image toggle wired to an element that carries the id', function () {
    /**
     * Cheap, source-level, and with no seeded admin: it reads the fork's own
     * blades off disk, so it runs in the plain suite on any clone.
     */
    $violations = rebrandLogoImageToggleViolations();

    $this->assertSame([], $violations, implode(PHP_EOL, $violations));

    /**
     * And the walk really found the toggle blades — a discovery helper that
     * quietly matched nothing would make the assertion above vacuous.
     */
    expect(rebrandLogoImageToggleFiles())->toBe([
        'packages/Sijilna/Brand/resources/views/admin/components/layouts/header/index.blade.php',
        'packages/Sijilna/Brand/resources/views/admin/components/layouts/sidebar/mobile/index.blade.php',
    ]);
});

it('keeps the provider and the scan on one namespace list', function () {
    $brandViews = 'packages/Sijilna/Brand/resources/views';

    /**
     * The scan map covers exactly the namespaces the constant declares, each
     * pointing at that namespace's brand directory. Pinning the expected map
     * here also pins the core directories the constant names, so a wrong core
     * path fails rather than quietly scanning nothing.
     */
    expect(rebrandViewOverrideMap())->toBe([
        'packages/Webkul/Admin/src/Resources/views/' => $brandViews.'/admin/',
        'packages/Webkul/Installer/src/Resources/views/' => $brandViews.'/installer/',
        'packages/Webkul/WebForm/src/Resources/views/' => $brandViews.'/web_form/',
    ]);

    /**
     * And the provider really prepended the brand directory for every namespace
     * the constant declares. THIS is the check with teeth: a hardcoded loop that
     * drops a namespace — the drift that left `web_form` unregistered, so the
     * vendor logo stayed on the public form while the guard stayed green — fails
     * here instead of shipping.
     */
    /**
     * The finder must come off the SAME factory the provider prepended to.
     * `view.finder` is a plain binding, not a singleton, so resolving it from the
     * container a second time yields a fresh, hint-free instance.
     */
    /** @var Factory $factory */
    $factory = app('view');

    /** @var FileViewFinder $finder */
    $finder = $factory->getFinder();

    $hints = $finder->getHints();

    foreach (array_keys(rebrandOverriddenNamespaces()) as $namespace) {
        expect($hints[$namespace][0] ?? null)
            ->toBe(base_path($brandViews.'/'.$namespace), "the '{$namespace}' view namespace is not the brand directory");
    }
});

it('keeps every user-facing surface free of vendor marks', function () {
    $violations = [];

    foreach (rebrandSurfaceFiles() as $file) {
        if ($file === REBRAND_SUPPORT_CONFIG_ENTRY) {
            $violations = array_merge($violations, rebrandScanSupportConfig());

            continue;
        }

        $violations = array_merge(
            $violations,
            str_ends_with($file, '/app.php')
                ? rebrandScanLocaleFile($file)
                : rebrandScanSourceFile(rebrandEffectiveSurfaceFile($file))
        );
    }

    /**
     * The failure message names the file and the token for every hit.
     */
    $this->assertSame([], $violations, implode(PHP_EOL, $violations));
});
