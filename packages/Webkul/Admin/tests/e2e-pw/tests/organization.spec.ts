import { expect, test } from "../fixtures/AdminFixtures";
import OrganizationPage, { buildOrganizationData } from "../pages/OrganizationPage";

/**
 * Each case builds and creates the record it acts on.
 *
 * They used to share one module-level `organizationData`: create wrote it, update edited "the first
 * row" and delete searched for the same name. One failure in create therefore produced three red
 * tests - update waited out its whole timeout for a row that was never written, and delete searched
 * for a name that did not exist. The dependency is removed rather than hidden, so a defect fails
 * exactly one test.
 *
 * `test.describe` is synchronous on purpose: Playwright does not await an async describe callback,
 * so registrations made after an `await` inside it would land after collection.
 */
test.describe("organization mangement", () => {
    test("verify organization create", async ({ adminPage }) => {
        const organizationPage = new OrganizationPage(adminPage);
        const orgData = buildOrganizationData();

        await organizationPage.navigateToOrganization();
        await organizationPage.createOrganization(orgData);
    })

    test('verify update organization', async ({ adminPage }) => {
        const organizationPage = new OrganizationPage(adminPage);
        const orgData = buildOrganizationData();

        await organizationPage.navigateToOrganization();
        await organizationPage.createOrganization(orgData);

        /**
         * Renaming on update is what makes the update observable: saving the same values back proves
         * only that the form accepted them.
         */
        const updated = buildOrganizationData({ name: `${orgData.name} Updated` });

        await organizationPage.searchByName(orgData.name);
        await organizationPage.updateOrganization(updated);

        await expect(adminPage.getByText(updated.name).first()).toBeVisible();
    })

    test("verify delete organization", async ({ adminPage }) => {
        const organizationPage = new OrganizationPage(adminPage);
        const orgData = buildOrganizationData();

        await organizationPage.navigateToOrganization();
        await organizationPage.createOrganization(orgData);

        await organizationPage.searchByName(orgData.name);
        await organizationPage.deleteOrganization();

        await expect(organizationPage.successMessage.first()).toBeVisible();
        await expect(adminPage.getByText(orgData.name)).toHaveCount(0);
    })

    test("verify mass delete organization", async ({ adminPage }) => {
        const organizationPage = new OrganizationPage(adminPage);
        const orgData = buildOrganizationData();

        await organizationPage.navigateToOrganization();
        await organizationPage.createOrganization(orgData);

        /*
         * Mass-deleting this record specifically, not "the first row". The assertion inside
         * `massDelete` is that this organization's row is gone, which holds whatever else the
         * listing contains — the previous version asserted the grid had become empty, which was only
         * true on an instance holding a single organization.
         */
        await organizationPage.massDelete(orgData.name);
    })
})
