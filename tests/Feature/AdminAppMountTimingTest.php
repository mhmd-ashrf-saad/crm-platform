<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * The admin app used to be mounted on the window `load` event (bead crm-b73).
 *
 * `load` waits for every subresource — the Google Fonts stylesheet, images, the preloaded logo —
 * and the server-rendered Blade fields are interactive the whole time. They are bound with
 * `v-bind="field"`, so a value typed in that window is written into a DOM node that `app.mount()`
 * then re-renders from Vue's own state, and the value is silently discarded.
 *
 * The mount now waits for `DOMContentLoaded`, which fires as soon as parsing is done and every
 * deferred module script (the `window.app` global, and the page's own component registrations) has
 * run. These assertions pin the wiring on the rendered page, because that is what the browser
 * executes; `packages/Webkul/Admin` is not modified, the overrides live in
 * `packages/Sijilna/Brand/resources/views/admin/components/layouts/`.
 */
it('mounts the admin app on DOMContentLoaded instead of the load event', function () {
    $response = $this->actingAs(getDefaultAdmin(), 'user')->get('/admin/contacts/persons');

    $response->assertOk();

    expect($response->getContent())
        ->toContain('const mountApp = () => window.app.mount("#app")')
        ->toContain('document.addEventListener("DOMContentLoaded", mountApp, { once: true })')
        ->not->toContain('window.addEventListener("load", function(event)');
});

/**
 * The login screen renders through the same layout family, and loses typed credentials the same
 * way — a password field emptied by a late mount looks like a wrong password.
 */
it('mounts the login app on DOMContentLoaded instead of the load event', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();

    expect($response->getContent())
        ->toContain('const mountApp = () => window.app.mount("#app")')
        ->toContain('document.addEventListener("DOMContentLoaded", mountApp, { once: true })')
        ->not->toContain('window.addEventListener("load", function(event)');
});
