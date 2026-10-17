<?php

namespace Sijilna\Brand;

/**
 * The locked Sijilna brand constants.
 *
 * Kept in the brand package rather than in a config file so that the provider
 * under app/Providers and the package's own artisan command read one source.
 * Nothing here names a vendor.
 */
final class Brand
{
    /**
     * Product name.
     */
    public const NAME = 'Sijilna';

    /**
     * Approved primary domain.
     */
    public const DOMAIN = 'sijilna.com';

    /**
     * Register blue, the approved identity colour (brand/palette.md).
     *
     * This is the value the admin's `--brand-color` custom property must carry.
     * Upstream's `general.settings.menu_color.brand_color` field defaults to
     * `#0E90D9` (Krayin's blue), and `SystemConfig::getDefaultConfig()` can only
     * reach a config key when one is set — so `#0E90D9` was what every branded
     * surface rendered until this constant existed.
     */
    public const PRIMARY_COLOR = '#0B3C5D';

    /**
     * The raster brand mark, and where it is published on the `public` disk.
     *
     * A raster, not the SVG: the generated dashboard PDF is rendered by dompdf
     * and base64-encoded by
     * `Webkul\Admin\Http\Controllers\DashboardController::getLogoDataUri()`,
     * and the Settings field that also writes this key validates
     * `mimes:bmp,jpeg,jpg,png,webp` — so the SVG lockups served by
     * `route('sijilna.asset')` are deliberately NOT what is published here.
     *
     * @see resources/assets/sijilna-wordmark-1024.png (rendered from
     *      brand/sijilna-wordmark.svg on the `#FAF7F1` paper surface)
     */
    public const LOGO_SOURCE = 'sijilna-wordmark-1024.png';

    /**
     * The published path of {@see self::LOGO_SOURCE} on the `public` disk.
     * `getLogoDataUri()` requires `Storage::disk('public')->exists($path)`.
     */
    public const PUBLISHED_LOGO_PATH = 'sijilna/sijilna-wordmark-1024.png';

    /**
     * The 16 px favicon cut, as a raster.
     */
    public const FAVICON_SOURCE = 'sijilna-favicon-48.png';

    /**
     * The published path of {@see self::FAVICON_SOURCE} on the `public` disk.
     */
    public const PUBLISHED_FAVICON_PATH = 'sijilna/sijilna-favicon-48.png';

    /**
     * The reversed wordmarks the asset route must also admit.
     *
     * The four light-mode marks below are paired with a `-reversed` cut of each
     * wordmark, and three overrides request them: the admin header logo, the
     * mobile sidebar logo and the error page logo (all in dark mode). They
     * shipped in `resources/assets/` but were missing from the route allowlist,
     * so those three surfaces rendered a 404 image in dark mode — on the exact
     * pages the rebrand exists to fix. One list, four light + two reversed.
     *
     * @var list<string>
     */
    public const SERVED_ASSETS = [
        'sijilna-mark.svg',
        'sijilna-mark-favicon.svg',
        'sijilna-wordmark.svg',
        'sijilna-wordmark-ar.svg',
        'sijilna-wordmark-reversed.svg',
        'sijilna-wordmark-ar-reversed.svg',
    ];

    /**
     * The view namespaces this brand package overrides, keyed by namespace with
     * the core view directory that namespace would otherwise resolve to.
     *
     * THIS IS THE ONE SOURCE OF TRUTH for the override set.
     * `BrandingServiceProvider::prependBrandViewNamespaces()` iterates these
     * keys to prepend the brand views directory, and
     * `tests/Feature/RebrandRegressionTest.php` builds its override-first scan
     * map from the same keys. It was a hardcoded `['admin', 'installer']` list on
     * both sides once, they drifted, and the public WebForm page kept serving
     * the vendor logo and favicon while the guard stayed green — so do not
     * reintroduce a second list anywhere.
     *
     * @var array<string, string>
     */
    public const OVERRIDDEN_NAMESPACES = [
        'admin' => 'packages/Webkul/Admin/src/Resources/views',
        'installer' => 'packages/Webkul/Installer/src/Resources/views',
        'web_form' => 'packages/Webkul/WebForm/src/Resources/views',
    ];

    /**
     * The footer credit, exactly as specified by brand/copy-deck.md §2.4.
     *
     * Register blue rgb(11, 60, 93) is #0B3C5D from the approved palette; the
     * value is stored as rich text (the field is a TinyMCE `editor`) and passes
     * through core()->sanitizeHtml() on the way into core_config.
     */
    public const FOOTER_CREDIT = 'Powered by <span style="color: rgb(11, 60, 93);"><a href="https://sijilna.com" target="_blank">Sijilna</a></span>.';
}
