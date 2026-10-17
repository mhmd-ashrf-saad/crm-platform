<?php

namespace App\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Sijilna\Brand\Brand;

/**
 * Applies the Sijilna brand to user-facing surfaces without editing a single
 * core Krayin file under packages/Webkul.
 *
 * The brand resources live in packages/Sijilna/Brand; only this thin provider
 * sits in app/Providers so that no composer manifest change (and therefore no
 * lock churn, no network) is needed. See packages/Sijilna/Brand/README.md.
 *
 * It is registered last in bootstrap/providers.php, so its boot() runs after
 * every Webkul provider and its route/view/config overrides win.
 */
class BrandingServiceProvider extends ServiceProvider
{
    /**
     * Cache key the vendor's ImageCacheController warms with the Krayin logo.
     */
    protected const VENDOR_LOGO_CACHE_KEY = 'krayin-logo';

    /**
     * Bootstrap the branding overrides.
     */
    public function boot(): void
    {
        $brandPath = $this->brandPath();

        $this->loadBrandConstants();
        $this->disableVendorInstallTelemetry();
        $this->publishBrandRasterAssets($brandPath);
        $this->applyBrandConfigDefaults();
        $this->overrideFooterCredit();
        $this->overrideHelpSupportConfig();
        $this->publishBrandStrings();
        $this->prependBrandViewNamespaces($brandPath);
        $this->registerBrandAssetRoute($brandPath);
        $this->replaceVendorLogoRoute($brandPath);
    }

    /**
     * Remove the vendor's post-install telemetry listener.
     *
     * `packages/Webkul/Installer/src/Providers/InstallerServiceProvider.php:38`
     * registers the event's only listener:
     *
     *     Event::listen('krayin.installed', 'Webkul\Installer\Listeners\Installer@installed');
     *
     * and that listener (`packages/Webkul/Installer/src/Listeners/Installer.php:29-52`)
     * POSTs the site domain, the installing administrator's email and name, and a
     * country code to https://updates.krayincrm.com/api/updates on every
     * successful install. The operator decision is that no such ping is sent at
     * all, and no core file may be edited to achieve it, so the listener is
     * forgotten here instead.
     *
     * ORDERING, NOT LUCK: this provider is appended last in
     * `bootstrap/providers.php`, so `boot()` runs after every Webkul provider
     * and InstallerServiceProvider has already registered the listener. Deleting
     * this call re-enables the vendor data collection — do not remove it without
     * a replacement; `tests/Feature/VendorTelemetryTest.php` is the durable
     * guard that fails if an upstream `2.2` merge restores the listener.
     *
     * `Event::forget()` drops the listener bound to this exact event name. The
     * unrelated wildcard listener in
     * `packages/Webkul/Automation/src/Providers/WorkflowServiceProvider.php:20`
     * is deliberately left alone: it listens to every event, and it ignores
     * `krayin.installed` because that name is not a `workflows.trigger_entities`
     * event.
     */
    protected function disableVendorInstallTelemetry(): void
    {
        Event::forget('krayin.installed');
    }

    /**
     * Register the brand package's artisan command.
     */
    public function register(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $command = $this->brandPath().'/src/Console/Commands/BrandCommand.php';

        if (! is_file($command)) {
            return;
        }

        /**
         * The brand package is not a composer package (see README.md), so the
         * file is loaded explicitly before the container resolves the class.
         */
        $this->loadBrandConstants();

        require_once $command;

        $class = 'Sijilna\Brand\Console\Commands\BrandCommand';

        if (class_exists($class)) {
            $this->commands([$class]);
        }
    }

    /**
     * Absolute path to the brand package, verified to exist.
     */
    protected function brandPath(): string
    {
        $path = dirname(__DIR__, 2).'/packages/Sijilna/Brand';

        if (! is_dir($path)) {
            Log::warning('Sijilna branding: brand package directory is missing.', ['path' => $path]);
        }

        return $path;
    }

    /**
     * Load the brand package's constants class.
     *
     * The package has no composer PSR-4 entry — see README.md for why — so the
     * file is required explicitly at boot for web and console alike.
     */
    protected function loadBrandConstants(): void
    {
        $file = $this->brandPath().'/src/Brand.php';

        if (is_file($file)) {
            require_once $file;
        }
    }

    /**
     * Change the rendered footer credit default.
     *
     * SystemConfig::getDefaultConfig() drops the leading segment of the field
     * code and consults that config key, so the field
     * `general.settings.footer.label` falls back to `settings.footer.label`.
     * That key is unset upstream, which is why the vendor default in
     * Admin/Config/core_config.php renders. Setting it here changes the
     * rendered footer with no database row and no core edit.
     *
     * A `general.settings.footer.label` core_config row, when present, still
     * takes precedence — `php artisan sijilna:brand` seeds it.
     */
    protected function overrideFooterCredit(): void
    {
        $credit = class_exists(Brand::class) ? Brand::FOOTER_CREDIT : 'Powered by Sijilna.';

        config([
            'settings.footer.label' => $credit,
        ]);
    }

    /**
     * Publish the brand's raster marks to the `public` disk.
     *
     * `DashboardController::getLogoDataUri()` reads the configured
     * `general.general.admin_logo.logo_image`, base64-encodes the file and hands
     * dompdf a data URI, so the generated dashboard report only carries the
     * brand when that path exists on the `public` disk. Publishing is the last
     * step the PDF needed: without it the report rendered with no logo and no
     * product name anywhere in the blade.
     *
     * IDEMPOTENT BY CONTENT, not by existence: the stored bytes are compared,
     * so a version bump of the PNG replaces it on the next boot and an unchanged
     * file is never rewritten. The whole publish is wrapped because a read-only
     * or absent storage directory must degrade to the pre-existing behaviour
     * (the PDF renders no logo, and the configured path simply 404s) rather than
     * break every page load with a filesystem exception.
     */
    protected function publishBrandRasterAssets(string $brandPath): void
    {
        if (! class_exists(Brand::class)) {
            return;
        }

        $assets = [
            Brand::LOGO_SOURCE => Brand::PUBLISHED_LOGO_PATH,
            Brand::FAVICON_SOURCE => Brand::PUBLISHED_FAVICON_PATH,
        ];

        try {
            $disk = Storage::disk('public');
        } catch (\Throwable $e) {
            Log::warning('Sijilna branding: the public storage disk is unavailable, so no brand raster was published.', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($assets as $source => $target) {
            $from = $brandPath.'/resources/assets/'.$source;

            if (! is_file($from)) {
                continue;
            }

            try {
                $contents = (string) file_get_contents($from);

                if ($disk->exists($target) && $disk->get($target) === $contents) {
                    continue;
                }

                $disk->put($target, $contents);
            } catch (\Throwable $e) {
                Log::warning('Sijilna branding: could not publish a brand raster to the public disk.', [
                    'source' => $source,
                    'target' => $target,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Set the brand values for config-backed surfaces that have none upstream.
     *
     * `SystemConfig::getConfigData()` looks for a `core_config` row first and
     * then falls through to `getDefaultConfig()`, which DROPS THE FIRST SEGMENT
     * of the field code and consults that config key:
     *
     *     general.general.admin_logo.logo_image  ->  general.admin_logo.logo_image
     *     general.settings.menu_color.brand_color ->  settings.menu_color.brand_color
     *
     * so these are the keys that must be set; the full dotted form is a silent
     * no-op. A `core_config` row, when one exists, still wins — the operator's
     * Settings screen and `php artisan sijilna:brand` both write rows.
     *
     *  - `settings.menu_color.brand_color`: unset upstream, so the core field
     *    default `#0E90D9` (Krayin's blue) was what `--brand-color` rendered on
     *    every admin page, including the login screen.
     *  - `general.admin_logo.logo_image`: unset, so the dashboard PDF rendered no
     *    logo at all. It is also read by the header, the mobile sidebar and the
     *    session blades, which then render this published raster instead of the
     *    dark-mode-aware SVG lockups.
     *  - `general.admin_logo.favicon_image`: unset, so the admin and web-form
     *    layouts fell back to the package's SVG while the Settings field that
     *    writes this key accepted only raster formats.
     */
    protected function applyBrandConfigDefaults(): void
    {
        $color = class_exists(Brand::class) ? Brand::PRIMARY_COLOR : '#0B3C5D';

        config([
            'settings.menu_color.brand_color' => $color,
            'general.admin_logo.logo_image' => class_exists(Brand::class) ? Brand::PUBLISHED_LOGO_PATH : 'sijilna/sijilna-wordmark-1024.png',
            'general.admin_logo.favicon_image' => class_exists(Brand::class) ? Brand::PUBLISHED_FAVICON_PATH : 'sijilna/sijilna-favicon-48.png',
        ]);
    }

    /**
     * Replace the Help & Resources link set.
     *
     * The page reads `config('support')` through HelpController, and its card
     * labels and URLs are all rendered, so this config key is the whole link
     * surface. Operator decision 2 deletes the support, documentation,
     * extensions, API-docs, forum and video cards rather than re-pointing them
     * at URLs that were never chosen, so only the self-hosted deployment card
     * survives and it falls back to the approved root domain.
     *
     * The installer ships no such config file, so this is the only surface.
     */
    protected function overrideHelpSupportConfig(): void
    {
        config([
            'support' => [
                'services' => [
                    [
                        'icon' => 'cloud',
                        'title' => 'admin::app.help.index.services.cloud-hosting.title',
                        'description' => 'admin::app.help.index.services.cloud-hosting.description',
                        'url' => 'https://'.Brand::DOMAIN.'/',
                        'url_label' => Brand::DOMAIN,
                    ],
                ],
                'resources' => [],
                'community' => [],
                'contact_url' => 'https://'.Brand::DOMAIN.'/',
            ],
        ]);
    }

    /**
     * Expose the approved tagline and product strings to the two surfaces that
     * are allowed to carry the tagline: the login screen and the mail chrome
     * (operator decision 11).
     *
     * Arabic is wired as approved rather than machine-translated (decision 8).
     */
    protected function publishBrandStrings(): void
    {
        config([
            'sijilna.name' => class_exists(Brand::class) ? Brand::NAME : 'Sijilna',
            'sijilna.domain' => class_exists(Brand::class) ? Brand::DOMAIN : 'sijilna.com',

            /*
             * The approved identity colour, so the fork's own blades carry a
             * brand fallback instead of the vendor's `#0E90D9`.
             */
            'sijilna.brand_color' => class_exists(Brand::class) ? Brand::PRIMARY_COLOR : '#0B3C5D',
            'sijilna.tagline.en' => 'Every deal, on the record.',
            'sijilna.tagline.ar' => 'كل صفقة لها سجل.',

            /*
             * Installer caption under the wizard logo. The English wording is
             * the operator-approved "a project by Sijilna" (decision 3); the
             * Arabic value is the approved installer string already reviewed
             * for that slot (copy-deck.md §3.5), not a new translation.
             */
            'sijilna.installer.caption_en' => 'a project by Sijilna',
            'sijilna.installer.caption_ar' => 'نظام إدارة عملاء تستضيفه بنفسك',
        ]);
    }

    /**
     * Put the brand package's views first for every namespace the app uses.
     *
     * resources/views/vendor/<hint> cannot be used: that directory is ignored
     * by git, so an override placed there can never be committed. Prepend the
     * package directories instead; anything not shipped here falls through to
     * the core view untouched.
     *
     * The namespace set comes from `Brand::OVERRIDDEN_NAMESPACES`, which
     * `tests/Feature/RebrandRegressionTest.php` reads for its scan map. A
     * hardcoded list here once omitted `web_form`, and the public web form kept
     * rendering vendor artwork that no guard could see; keep this list and the
     * test reading one constant.
     */
    protected function prependBrandViewNamespaces(string $brandPath): void
    {
        $views = $brandPath.'/resources/views';

        if (! is_dir($views)) {
            return;
        }

        if (! class_exists(Brand::class)) {
            Log::error('Sijilna branding: brand constants are missing, so no view namespace was overridden.');

            return;
        }

        foreach (array_keys(Brand::OVERRIDDEN_NAMESPACES) as $namespace) {
            $path = $views.'/'.$namespace;

            if (is_dir($path)) {
                $this->app->make('view')->prependNamespace($namespace, $path);
            }
        }
    }

    /**
     * The `x-admin::*` anonymous components are overridden through the `admin`
     * VIEW NAMESPACE, not through a hash hint.
     *
     * MEASURED, and it is not what the lane brief assumed: this Laravel
     * version's ComponentTagCompiler resolves `<x-admin::layouts.header />`
     * through `admin::components.layouts.header.index`. The resolution order is
     * findClassByComponent() -> guessClassName() ->
     * guessAnonymousComponentUsingNamespaces() -> guessAnonymousComponentUsingPaths()
     * (vendor/laravel/framework/src/Illuminate/View/Compilers/ComponentTagCompiler.php),
     * and the namespaces step runs first with the default `components.` prefix
     * prepended, so `admin::layouts.header` becomes
     * `admin::components.layouts.header` and then `.index` (guessViewName()).
     * The compiled layout proves it:
     * `AnonymousComponent::resolve(['view' => 'admin::components.layouts.header.index'])`.
     *
     * Registering a file under the opaque hint that
     * Blade::anonymousComponentPath($dir, 'admin') creates therefore has NO
     * effect on a rendered page: guessAnonymousComponentUsingPaths() is never
     * reached for these tags. The working mechanism is an ordinary namespace
     * override — this package ships
     * resources/views/admin/components/layouts/header/index.blade.php and
     * prependBrandViewNamespaces() puts that directory first, so the `admin`
     * namespace finds the copy and everything unshipped falls through to core.
     */

    /**
     * Serve the approved brand marks from this package.
     *
     * Views need a URL, not a path, and the built bundles
     * (public/admin/build/**, public/installer/build/**) are build output that
     * `npm run build` overwrites, so the vendor artwork cannot be replaced by
     * editing them. This route serves the six approved SVGs out of the
     * package, which is also why no raster file has to be published for the
     * in-page marks.
     *
     * The allowlist is explicit, not a pattern: it is the list of marks the fork
     * has actually approved, and an `*.svg` glob would serve anything dropped
     * into the directory. It is read from `Brand::SERVED_ASSETS` so the route and
     * any test asserting the served set cannot drift.
     */
    protected function registerBrandAssetRoute(string $brandPath): void
    {
        $assets = $brandPath.'/resources/assets';

        $allowed = class_exists(Brand::class) ? Brand::SERVED_ASSETS : [];

        $this->app->make('router')->get('sijilna/asset/{file}', function (string $file) use ($assets, $allowed) {
            abort_unless(in_array($file, $allowed, true), 404);

            $path = $assets.'/'.$file;

            abort_unless(is_file($path), 404);

            return response()->file($path, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'max-age=10080, public',
            ]);
        })->where('file', '[A-Za-z0-9_\-\.]+')->name('sijilna.asset');
    }

    /**
     * Replace the vendor logo route.
     *
     * InstallerServiceProvider registers GET cache/{filename} as `image_cache`
     * pointing at ImageCacheController, which ignores the filename and fetches
     * https://updates.krayincrm.com/krayin.png over the network. The admin
     * layout preloads url('cache/logo/bagisto.png'), which matches that route.
     *
     * This provider boots last, and RouteCollection::addToCollections indexes
     * routes by method+uri, so registering the same URI and the same route name
     * replaces the vendor handler. The response is served from this package and
     * never touches the network; the vendor controller is not referenced.
     */
    protected function replaceVendorLogoRoute(string $brandPath): void
    {
        try {
            if (Cache::has(self::VENDOR_LOGO_CACHE_KEY)) {
                Cache::forget(self::VENDOR_LOGO_CACHE_KEY);
            }
        } catch (\Throwable $e) {
            Log::warning('Sijilna branding: could not drop the cached vendor logo.', ['error' => $e->getMessage()]);
        }

        $logo = $brandPath.'/resources/assets/sijilna-mark.svg';

        $this->app->make('router')->get('cache/{filename}', [
            'as' => 'image_cache',
            'uses' => function () use ($logo) {
                if (! is_file($logo)) {
                    abort(404);
                }

                return response()->file($logo, [
                    'Content-Type' => 'image/svg+xml',
                    'Cache-Control' => 'max-age=10080, public',
                ]);
            },
        ])->where(['filename' => '[ \w\\.\\/\\-\\@\(\)\=]+']);
    }
}
