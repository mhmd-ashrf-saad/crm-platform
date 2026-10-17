<?php

/**
 * Privacy guard: the vendor's install telemetry must never come back.
 *
 * What was removed, and why this test exists by name:
 * `packages/Webkul/Installer/src/Providers/InstallerServiceProvider.php:38`
 * registered the event's only listener,
 * `Webkul\Installer\Listeners\Installer@installed`, for `krayin.installed`.
 * That listener (`packages/Webkul/Installer/src/Listeners/Installer.php:29-52`)
 * POSTed the site domain, the installing ADMINISTRATOR'S NAME AND EMAIL, and a
 * country code to `https://updates.krayincrm.com/api/updates` on every
 * successful install — a silent transfer of personal data to the vendor. The
 * operator decided no ping of any kind is sent.
 *
 * The listener is forgotten at boot by
 * `App\Providers\BrandingServiceProvider::disableVendorInstallTelemetry()`, so
 * no core Krayin file had to be edited and an upstream `2.2` merge cannot
 * restore it silently: this test fails, and its name states the reason.
 *
 * Two listeners for `krayin.installed` are distinguishable and only one of them
 * is telemetry:
 *
 *  - the event-bound listener — the vendor's telemetry listener — which
 *    `getRawListeners()['krayin.installed']` exposes; and
 *  - the wildcard listener registered in
 *    `packages/Webkul/Automation/src/Providers/WorkflowServiceProvider.php:20`,
 *    which subscribes to EVERY event and returns immediately for an event name
 *    that is not in `workflows.trigger_entities` (and `krayin.installed` is
 *    not). It is unrelated to telemetry and must not be removed, so this test
 *    asserts on the event-bound listener rather than on the raw count that
 *    `getListeners()` returns.
 */
it('sends no administrator data to the vendor: no listener is bound to the krayin.installed event', function () {
    $event = 'krayin.installed';

    $bound = app('events')->getRawListeners()[$event] ?? [];

    expect($bound)->toBe(
        [],
        'a listener is bound to '.$event.', which the vendor telemetry '
        .'(Webkul\Installer\Listeners\Installer@installed) transmitted the '
        ."installing administrator's name and email to the vendor's server from"
    );

    /**
     * Belt and braces against a future wildcard re-registration: the vendor
     * listener class must not be reachable for this event through any listener
     * the dispatcher would run, wildcards included.
     */
    $resolved = array_filter(
        app('events')->getListeners($event),
        function ($listener): bool {
            $variables = (new ReflectionFunction($listener))->getStaticVariables();

            return ($variables['listener'] ?? null) === 'Webkul\Installer\Listeners\Installer@installed';
        }
    );

    expect($resolved)->toBe([], 'the vendor telemetry listener is still reachable for '.$event);
});
