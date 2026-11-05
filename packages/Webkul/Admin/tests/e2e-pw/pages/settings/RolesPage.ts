import { expect, Locator, Page } from "@playwright/test";

import { SettingsPage } from "../SettingsPage";
import { generateDescription, generateFirstName } from "../../utils/faker";
import { PermissionType, runSuffix } from "../../utils/acl";

export type RoleData = {
    name: string;
    description: string;
    permissionType: PermissionType;

    /**
     * ACL keys to tick in the access-control tree. Only read when `permissionType` is `custom`.
     */
    permissions?: string[];
};

/**
 * Build role data with a unique name, so repeated runs never collide in the listing.
 */
export function buildRoleData(overrides: Partial<RoleData> = {}): RoleData {
    return {
        name: `${generateFirstName()} Role ${runSuffix()}`,
        description: generateDescription().slice(0, 60),
        permissionType: "custom",
        permissions: [],
        ...overrides,
    };
}

export class RolesPage extends SettingsPage {
    readonly page: Page;

    readonly createRoleButton: Locator;
    readonly saveRoleButton: Locator;

    readonly nameInput: Locator;
    readonly descriptionTextarea: Locator;
    readonly permissionTypeSelect: Locator;

    readonly createSuccessMessage: Locator;
    readonly updateSuccessMessage: Locator;
    readonly deleteSuccessMessage: Locator;

    readonly permissionsExceedOwnError: Locator;
    readonly currentRoleEditError: Locator;
    readonly currentRoleDeleteError: Locator;
    readonly beingUsedError: Locator;

    constructor(page: Page) {
        super(page);
        this.page = page;

        this.createRoleButton = page.getByRole("link", { name: "Create Roles" });
        this.saveRoleButton = page.getByRole("button", { name: "Save Role" });

        this.nameInput = page.locator('input[name="name"]');
        this.descriptionTextarea = page.locator('textarea[name="description"]');
        this.permissionTypeSelect = page.locator('select[name="permission_type"]');

        this.createSuccessMessage = this.flash("Role created successfully.");
        this.updateSuccessMessage = this.flash("Role updated successfully.");
        this.deleteSuccessMessage = this.flash("Role deleted successfully.");

        this.permissionsExceedOwnError = page.getByText(
            "You can only create or edit roles whose permissions are the same as or fewer than your own."
        );
        this.currentRoleEditError = page.getByText("Can not edit the role assigned to your own account.");
        this.currentRoleDeleteError = page.getByText("Can not delete role assigned to the current user.");
        this.beingUsedError = this.flash("Role can not be deleted, as this is being used in admin user.");
    }

    /**
     * The access-control tree renders each ACL key as a hidden checkbox carrying the key as its
     * `value`.
     *
     * The `id` is not usable as an anchor: the tree component prefixes it with a token regenerated
     * on every render (`mttr4p8q_leads.edit`), so only `value` is stable across page loads. The
     * input itself is `hidden`, so the wrapping label is what a user actually clicks.
     */
    permissionCheckbox(key: string): Locator {
        return this.page.locator(`input[name="permissions[]"][value="${key}"]`);
    }

    permissionLabel(key: string): Locator {
        return this.page.locator(`label:has(input[name="permissions[]"][value="${key}"])`);
    }

    async navigateToCreateRole(): Promise<void> {
        await this.page.goto("admin/settings/roles/create");
    }

    /**
     * Tick an ACL key in the tree.
     *
     * Selection is hierarchical in both directions: ticking a key also ticks its ancestors AND its
     * whole subtree. Ticking `leads` therefore grants `leads.delete` too.
     *
     * Pass only the most specific keys a role should hold and let the ancestors follow — listing a
     * parent alongside its child silently widens the role far beyond what the test intended.
     */
    async selectPermission(key: string): Promise<void> {
        const label = this.permissionLabel(key);

        await expect(label, `permission "${key}" should be offered in the tree`).toBeVisible();

        if (!(await this.permissionCheckbox(key).isChecked())) {
            await label.click();
        }
    }

    async deselectPermission(key: string): Promise<void> {
        if (await this.permissionCheckbox(key).isChecked()) {
            await this.permissionLabel(key).click();
        }
    }

    async selectPermissions(keys: string[]): Promise<void> {
        for (const key of keys) {
            await this.selectPermission(key);
        }
    }

    /**
     * Whether an ACL key is offered at all. `Acl::getAuthorizedItems()` prunes the tree to the
     * acting user's own permissions, so a key being absent is itself the assertion in the
     * escalation suites.
     */
    async isPermissionOffered(key: string): Promise<boolean> {
        return (await this.permissionLabel(key).count()) > 0;
    }

    async fillRoleForm(role: RoleData): Promise<void> {
        await this.nameInput.fill(role.name);
        await this.descriptionTextarea.fill(role.description);
        await this.permissionTypeSelect.selectOption(role.permissionType);

        if (role.permissionType === "custom") {
            await this.selectPermissions(role.permissions ?? []);
        }
    }

    /**
     * Create a role through the form and confirm it lands in the listing.
     */
    async createRole(role: RoleData): Promise<void> {
        await this.navigateToCreateRole();
        await this.fillRoleForm(role);
        await this.saveRoleButton.click();

        await expect(this.createSuccessMessage.first()).toBeVisible();

        await this.searchRole(role.name);

        await expect(this.page.getByText(role.name).first()).toBeVisible();
    }

    /**
     * Submit the create form without asserting the outcome, for cases where the expected result is
     * a refusal rather than a new role.
     */
    async submitRoleForm(role: RoleData): Promise<void> {
        await this.navigateToCreateRole();
        await this.fillRoleForm(role);
        await this.saveRoleButton.click();
    }

    async searchRole(name: string): Promise<void> {
        await this.navigateToRoles();
        await this.searchInputExact.fill(name);
        await this.page.keyboard.press("Enter");
        await this.page.waitForLoadState("networkidle");
    }

    /**
     * The name the roles listing shows for a role id.
     *
     * The seeded administrator role is named in whichever language the install was seeded in —
     * `مسؤول` on this Arabic-first fork, `Administrator` upstream — because the installer names it
     * with `installer::app.seeders.user.role.administrator` in the install's own locale. A spec
     * that searched for the English name therefore landed on an empty listing and then clicked
     * whichever edit or delete icon happened to be first, which is why the "refuses to delete"
     * case reported a success it never earned.
     *
     * The name comes back from the listing endpoint the grid itself calls, and the record is found
     * through the id in its own action URLs rather than by name, so nothing here is language
     * dependent. Callers then search the grid by that name, which keeps the behaviour under test —
     * deleting or editing the role the signed-in account holds — exactly the same as before.
     */
    async roleNameById(roleId: number): Promise<string> {
        const name = await this.page.evaluate(async (id: number) => {
            const response = await fetch(`/admin/settings/roles?pagination[per_page]=100`, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
                credentials: "same-origin",
            });

            if (! response.ok) {
                throw new Error(`the roles listing answered HTTP ${ response.status }`);
            }

            const body = await response.json();

            /**
             * Matched on the id its action URLs end with (`…/roles/edit/1` for the edit form,
             * `…/roles/1` for the delete route) rather than on a shape those routes could change:
             * the id is the last path segment of both.
             */
            const record = (body.records ?? []).find((row: any) =>
                (row.actions ?? []).some(
                    (action: any) => Number(/(\d+)$/.exec(action.url ?? "")?.[1]) === id
                )
            );

            return record?.name ?? null;
        }, roleId);

        expect(name, `the roles listing should offer role ${ roleId }`).toBeTruthy();

        return name as string;
    }

    /**
     * Open a named role's edit form by clicking its row's edit icon after searching for it, so the
     * test never depends on the role's position in an unfiltered listing.
     */
    async openRoleForEdit(name: string): Promise<void> {
        await this.searchRole(name);
        await this.firstEditIcon.click();
        await this.page.waitForLoadState("networkidle");
    }

    async updateRole(name: string, changes: Partial<RoleData>): Promise<void> {
        await this.openRoleForEdit(name);

        if (changes.name) {
            await this.nameInput.fill(changes.name);
        }

        if (changes.description) {
            await this.descriptionTextarea.fill(changes.description);
        }

        if (changes.permissionType) {
            await this.permissionTypeSelect.selectOption(changes.permissionType);
        }

        if (changes.permissions?.length) {
            await this.selectPermissions(changes.permissions);
        }

        await this.saveRoleButton.click();
    }

    async deleteRole(name: string): Promise<void> {
        await this.searchRole(name);
        await this.firstDeleteIcon.click();
        await this.agreeButton.click();
    }
}
