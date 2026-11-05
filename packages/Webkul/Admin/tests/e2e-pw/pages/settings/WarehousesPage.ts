// pages/WarehousePage.ts
import { Page, Locator, expect } from '@playwright/test';
import { SettingsPage } from '../SettingsPage';
import { generateDescription, generateEmail, generateLastName, generateLocation, generateName, generatePhoneNumber, generateRandomNumericString } from '../../utils/faker';

export type WarehouseData = {
    name: string;
    description: string;
    contactName: string;
    contactEmail: string;
    contactAddress: string;

    /**
     * ISO 3166-1 alpha-2 country code — the *value* the country select's options carry.
     *
     * Not the country's name: those labels come from the `countries` table in the interface
     * language, so India's option reads `الهند` on this Arabic-first install. Selecting the label
     * `India` waited for an option that no longer exists and timed out. The organization and quote
     * page objects already select by code for the same reason.
     */
    country: string;

    /**
     * The state's ISO 3166-2 code — again the option's value rather than its label.
     *
     * A country with no rows in `country_states` renders this field as free text instead of a
     * list, and `fillContactAddress` types the value in when that is what the form drew.
     */
    state: string;

    city: string;
    zipcode: string;
    phone: string;
};

export const warehouseData: WarehouseData = {
    name: generateLastName(),
    description: generateDescription().slice(0,20),
    contactName: generateLastName(),
    contactEmail:generateEmail(),
    contactAddress: 'Plot 123, Industrial Area, Meerut, UP 250001',
    country: 'IN',
    state: 'UP',
    city: 'Meerut',
    zipcode: '250001',
    phone: generatePhoneNumber(),
};

export class WarehousePage extends SettingsPage {
    readonly page: Page;
    readonly warehouseCreateLink: Locator;
    readonly warehouseNameInput: Locator;
    readonly warehouseAddressInput: Locator;
    readonly saveButton: Locator;
    readonly firstEditButton: Locator;
    readonly firstDeleteButton: Locator;
    readonly successMessage: Locator;
    readonly warehousesTable: Locator;
    readonly contactNameInput: Locator;
    readonly contactEmailInput: Locator;
    readonly contactPhoneInput: Locator;
    readonly contactAddressTextarea: Locator;
    readonly countrySelect: Locator;
    readonly stateSelect: Locator;
    readonly cityInput: Locator;
    readonly zipcodeInput: Locator;
    readonly warehouseDescriptionInput: Locator;

    constructor(page: Page) {
        super(page);
        this.page = page;
        this.warehouseCreateLink = page.getByRole('link', { name: 'Create Warehouse' })
        this.warehouseNameInput = page.getByRole('textbox', { name: 'Name *', exact: true });
        this.warehouseAddressInput = page.locator('textarea[name="contact_address[address]"]');
        this.saveButton = page.getByRole('button', { name: 'Save Warehouse' })
        this.firstEditButton = page.locator('span.cursor-pointer.icon-edit').first();
        this.firstDeleteButton = page.locator('span.cursor-pointer.icon-delete').first();
        this.successMessage = this.flash(/Warehouse.*successfully/);
        this.warehousesTable = page.getByRole('table');
        this.contactNameInput = page.getByRole('textbox', { name: 'Contact Name *' });
        this.contactEmailInput = page.getByRole('textbox', { name: 'Contact Emails *' })
        this.contactPhoneInput = page.getByRole('textbox', { name: 'Contact Numbers' })
        this.contactAddressTextarea = page.locator('textarea[name="contact_address[address]"]');
        this.countrySelect = page.locator('select[name="contact_address[country]"]');
        this.stateSelect = page.locator('select[name="contact_address[state]"]');
        this.cityInput = page.getByRole('textbox', { name: 'City' });
        this.zipcodeInput = page.getByRole('textbox', { name: 'Postcode' });
        this.warehouseDescriptionInput = page.getByRole('textbox', { name: 'Description' });
    }

    async warehouseForm(warehouseData: WarehouseData) {
        await this.warehouseNameInput.fill(warehouseData.name);
        await this.warehouseDescriptionInput.fill(warehouseData.description);
          await this.warehouseAddressInput.fill(warehouseData.contactAddress);
        await this.contactNameInput.fill(warehouseData.contactName);
        await this.contactEmailInput.fill(warehouseData.contactEmail);
        await this.contactPhoneInput.fill(warehouseData.phone);
        await this.contactAddressTextarea.fill(warehouseData.contactAddress);
        await this.fillContactAddress(warehouseData.country, warehouseData.state);
        await this.cityInput.fill(warehouseData.city);
        await this.zipcodeInput.fill(warehouseData.zipcode);
        await this.saveButton.click();
        await expect(this.successMessage.first()).toBeVisible();
        await this.searchWarehouse(warehouseData.contactName);
        await expect(this.page.getByRole('paragraph').filter({ hasText: warehouseData.contactName }).first()).toBeVisible();
        
    }

    /**
     * Choose the country and then the state, both by code.
     *
     * The labels are translated — the country list reads `الهند` here — while the option values are
     * ISO codes (`countries.code`, `country_states.code`), so a code is the only handle that does
     * not depend on the interface language.
     *
     * The state control is not fixed either: the address component
     * (`attributes/edit/address.blade.php`) renders a select only for a country that has rows in
     * `country_states`, and a free-text input for one that has none, so which one it drew is read
     * after the country is applied rather than assumed.
     */
    async fillContactAddress(countryCode: string, stateCode: string): Promise<void> {
        await this.countrySelect.selectOption(countryCode);

        /**
         * The country's own value is what the swap hangs off, so waiting for it orders the read
         * below after the re-render instead of reading the control the old country left behind.
         */
        await expect(this.countrySelect).toHaveValue(countryCode);

        if (await this.stateSelect.count()) {
            await this.stateSelect.selectOption(stateCode);

            return;
        }

        await this.page.locator('input[name="contact_address[state]"]').fill(stateCode);
    }

    async searchWarehouse(warehouseName:string)
    {
        await this.searchInputExact.fill(warehouseName);
        await this.page.keyboard.press('Enter');
        
    }
    async deleteWarehouse()
    {
        await this.firstDeleteButton.click()
        await this.agreeButton.click();
        await expect(this.successMessage.first()).toBeVisible();
    }
}
