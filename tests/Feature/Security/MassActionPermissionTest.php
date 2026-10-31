<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * The mass-delete button is drawn from the grid's `mass_actions` payload, so a grid that always
 * registered the action handed every user a control that the ACL already refused to perform. The
 * server stayed safe — `admin.{module}.mass_delete` is mapped to the module's `delete` permission in
 * `acl.php` and the Bouncer middleware fails closed — but the UI promised an operation that could
 * only ever end in a 401.
 *
 * These tests pin both halves: the action is absent from the grid unless the role holds the delete
 * permission, and a forged request to the same route is still refused.
 */
uses(DatabaseTransactions::class);

/**
 * A user whose role holds exactly the given permission keys.
 */
function massActionUser(array $permissions, string $permissionType = 'custom'): User
{
    $role = Role::create([
        'name' => 'Mass Action Role '.bin2hex(random_bytes(6)),
        'description' => 'Created by the mass action permission tests.',
        'permission_type' => $permissionType,
        'permissions' => $permissionType === 'all' ? null : $permissions,
    ]);

    return User::create([
        'name' => 'Mass Action Probe',
        'email' => 'mass-action-'.bin2hex(random_bytes(6)).'@example.invalid',
        'password' => bcrypt('correct-horse-battery-staple'),
        'status' => 1,
        'role_id' => $role->id,
        'view_permission' => 'global',
    ]);
}

/**
 * The mass action urls a grid serves to the given user.
 */
function massActionUrls(User $user, string $gridUrl): array
{
    $response = test()
        ->actingAs($user, 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get($gridUrl);

    expect($response->getStatusCode())->toBe(200);

    return collect($response->json('mass_actions') ?? [])
        ->pluck('url')
        ->filter()
        ->values()
        ->all();
}

dataset('massDeleteGrids', [
    'leads' => ['leads', '/admin/leads', 'leads.delete', '/admin/leads/mass-destroy', 'post'],
    'quotes' => ['quotes', '/admin/quotes', 'quotes.delete', '/admin/quotes/mass-destroy', 'post'],
    'products' => ['products', '/admin/products', 'products.delete', '/admin/products/mass-destroy', 'post'],
    'organizations' => ['contacts.organizations', '/admin/contacts/organizations', 'contacts.organizations.delete', '/admin/contacts/organizations/mass-destroy', 'put'],
    'activities' => ['activities', '/admin/activities/get', 'activities.delete', '/admin/activities/mass-destroy', 'post'],
]);

it('hides the mass-delete action from a role without the delete permission', function (
    string $viewPermission,
    string $gridUrl,
    string $deletePermission,
    string $massDeleteUrl
) {
    securityTestPipeline();

    $urls = massActionUrls(massActionUser([$viewPermission]), $gridUrl);

    expect($urls)->not->toContain(url($massDeleteUrl));
})->with('massDeleteGrids');

it('offers the mass-delete action to a role holding the delete permission', function (
    string $viewPermission,
    string $gridUrl,
    string $deletePermission,
    string $massDeleteUrl
) {
    securityTestPipeline();

    $urls = massActionUrls(massActionUser([$viewPermission, $deletePermission]), $gridUrl);

    expect($urls)->toContain(url($massDeleteUrl));
})->with('massDeleteGrids');

it('refuses a forged mass-delete request from a role without the delete permission', function (
    string $viewPermission,
    string $gridUrl,
    string $deletePermission,
    string $massDeleteUrl,
    string $method
) {
    securityTestPipeline();

    $user = massActionUser([$viewPermission]);

    /**
     * The grid itself stays reachable: the refusal below is the delete permission, not a blanket
     * denial of the module.
     */
    expect(massActionUrls($user, $gridUrl))->toBeArray();

    $response = test()
        ->actingAs($user, 'user')
        ->{$method}($massDeleteUrl, ['indices' => [1]]);

    expect($response->getStatusCode())->toBe(401);
})->with('massDeleteGrids');
