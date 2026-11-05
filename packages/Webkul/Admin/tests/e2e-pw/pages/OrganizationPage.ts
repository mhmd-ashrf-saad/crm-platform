import { expect, Locator, Page } from "playwright/test";
import CoreLocators from "../locator/CoreLocators";
import { generateFirstName, generateLocation, generateName } from "../utils/faker";
export type OrganizationData = {
    name: string;
    address: string;
    country: string; // country code, e.g. 'IN'
    state: string;   // state code, e.g. 'DL'
    city: string;
    postcode: string;
};

/**
 * Build an organization the caller owns.
 *
 * The shared `organizationData` below is a constant, but a spec that let create, update and delete
 * all act on it turned one failure into three: update waited out its whole test timeout for a row
 * that was never written, and delete then searched for a name that did not exist. Each case builds
 * its own instance instead, so one defect fails one test.
 */
export function buildOrganizationData(overrides: Partial<OrganizationData> = {}): OrganizationData {
    return {
        name: generateName(),
        address: generateLocation(),
        country: "IN",
        state: "DL",
        city: "New Delhi",
        postcode: "110015",
        ...overrides,
    };
}

/**
 * Default instance kept for existing importers (the person form and the data-transfer spec read the
 * name). Do not let more than one case act on it.
 */
export const organizationData: OrganizationData = buildOrganizationData();
export default class OrganizationPage extends CoreLocators{

    
    readonly page:Page
    readonly firstOrganization:Locator;
    constructor(page:Page)
    {
        super(page)
        this.page=page
        this.firstOrganization= page.locator(`(//p[@class="break-words"])[1]`);

    }
    async navigateToOrganization()
    {
       await  this.page.goto("admin/contacts/organizations");
    }

    async createOrganization(orgData:OrganizationData) {
    /**
     * The Sales Owner this organization is assigned to: the account the suite is signed in as, read
     * from its own account screen. Reading it visits that screen, so it happens before the form is
     * reached and the listing is re-opened afterwards. See `currentUserName()`.
     *
     * It used to be the literal "Example", which only matched because `tests/account.spec.ts`
     * renames the signed-in administrator earlier in a full run.
     */
    const salesOwner = await this.currentUserName();

    await this.navigateToOrganization();
    await this.createOrgLink.click();

    // Fill organization details
    await this.orgNameTextbox.fill(orgData.name);
    await this.orgAddressTextarea.fill(orgData.address);

    await this.orgCountryCombobox.selectOption(orgData.country);
    await this.orgStateSelect.selectOption(orgData.state);

    await this.orgCityTextbox.fill(orgData.city);
    await this.orgPostcodeTextbox.fill(orgData.postcode);

    /**
     * Sales Owner is the organization form's one lookup attribute. Every locator is scoped to that
     * lookup's own root, identified by the hidden input it renders (`user_id`); the previous
     * whole-page `getByRole('listitem').filter({ hasText: 'Example' })` matched the listitem of
     * whatever picker happened to be open, not necessarily this one.
     */
    await this.selectSalesOwner(salesOwner);

    // Save organization
    await this.saveOrganizationButton.click();

    /**
     * The flash first, the row second, and the order is the point.
     *
     * The toast lives for five seconds (`flash-group/item.blade.php`, `duration: 5000`) and this
     * assertion used to run after the row had been waited for, which on a slow page is longer than
     * that: with the DebugBar mounted, the grid's own reload took the toast with it and the whole
     * document still matched — because the DebugBar dumps the AJAX response body, flash text
     * included. Scoped to `#app` the assertion can only see the toast, so it has to be made while the
     * toast is the thing that exists: immediately after the save that produced it.
     */
    await expect(this.organizationCreateSuccessMessage.first()).toBeVisible();

    /**
     * The created row in the list is the second signal: the toast acknowledges the save, the row is
     * the record really being there.
     */
    await expect(this.page.getByText(orgData.name).first()).toBeVisible();
    }



    async updateOrganization(orgData:OrganizationData)
    {
    
    
    await this.firstEditIcon.click();
    // Fill organization details
    await this.orgNameTextbox.fill(orgData.name);
    await this.orgAddressTextarea.fill(orgData.address);

    await this.orgCountryCombobox.first().selectOption(orgData.country);
    await this.orgStateSelect.selectOption(orgData.state);

    await this.orgCityTextbox.fill(orgData.city);
    await this.orgPostcodeTextbox.fill(orgData.postcode);

    // Save organization
    await this.saveOrganizationButton.click();
    return orgData;

    }

    async deleteOrganization(){

       await this.firstDeleteIcon.click();
       await this.agreeButton.click();
    }

}